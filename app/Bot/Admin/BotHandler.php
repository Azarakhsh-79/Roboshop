<?php

namespace App\Bot\Admin;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\User;

class BotHandler
{
    use BotHelpersTrait;

    protected $chatId;
    protected $text;
    protected $messageId;
    protected $messageData;
    protected $user;
    protected $botDatabaseId;
    

    public function __construct($chatId, $text, $messageId, $messageData, $user = null, $bot = null)
    {
        $this->chatId = $chatId;
        $this->text = $text;
        $this->messageId = $messageId;
        $this->messageData = $messageData;

        if ($bot) {
            $this->botDatabaseId = $bot->id;
        }

        if ($user && $bot) {
            $this->user = $user;
            App::setLocale($this->user->language ?? 'fa');
            $this->session = Cache::get('user_session_' . $this->chatId, []);
        } elseif ($chatId) {
            $this->initializeUserAndBot();
        }
    }

    public function handleRequest()
    {
        $isGlobalCommand = match (true) {
            str_starts_with($this->text, '/start') => true,
            $this->text === '/cancel' => true,
            default => false,
        };

        if ($isGlobalCommand) {
            if (str_starts_with($this->text, '/start')) {
                $this->clearSession();

                $parts = explode(' ', $this->text);
                if (count($parts) > 1) {
                    $token = $parts[1];
                    $this->handleDeepLinkToken($token);
                }

                $this->showMainMenu();
            } elseif ($this->text === '/cancel') {
                $this->clearSession();
                $this->deleteMessage($this->messageId);
                $this->sendRequest('sendMessage', [
                    'chat_id' => $this->chatId,
                    'text' => __('bot.creation.cancelled'),
                    'reply_markup' => json_encode(['remove_keyboard' => true])
                ]);
                $this->showMainMenu();
            }
            return;
        }

        $state = $this->getSession('state');

        if ($state) {
            if (str_starts_with($state, 'addBot_')) {
                $this->handleAddBotProcess();
                return;
            }
        }

        $this->handleUnknownCommand();
    }

    public function handleCallbackQuery($callbackQuery)
    {
        $data = $callbackQuery['data'];
        $messageId = $callbackQuery['message']['message_id'];
        $callbackId = $callbackQuery['id']; // استخراج آیدی کال‌بک برای پاسخ‌های پاپ‌آپ

        // همگام‌سازی messageId کلاس با آیدی پیام دکمه شیشه‌ای
        $this->messageId = $messageId;

        if ($data === 'menu_add_bot') {
            $this->updateSession(['state' => 'addBot_start']);
            $this->handleAddBotProcess();
        } elseif ($data === 'addBot_method_token') {
            $this->updateSession(['state' => 'addBot_waiting_token']);
            $this->promptForBotToken($messageId);
        } elseif ($data === 'addBot_method_managed') {
            $this->updateSession(['state' => 'addBot_waiting_creation']);
            $this->showManagedBotLink($messageId);
        } elseif ($data === 'menu_my_bots') {
            $this->showMyBots($messageId);
        } elseif (str_starts_with($data, 'select_bot_')) {
            $botId = str_replace('select_bot_', '', $data);
            $this->manageSpecificBot($botId, $messageId);
        } elseif ($data === 'menu_support') {
            $this->showSupportMenu($messageId);
        } elseif ($data === 'menu_guide') {
            $this->showGuideMenu($messageId);
        } elseif ($data === 'menu_settings') {
            $this->showSettingsMenu($messageId);
        } elseif ($data === 'menu_change_lang') {
            $this->showLanguagePicker($messageId);
        } elseif (str_starts_with($data, 'set_lang_')) {
            $this->changeUserLanguage(str_replace('set_lang_', '', $data), $messageId);
        } elseif ($data === 'back_to_main') {
            $this->showMainMenu($messageId);
        } elseif ($data === 'cancel_process') {
            $this->clearSession();
            $this->showMainMenu($messageId);
        } elseif ($data === 'skip_phone') {
            $this->handleSkipPhone($messageId);

            // =================================================================
            // ✅ تایید فیش واریزی و ارتقای پلن فروشگاه
            // =================================================================
        } elseif (str_starts_with($data, 'superadmin_sub_approve_')) {
            $paymentId = str_replace('superadmin_sub_approve_', '', $data);
            $payment = \App\Models\SubscriptionPayment::with(['bot', 'user'])->find($paymentId);

            if (!$payment || $payment->status !== 'awaiting_approval') {
                $this->sendRequest('answerCallbackQuery', [
                    'callback_query_id' => $callbackId,
                    'text' => "⚠️ این فیش قبلاً بررسی شده است.",
                    'show_alert' => true
                ]);
                return;
            }

            // ۱. آپدیت وضعیت پرداخت
            $payment->update([
                'status' => 'paid',
                'approver_chat_id' => $this->chatId
            ]);

            // ۲. تمدید یا ارتقای پلن ربات
            $bot = $payment->bot;
            $currentExpireStr = $bot->subscription_expires_at ?? now();
            $currentExpire = \Carbon\Carbon::parse($currentExpireStr);

            if ($currentExpire->isPast()) {
                $currentExpire = now();
            }

            $newExpireDate = $currentExpire->copy()->addDays($payment->duration_days);

            $bot->update([
                'subscription_plan' => $payment->plan_key,
                'subscription_expires_at' => $newExpireDate,
                'status' => 'active'
            ]);

            // ۳. پاک کردن کش ربات برای اعمال فوری دسترسی‌ها
            if ($bot->webhook_secret) {
                \Cache::forget("bot_config:{$bot->webhook_secret}");
            }

            // ۴. آپدیت پیام ادمین (حذف دکمه‌ها و تغییر کپشن)
            $newCaption = "✅ <b>تایید شد</b>\n\n" .
                "اشتراک {$payment->plan_name} برای فروشگاه <b>{$bot->bot_name}</b> با موفقیت فعال شد.\n" .
                "اعتبار جدید: " . \Morilog\Jalali\Jalalian::fromCarbon($newExpireDate)->format('Y/m/d');

           Http::post("https://api.telegram.org/bot" . config('telegram.admin.bot_token') . "/editMessageCaption", [
                'chat_id' => $this->chatId,
                'message_id' => $this->messageId,
                'caption' => $newCaption,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => []])
            ]);

            // ۵. ارسال پیام تبریک به مدیر فروشگاه
            $shopOwnerText = "🎉 <b>پرداخت شما تایید شد!</b>\n\n" .
                "اشتراک <b>{$payment->plan_name}</b> با موفقیت روی فروشگاه شما اعمال گردید.\n" .
                "⏳ اعتبار تا تاریخ: " . \Morilog\Jalali\Jalalian::fromCarbon($newExpireDate)->format('Y/m/d') . "\n\n" .
                "پر روزی باشید! 🌹";

           Http::post("https://api.telegram.org/bot{$bot->bot_token}/sendMessage", [
                'chat_id' => $payment->user->chat_id,
                'text' => $shopOwnerText,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '⚙️ ورود به داشبورد', 'callback_data' => 'admin_panel']]]])
            ]);

            // =================================================================
            // ❌ رد فیش واریزی
            // =================================================================
        } elseif (str_starts_with($data, 'superadmin_sub_reject_')) {
            $paymentId = str_replace('superadmin_sub_reject_', '', $data);
            $payment = \App\Models\SubscriptionPayment::with(['bot', 'user'])->find($paymentId);

            if (!$payment || $payment->status !== 'awaiting_approval') {
                $this->sendRequest('answerCallbackQuery', [
                    'callback_query_id' => $callbackId,
                    'text' => "⚠️ این فیش قبلاً بررسی شده است.",
                    'show_alert' => true
                ]);
                return;
            }

            // ۱. آپدیت وضعیت پرداخت
            $payment->update([
                'status' => 'failed',
                'approver_chat_id' => $this->chatId
            ]);

            // ۲. آپدیت پیام ادمین (حذف دکمه‌ها و تغییر کپشن)
            $newCaption = "❌ <b>رد شد</b>\n\nفیش ارسالی برای فروشگاه <b>{$payment->bot->bot_name}</b> نامعتبر تشخیص داده شد.";

           Http::post("https://api.telegram.org/bot" . config('telegram.admin.bot_token') . "/editMessageCaption", [
                'chat_id' => $this->chatId,
                'message_id' => $this->messageId,
                'caption' => $newCaption,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => []])
            ]);

            // ۳. ارسال پیام خطا به مدیر فروشگاه
            $shopOwnerText = "⚠️ <b>فیش شما تایید نشد!</b>\n\n" .
                "مدیر عزیز، تصویر فیش پرداختی شما برای تمدید اشتراک نامعتبر بود.\n" .
                "لطفاً در صورت بروز مشکل با پشتیبانی پلتفرم در تماس باشید.";

           Http::post("https://api.telegram.org/bot{$payment->bot->bot_token}/sendMessage", [
                'chat_id' => $payment->user->chat_id,
                'text' => $shopOwnerText,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '🔄 تلاش مجدد', 'callback_data' => 'admin_subscription']]]])
            ]);

        } else {
            $this->handleUnknownCommand();
        }
    }

    public function handleManagedBotUpdate(array $update)
    {
        Log::info('[Bot Creation] Step 1: Received ManagedBot update.', ['update' => $update]);

        $managedBot = $update['managed_bot'] ?? null;
        if (!$managedBot) {
            Log::warning('[Bot Creation] Aborted: No managed_bot data in update.');
            return;
        }

        $botInfo = $managedBot['bot'] ?? [];
        $creator = $managedBot['user'] ?? [];

        $creatorUserId = $creator['id'] ?? null;
        if (!$creatorUserId) {
            Log::error('[Bot Creation] Aborted: Managed bot update missing creator id.', ['payload' => $managedBot]);
            return;
        }

        $user = User::where('chat_id', $creatorUserId)
            ->where('bot_id', $this->botDatabaseId)
            ->first();

        if (!$user) {
            Log::error("[Bot Creation] Aborted: User not found for creator chat_id: {$creatorUserId}");
            return;
        }

        Log::info('[Bot Creation] Step 2: Creator user identified successfully.', [
            'user_id' => $user->id,
            'phone' => $user->phone
        ]);

        $token = $this->getManagedBotToken($botInfo['id']);
        if (!$token) {
            Log::error("[Bot Creation] Aborted: Failed to get token for new bot ID: {$botInfo['id']}");
            $this->sendRequest('sendMessage', [
                'chat_id' => $user->chat_id,
                'text' => '❌ دریافت توکن ربات با خطا مواجه شد.',
            ]);
            return;
        }

        Log::info('[Bot Creation] Step 3: Token retrieved successfully. Proceeding to finalize...');

        $this->finalizeBotCreationFromManaged($token, $botInfo, $user);
    }



}
