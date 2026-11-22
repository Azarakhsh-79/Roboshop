<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ManagedBot;
use Illuminate\Support\Facades\Cache;
use App\Models\User;
use App\Bot\Shop\ShopBotHandler;
use Throwable;
use Illuminate\Support\Facades\DB;

class ShopBotController extends Controller
{
    public function handle(Request $request, $secret)
    {
        $requestStart = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);
        $logPerf = config('app.log_bot_performance', false);
        $queryLog = [];
        $status = 'success'; // وضعیت پیش‌فرض موفق
        $chatId = null; // برای ردیابی اسپمرها

        if ($logPerf) {
            DB::listen(function ($query) use (&$queryLog) {
                $queryLog[] = $query->time;
                // ثبت دقیق دستور SQL در صورت کند بودن بیش از حد (مثلاً بالای ۵۰۰ میلی‌ثانیه)
                if ($query->time > 500) {
                    Log::channel('performance')->warning('critical_slow_query', [
                        'time' => $query->time,
                        'sql' => $query->sql
                    ]);
                }
            });
        }

        $telegramHeader = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (empty($telegramHeader) || !hash_equals((string) $secret, (string) $telegramHeader)) {
            return response()->json(['error' => 'Access Denied'], 403);
        }

        $update = $request->all();
        if (empty($update)) {
            if ($logPerf)
                \App\Logging\PerformanceLogger::logBotRequest('empty_update', null, null, 'success', $requestStart, $queryLog, 0);
            return response()->json(['status' => 'no_update']);
        }

        $bot = ManagedBot::with('settings')->where('webhook_secret', $secret)->first();

        if (!$bot || !in_array($bot->status, ['active', 'pending'])) {
            if ($logPerf)
                \App\Logging\PerformanceLogger::logBotRequest('bot_unavailable', $bot?->id, null, 'failed', $requestStart, $queryLog, 0);
            return response()->json(['error' => 'Bot Unavailable'], 403);
        }

        $telegramUser = $this->extractTelegramUser($update);
        $user = null;

        if ($telegramUser) {
            $chatId = $telegramUser['id']; // گرفتن آیدی کاربر برای لاگ
        }

        if ($telegramUser && !str_starts_with($update['message']['text'] ?? '', '/start verify_')) {

            $chatType = $update['message']['chat']['type'] ?? $update['callback_query']['message']['chat']['type'] ?? 'private';

            if ($chatType !== 'private') {
                if ($logPerf)
                    \App\Logging\PerformanceLogger::logBotRequest('ignored_non_private', $bot->id, $chatId, 'success', $requestStart, $queryLog, 0);
                return response()->json(['status' => 'ignored - not private chat']);
            }

            $user = User::firstOrNew(['bot_id' => $bot->id, 'chat_id' => $chatId]);

            if (!$user->exists) {
                $user->status = 'active';
                $user->language = 'fa';
            }

            $user->username = $telegramUser['username'] ?? null;
            $user->first_name = $telegramUser['first_name'] ?? null;
            $user->last_name = $telegramUser['last_name'] ?? null;

            if ($user->isDirty()) {
                $user->save();
            }

            $isOwnerOrAdmin = (((int) $bot->owner_id === (int) $user->id) || ($user->is_admin == 1 && (int) $user->bot_id === (int) $bot->id));

            if (!$isOwnerOrAdmin && isset($user->status) && !in_array($user->status, ['active', 'pending'])) {
                if ($logPerf)
                    \App\Logging\PerformanceLogger::logBotRequest("user_{$user->status}", $bot->id, $chatId, 'failed', $requestStart, $queryLog, 0);
                // ... ادامه کد مسدودیت ... (بدون تغییر)
                return response()->json(['status' => 'blocked']);
            }

            $isBotActive = $bot->settings->firstWhere('key', 'is_active')?->value === '1';

            if (!$isOwnerOrAdmin && !$isBotActive) {
                if ($logPerf)
                    \App\Logging\PerformanceLogger::logBotRequest('bot_inactive', $bot->id, $chatId, 'failed', $requestStart, $queryLog, 0);
                return response()->json(['status' => 'inactive']);
            }
        }

        $isExpired = $bot->subscription_expires_at?->isPast() ?? false;
        $handler = null;
        $telegramTimeMs = 0;

        try {
            $handler = new ShopBotHandler($bot, $user, $update, $isExpired);
            $handler->handle();
        } catch (Throwable $e) {
            $status = 'failed'; // 👈 ثبت خطا در لاگ پرفورمنس
            Log::error('ShopBot Update Processing Failed', [
                'bot_id' => $bot->id,
                'update_id' => $update['update_id'] ?? null,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        } finally {
            $telegramTimeMs = $handler?->getTelegramTimeMs() ?? 0;
            unset($handler);
        }

        // 🛡 ثبت نهایی لاگ با تمامی پارامترهای جدید
        if ($logPerf) {
            $updateType = $this->detectUpdateType($update);
            \App\Logging\PerformanceLogger::logBotRequest($updateType, $bot->id, $chatId, $status, $requestStart, $queryLog, $telegramTimeMs);
        }

        return response()->json(['status' => 'ok']);
    }
    // ---------------------------------------------------
    // متدهای کمکی جدید (Performance Logging)
    // ---------------------------------------------------

    private function detectUpdateType(array $update): string
    {
        foreach ([
            'message',
            'callback_query',
            'inline_query',
            'pre_checkout_query',
            'shipping_query',
            'my_chat_member',
            'channel_post',
            'edited_channel_post',
            'edited_message'
        ] as $type) {
            if (isset($update[$type])) {
                return $type;
            }
        }
        return 'unknown';
    }

    private function logPerformance(string $updateType, ?ManagedBot $bot, float $requestStart, array $queryLog, float $telegramTimeMs = 0): void
    {
        $durationMs = round((microtime(true) - $requestStart) * 1000, 2);
        $queryTimeMs = round(array_sum($queryLog), 2);
        $otherMs = round($durationMs - $queryTimeMs - $telegramTimeMs, 2);

        Log::channel('performance')->info('bot_request', [
            'bot_id' => $bot?->id,
            'update_type' => $updateType,
            'duration_ms' => $durationMs,
            'query_count' => count($queryLog),
            'query_time_ms' => $queryTimeMs,
            'telegram_time_ms' => round($telegramTimeMs, 2),
            'other_ms' => $otherMs,
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ]);
    }

    public function setWebhook(Request $request, $botId)
    {
        try {
            $bot = ManagedBot::findOrFail($botId);
            $webhookUrl = url("/api/bot/shopbot/{$bot->webhook_secret}");

            $response = Http::post("https://api.telegram.org/bot{$bot->bot_token}/setWebhook", [
                'url' => $webhookUrl,
                'secret_token' => $bot->webhook_secret,
                'allowed_updates' => ['message', 'callback_query', 'inline_query'],
            ]);

            if ($response->successful() && $response->json('ok')) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Webhook set successfully.',
                    // ماسک کردن آدرس وب‌هوک در خروجی API
                    'webhook_url' => url('/api/bot/shopbot/********')
                ]);
            }

            // ثبت خطای تلگرام در لاگ بدون افشای توکن
            $errorDesc = $response->json('description') ?? 'Unknown Telegram Error';
            Log::warning("SetWebhook failed for Bot {$bot->id}: " . $errorDesc);

            return response()->json(['error' => 'Telegram API request failed.'], 500);

        } catch (\Exception $e) {
            // حذف Token و Secret از متن Exception قبل از ثبت در لاگ
            $safeMessage = str_replace(
                [$bot->bot_token, $bot->webhook_secret],
                ['***TOKEN***', '***SECRET***'],
                $e->getMessage()
            );
            Log::error("SetWebhook Exception: " . $safeMessage);

            // ارسال پیام عمومی به کاربر API
            return response()->json(['error' => 'Telegram API request failed.'], 500);
        }
    }

    public function deleteWebhook(Request $request)
    {
        // 🛡 حذف قابلیت اجرای Bulk روی تمام ربات‌ها
        if (!$request->has('token')) {
            return response()->json(['error' => 'Token (Bot Username) is required.'], 400);
        }

        $bot = $this->getBotFromRequest($request);

        try {
            $response = Http::get("https://api.telegram.org/bot{$bot->bot_token}/deleteWebhook");
            $telegramResult = $response->json();
            $isOk = $response->successful() && ($telegramResult['ok'] ?? false);

            return response()->json([
                'action' => 'deleteWebhook',
                'bot_username' => $bot->bot_username,
                'status' => $isOk ? 'success' : 'failed',
                'telegram_response' => $telegramResult['description'] ?? 'Unknown',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'action' => 'deleteWebhook',
                'bot_username' => $bot->bot_username,
                'status' => 'error',
                'message' => 'Telegram API request failed.',
            ], 500);
        }
    }
    public function getWebhookInfo(Request $request, $botId)
    {
        try {
            $bot = ManagedBot::findOrFail($botId);

            $response = Http::get("https://api.telegram.org/bot{$bot->bot_token}/getWebhookInfo");

            if ($response->successful() && $response->json('ok')) {
                $info = $response->json('result');

                // ساخت یک آرایه ایمن برای خروجی API
                $safeInfo = [
                    'has_custom_certificate' => $info['has_custom_certificate'] ?? false,
                    'pending_update_count' => $info['pending_update_count'] ?? 0,
                    'last_error_date' => $info['last_error_date'] ?? null,
                    'last_error_message' => $info['last_error_message'] ?? null,
                    // اگر URL ست شده بود، نسخه ماسک شده را برگردان
                    'webhook_url' => !empty($info['url']) ? url('/api/bot/shopbot/********') : null,
                ];

                return response()->json([
                    'status' => 'success',
                    'info' => $safeInfo
                ]);
            }

            return response()->json(['error' => 'Failed to fetch webhook info.'], 500);

        } catch (\Exception $e) {
            // حذف Token از متن Exception
            $safeMessage = str_replace($bot->bot_token, '***TOKEN***', $e->getMessage());
            Log::error("GetWebhookInfo Exception: " . $safeMessage);

            return response()->json(['error' => 'Telegram API request failed.'], 500);
        }
    }
    // ---------------------------------------------------
    // متدهای کمکی (Private Helpers)
    // ---------------------------------------------------

    private function getBotFromRequest(Request $request)
    {
        $botUsername = ltrim($request->query('token'), '@'); // حذف @ در صورت وجود

        if (!$botUsername) {
            abort(400, 'Token (Bot Username) is required.');
        }

        $bot = ManagedBot::where('bot_username', $botUsername)->first();

        if (!$bot) {
            abort(404, 'Bot not found in database.');
        }

        return $bot;
    }

    private function extractTelegramUser($update)
    {
        if (isset($update['message'])) {
            return $update['message']['from'];
        } elseif (isset($update['callback_query'])) {
            return $update['callback_query']['from'];
        } elseif (isset($update['inline_query'])) {
            return $update['inline_query']['from'];
        } elseif (isset($update['pre_checkout_query'])) {
            return $update['pre_checkout_query']['from'];
        } elseif (isset($update['shipping_query'])) {
            return $update['shipping_query']['from'];
        } elseif (isset($update['my_chat_member'])) {
            return $update['my_chat_member']['from'];
        }

        return null;
    }

}
