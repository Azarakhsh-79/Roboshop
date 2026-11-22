<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Bot\Admin\BotHandler;
use Throwable;

use Illuminate\Support\Facades\Cache;


class AdminTelegramBotController extends Controller
{

    public function handle(Request $request)
    {
        $update = $request->all();
        log::info('Admin Bot Update Received: ', $update);
        if (empty($update)) {
            Log::warning('Admin Bot: Empty update received');
            return response()->json(['status' => 'no_update']);
        }

        try {
            // ---------- کش کردن ربات ادمین (فقط یک بار) ----------
            $adminBot = Cache::remember('admin_bot_record', 86400, function () {
                $token = config('telegram.admin.bot_token');
                return \App\Models\ManagedBot::firstOrCreate(
                    ['bot_token' => $token],
                    [
                        'bot_id_string' => 'admin_bot',
                        'bot_name' => 'AdminBot',
                        'status' => 'active',
                        'subscription_plan' => 'unlimited',
                        'owner_id' => 1,
                    ]
                );
            });

            // ---------- آپدیت مربوط به Telegram Managed Bots (ساخت ربات جدید) ----------
            if (isset($update['managed_bot'])) {
                $botHandler = new BotHandler(null, null, null, null, null, $adminBot);
                $botHandler->handleManagedBotUpdate($update);

                return response()->json(['status' => 'ok']);
            }

            // استخراج اطلاعات مشترک از آپدیت
            $chatId = null;
            $messageId = null;
            $text = null;
            $messageData = null;
            $from = null;
            $isCallback = false;
            $callbackQuery = null;

            if (isset($update['message'])) {
                $message = $update['message'];
                $chatId = $message['chat']['id'] ?? null;
                $messageId = $message['message_id'] ?? null;
                $text = $message['text'] ?? '';
                $messageData = $message;
                $from = $message['from'] ?? null;
            } elseif (isset($update['callback_query'])) {
                $callbackQuery = $update['callback_query'];
                $message = $callbackQuery['message'];
                $chatId = $message['chat']['id'] ?? null;
                $messageId = $message['message_id'] ?? null;
                $text = $callbackQuery['data'] ?? '';
                $messageData = $message;
                $from = $callbackQuery['from'] ?? null;
                $isCallback = true;
            }

            if (!$chatId || !$messageId) {
                return response()->json(['status' => 'ok']);
            }

            // ---------- کش کردن کاربر (با زمان ۱ ساعت) ----------
            $cacheKey = 'user_' . $adminBot->id . '_' . $chatId;
            $user = Cache::remember($cacheKey, 3600, function () use ($chatId, $from, $adminBot) {
                return \App\Models\User::updateOrCreate(
                    ['chat_id' => $chatId, 'bot_id' => $adminBot->id],
                    [
                        'username' => $from['username'] ?? null,
                        'first_name' => $from['first_name'] ?? null,
                        'last_name' => $from['last_name'] ?? null,
                    ]
                );
            });



            // ---------- ساخت نمونه BotHandler با داده‌های کش شده ----------
            $botHandler = new BotHandler(
                $chatId,
                $text,
                $messageId,
                $messageData,
                $user,
                $adminBot
            );

            if ($isCallback) {
                $botHandler->handleCallbackQuery($callbackQuery);
            } else {
                $botHandler->handleRequest();
            }

        } catch (Throwable $e) {
            $this->handleError($e, $update);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * تنظیم وب‌هوک با ارسال secret_token برای امنیت بیشتر
     */
    public function setWebhook(Request $request)
    {
        if ($request->query('secret') !== config('telegram.admin.webhook_secret')) {
            abort(403, 'Unauthorized');
        }

        $token = config('telegram.admin.bot_token');
        $url = config('telegram.admin.webhook_url');
        $secretToken = config('telegram.admin.secret_token');

        $response = Http::post("https://api.telegram.org/bot{$token}/setWebhook", [
            'url' => $url,
            'secret_token' => $secretToken,
            'allowed_updates' => ['message', 'callback_query', 'managed_bot' , 'inline_query' ],
        ]);

        return $response->json();
    }

    /**
     * حذف وب‌هوک
     */
    public function deleteWebhook(Request $request)
    {
        if ($request->query('secret') !== config('telegram.admin.webhook_secret')) {
            abort(403, 'Unauthorized');
        }

        $token = config('telegram.admin.bot_token');
        return Http::get("https://api.telegram.org/bot{$token}/deleteWebhook")->json();
    }

    /**
     * دریافت اطلاعات وب‌هوک فعلی
     */
    public function getWebhookInfo(Request $request)
    {
        if ($request->query('secret') !== config('telegram.admin.webhook_secret')) {
            abort(403, 'Unauthorized');
        }

        $token = config('telegram.admin.bot_token');
        return Http::get("https://api.telegram.org/bot{$token}/getWebhookInfo")->json();
    }

    /**
     * مدیریت خطا با ارسال پیام به سوپردمین (در صورت وجود)
     */
    private function handleError(Throwable $e, array $update)
    {
        Log::error('AdminBot Exception: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'update' => $update
        ]);

        $chatId = $update['message']['chat']['id']
            ?? $update['callback_query']['message']['chat']['id']
            ?? null;

        if (!$chatId) {
            return;
        }

        $token = config('telegram.admin.bot_token');
        $superAdminId = config('telegram.admin.super_admin_id');

        if ($superAdminId && (string) $chatId === (string) $superAdminId) {
            $errorMessage = "❌ **System Error:**\n\nMessage: `{$e->getMessage()}`\nLine: {$e->getLine()}\nFile: {$e->getFile()}";
        } else {
            $errorMessage = "❌ خطایی در پردازش رخ داد. لطفاً دقایقی دیگر تلاش کنید.";
        }

        try {
            Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $errorMessage,
                'parse_mode' => 'Markdown'
            ]);
        } catch (Throwable $ignored) {
            // خطا در ارسال پیام خطا – لاگ می‌کنیم اما ادامه می‌دهیم
            Log::error('Failed to send error message to user', ['chat_id' => $chatId]);
        }
    }
}
