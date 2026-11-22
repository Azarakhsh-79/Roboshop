<?php

namespace App\Bot\Admin;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\ManagedBot;
use App\Models\Setting;

trait BotHelpersTrait
{
    protected array $session = [];

    // ================================================================
    // 💾 بخش ۱: مدیریت نشست کاربر (User Session Management)
    // ================================================================

    /**
     * دریافت اطلاعات از سشن لود شده (بدون درخواست به کش)
     */
    protected function getSession($key = null, $default = null)
    {
        // چون سشن در متد initializeUserAndBot پر شده، مستقیم از متغیر می‌خوانیم
        if ($key === null) {
            return $this->session;
        }

        return $this->session[$key] ?? $default;
    }

    /**
     * بروزرسانی سشن (هم در متغیر کلاس و هم در کش)
     */
    protected function updateSession(array $newData)
    {
        // ۱. آپدیت متغیر لوکال (برای استفاده‌های بعدی در همین درخواست)
        $this->session = array_merge($this->session, $newData);

        // ۲. ذخیره در کش (برای درخواست‌های بعدی)
        Cache::put('user_session_' . $this->chatId, $this->session, 3600);
    }

    /**
     * پاک کردن کامل سشن
     */
    protected function clearSession()
    {
        $this->session = [];
        Cache::forget('user_session_' . $this->chatId);
    }

    // ================================================================
    // 🚀 بخش ۲: هسته مرکزی و راه‌اندازی (Core & Init)
    // ================================================================

    /**
     * راه‌اندازی اولیه: شناسایی ربات، کاربر و لود کردن سشن
     */
    /**
     * راه‌اندازی اولیه: شناسایی ربات، کاربر و لود کردن سشن
     */
    protected function initializeUserAndBot()
    {
        // فقط برای مواقعی که کاربر و ربات از بیرون داده نشده باشد
        $token = config('telegram.admin.bot_token');
        $bot = ManagedBot::firstOrCreate(
            ['bot_token' => $token],
            [
                'bot_id_string' => 'admin_bot',
                'bot_name' => 'AdminBot',
                'status' => 'active',
                'subscription_plan' => 'unlimited',
                'owner_id' => 1,
            ]
        );
        $this->botDatabaseId = $bot->id;

        $from = $this->messageData['from'] ?? null;
        if ($from) {
            $this->user = User::updateOrCreate(
                ['chat_id' => $this->chatId, 'bot_id' => $this->botDatabaseId],
                [
                    'username' => $from['username'] ?? null,
                    'first_name' => $from['first_name'] ?? null,
                    'last_name' => $from['last_name'] ?? null,
                ]
            );

            // =================================================================
            // 🚀 ارسال نوتیفیکیشن فقط در صورتی که کاربر واقعاً "جدید" باشد
            // =================================================================
            if ($this->user->wasRecentlyCreated) {
                $superAdminId = env('SUPER_ADMIN_ID');

                // جلوگیری از ارسال پیام به خودتان اگر با اکانت سوپرادمین استارت کردید
                if ($superAdminId && (string) $this->chatId !== (string) $superAdminId) {
                    $username = $this->user->username ? '@' . $this->user->username : 'بدون یوزرنیم';
                    $firstName = $this->user->first_name ?? 'بدون نام';

                    $notifyText = "🚨 <b>عضو جدید در ربات مادر (ثبت‌نام)!</b>\n\n";
                    $notifyText .= "👤 <b>نام:</b> {$firstName}\n";
                    $notifyText .= "▫️ <b>یوزرنیم:</b> {$username}\n";
                    $notifyText .= "▫️ <b>شناسه (Chat ID):</b> <code>{$this->chatId}</code>\n";

                    $this->sendRequest('sendMessage', [
                        'chat_id' => $superAdminId,
                        'text' => $notifyText,
                        'parse_mode' => 'HTML',
                    ]);
                }
            }

            App::setLocale($this->user->language ?? 'fa');
        }
        $this->session = Cache::get('user_session_' . $this->chatId, []);
    }

    /**
     * ارسال درخواست به تلگرام
     */
    public function sendRequest(string $method, array $data): ?array
    {
        $token = env('ADMIN_BOT_TOKEN');
        $url = "https://api.telegram.org/bot{$token}/{$method}";

        try {
            $response = Http::timeout(10)->post($url, $data);
            return $response->json();
        } catch (\Exception $e) {
            Log::error("Telegram API Error: " . $e->getMessage());
            return null;
        }
    }

    protected function answerCallback($callbackId, $text = null)
    {
        $this->sendRequest('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => $text
        ]);
    }

    // ================================================================
    // ⚙️ بخش ۳: مدیریت فرایندها (Process Logic)
    // ================================================================


    protected function handleAddBotProcess()
    {
        $currentState = $this->getSession('state');
        $handler = match ($currentState) {

            // 🟢 مرحله ۱: شروع - بررسی شماره موبایل برای امنیت
            // 🟢 مرحله ۱: شروع - بررسی شماره موبایل برای امنیت و چک کردن محدودیت ساخت
            'addBot_start' => function () {
                    Log::info("[Bot Add Process] Step 1: Process started for ChatID: {$this->chatId}");

                    // =================================================================
                    // 🛑 اعمال محدودیت: هر کاربر فعلاً فقط مجاز به ساخت ۱ فروشگاه است
                    // =================================================================
                    $userIds = \App\Models\User::where('chat_id', $this->chatId)->pluck('id')->toArray();
                    $userBotsCount = \App\Models\ManagedBot::whereIn('owner_id', $userIds)->count();

                    if ($userBotsCount >= 1) {
                        // پاک کردن سشن برای جلوگیری از گیر کردن ربات در وضعیت ساخت
                        $this->clearSession();

                        $text = "⛔️ <b>محدودیت ایجاد فروشگاه</b>\n\n";
                        $text .= "کاربر گرامی، در نسخه فعلی پلتفرم روبوشاپ، هر حساب کاربری تنها مجاز به ساخت و مدیریت <b>یک فروشگاه</b> می‌باشد.\n\n";
                        $text .= "<i>(در آپدیت‌های آینده امکان افزودن فروشگاه‌های بیشتر به پنل شما اضافه خواهد شد)</i>";

                        $this->sendRequest('editMessageText', [
                        'chat_id' => $this->chatId,
                        'message_id' => $this->messageId,
                        'text' => $text,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'back_to_main']]
                            ]
                        ])
                        ]);

                        return; // توقف کامل فرآیند ساخت ربات
                    }

                    // =================================================================
                    // ادامه فرآیند در صورتی که کاربر فروشگاهی نداشته باشد
                    // =================================================================
                    if (!empty($this->user->phone)) {
                        Log::info("[Bot Add Process] User already has phone ({$this->user->phone}). Skipping OTP and showing method selection.");
                        $this->showBotCreationMethods($this->messageId, "✅ <b>هویت شما قبلاً تایید شده است.</b>\n\n");
                        return;
                    }

                    $this->updateSession(['state' => 'addBot_waiting_phone']);

                    $text = "🛡 <b>تایید هویت و حفظ امنیت فروشگاه</b>\n\n";
                    $text .= "برای اینکه مالکیت فروشگاه شما کاملاً ایمن باشد و در آینده بتوانید به پنل مدیریت پیشرفته سایت دسترسی داشته باشید، لطفاً ابتدا شماره موبایل خود را وارد کنید.\n\n";
                    $text .= "📱 <i>شماره خود را تایپ و ارسال کنید (مثال: 09123456789):</i>";

                    $keyboard = [
                    'inline_keyboard' => [
                        [['text' => __('bot.cancel_action'), 'callback_data' => 'cancel_process']]
                    ]
                    ];

                    $res = $this->sendRequest('editMessageText', [
                    'chat_id' => $this->chatId,
                    'message_id' => $this->messageId,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode($keyboard)
                    ]);

                    if ($res['ok'] ?? false) {
                        $this->updateSession(['message_id' => $res['result']['message_id']]);
                    }
                },

            // 🟡 مرحله ۲: دریافت شماره و ارسال پیامک تایید (OTP)
            'addBot_waiting_phone' => function () {
                    $this->deleteMessage($this->messageId); // حذف پیام ارسالی کاربر
                    $lastBotMessageId = $this->getSession('message_id');

                    $phone = $this->normalizePhoneNumber($this->text);
                    Log::info("[Bot Add Process] Step 2: Phone input received", ['chat_id' => $this->chatId, 'input' => $this->text, 'normalized' => $phone]);

                    $cancelKeyboard = [
                    'inline_keyboard' => [
                        [['text' => __('bot.cancel_action'), 'callback_data' => 'cancel_process']]
                    ]
                    ];

                    if (!$this->isValidPhoneNumber($phone)) {
                        Log::warning("[Bot Add Process] Invalid phone format rejected.", ['phone' => $phone]);
                        $this->sendRequest('editMessageText', [
                        'chat_id' => $this->chatId,
                        'message_id' => $lastBotMessageId,
                        'text' => "❌ <b>شماره موبایل نامعتبر است.</b>\nلطفاً یک شماره صحیح (مانند 09123456789) ارسال کنید.",
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode($cancelKeyboard)
                        ]);
                        return;
                    }

                    // 🛡️ سپر امنیتی پیامک (Rate Limiting)
                    $rateLimitKey = 'send_otp_' . $this->chatId;

                    if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
                        $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($rateLimitKey);
                        $minutes = ceil($seconds / 60);

                        Log::warning("[Bot Add Process] Rate limit exceeded for OTP.", ['chat_id' => $this->chatId]);

                        $this->sendRequest('editMessageText', [
                        'chat_id' => $this->chatId,
                        'message_id' => $lastBotMessageId,
                        'text' => "⏳ <b>درخواست بیش از حد!</b>\n\nشما بیش از حد مجاز درخواست پیامک داده‌اید. لطفاً <b>{$minutes} دقیقه دیگر</b> مجدداً تلاش کنید.",
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode($cancelKeyboard)
                        ]);
                        return;
                    }

                    // تولید کد و اتصال مستقیم به سرویس پیامک سایت
                    $code = rand(10000, 99999);
                    try {
                        Log::info("[Bot Add Process] Sending OTP SMS", ['phone' => $phone, 'code' => $code]);
                        app(\App\Services\SmsService::class)->sendOtp($phone, $code);

                        // ثبت یک تلاش در RateLimiter (قفل یک ساعته در صورت ۳ بار درخواست پشت سر هم)
                        \Illuminate\Support\Facades\RateLimiter::hit($rateLimitKey, 3600);

                    } catch (\Exception $e) {
                        Log::error('[Bot Add Process] SMS Send Error: ' . $e->getMessage());
                        $this->sendRequest('editMessageText', [
                        'chat_id' => $this->chatId,
                        'message_id' => $lastBotMessageId,
                        'text' => "❌ خطایی در ارسال سیستم پیامکی رخ داد. لطفاً دقایقی دیگر تلاش کنید.",
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode($cancelKeyboard)
                        ]);
                        return;
                    }

                    $this->updateSession([
                    'state' => 'addBot_waiting_otp',
                    'pending_phone' => $phone,
                    'otp_code' => $code,
                    'otp_expires_at' => now()->addMinutes(2)->timestamp
                    ]);

                    $this->sendRequest('editMessageText', [
                    'chat_id' => $this->chatId,
                    'message_id' => $lastBotMessageId,
                    'text' => "✉️ کد تایید ۵ رقمی به شماره <b>{$phone}</b> پیامک شد.\n\nلطفاً کد دریافت شده را ارسال کنید.",
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode($cancelKeyboard)
                    ]);
                },

            // 🟠 مرحله ۳: تایید کد OTP و ارائه لینک ایجاد ربات
            'addBot_waiting_otp' => function () {
                    $this->deleteMessage($this->messageId); // حذف پیام ارسالی کاربر
                    $lastBotMessageId = $this->getSession('message_id');
                    $cancelKeyboard = [
                    'inline_keyboard' => [
                        [['text' => __('bot.cancel_action'), 'callback_data' => 'cancel_process']]
                    ]
                    ];

                    // 👈 اصلاح ولیدیشن: جدا کردن اعداد از متن ارسالی (برای جلوگیری از خطای فرمت)
                    $enteredCode = preg_replace('/[^0-9]/', '', $this->normalizePhoneNumber($this->text));

                    $expectedCode = $this->getSession('otp_code');
                    $expiresAt = $this->getSession('otp_expires_at');
                    $pendingPhone = $this->getSession('pending_phone');

                    Log::info("[Bot Add Process] Step 3: Verifying OTP", [
                    'chat_id' => $this->chatId,
                    'entered_code' => $enteredCode,
                    'expected_code' => $expectedCode
                    ]);

                    if (!$expectedCode || now()->timestamp > $expiresAt) {
                        Log::warning("[Bot Add Process] OTP Expired or Session lost.");
                        $this->updateSession(['state' => 'addBot_waiting_phone']);
                        $this->sendRequest('editMessageText', [
                        'chat_id' => $this->chatId,
                        'message_id' => $lastBotMessageId,
                        'text' => "❌ کد تایید منقضی شده است. لطفاً شماره موبایل خود را مجدداً وارد کنید.",
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode($cancelKeyboard)
                        ]);
                        return;
                    }

                    if ($enteredCode != $expectedCode) {
                        Log::warning("[Bot Add Process] Incorrect OTP entered.");
                        $this->sendRequest('editMessageText', [
                        'chat_id' => $this->chatId,
                        'message_id' => $lastBotMessageId,
                        'text' => "❌ کد وارد شده اشتباه است. لطفاً دوباره تلاش کنید.",
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode($cancelKeyboard)
                        ]);
                        return;
                    }

                    Log::info("[Bot Add Process] OTP Verified Successfully. Checking for account merging...");

                    // 🌟 کد صحیح است! بررسی اینکه آیا این شماره قبلاً در سایت ثبت شده است؟
                    if ($this->user) {
                        $existingMasterUser = User::where('phone', $pendingPhone)
                        ->where(function ($q) {
                            // فقط حساب مادر سایت یا ادمین بات اصلی
                            $q->whereNull('bot_id')->orWhere('bot_id', $this->botDatabaseId);
                        })
                        ->where('id', '!=', $this->user->id)
                        ->first();

                        if ($existingMasterUser) {
                            Log::info("[Bot Add Process] Merging Telegram account with existing Web account.", [
                            'web_user_id' => $existingMasterUser->id,
                            'telegram_ghost_id' => $this->user->id
                            ]);

                            $ghostUser = $this->user;
                            $ghostUser->delete();

                            $existingMasterUser->update([
                            'chat_id' => $this->chatId,
                            'bot_id' => $this->botDatabaseId,
                            'username' => $ghostUser->username,
                            'first_name' => $ghostUser->first_name,
                            'last_name' => $ghostUser->last_name,
                            ]);

                            $this->user = $existingMasterUser;

                            Cache::forget('user_' . $this->botDatabaseId . '_' . $this->chatId);
                        } else {
                            Log::info("[Bot Add Process] Saving phone to current Telegram account.", ['user_id' => $this->user->id]);
                            $this->user->update(['phone' => $pendingPhone]);
                        }
                    }

                    // 👈 تغییر مهم: به جای تغییر وضعیت به waiting_creation مستقیماً منوی انتخاب روش را فراخوانی می‌کنیم
                    $this->updateSession([
                    'otp_code' => null,
                    'pending_phone' => null
                    ]);

                    $this->showBotCreationMethods($lastBotMessageId, "✅ <b>هویت شما با موفقیت تایید شد!</b>\n\n");
                },

            // 🔵 مرحله جدید: پردازش توکن دریافت شده از کاربر
            'addBot_waiting_token' => function () {
                    $this->processManualBotToken();
                },


            // 🔵 مرحله ۴: منتظر ماندن برای ارسال آپدیت Managed Bot از سمت تلگرام
            'addBot_waiting_creation' => function () {
                    $this->deleteMessage($this->messageId);

                    $lastBotMessageId = $this->getSession('message_id');
                    $keyboard = [
                    'inline_keyboard' => [
                        [['text' => __('bot.cancel_action'), 'callback_data' => 'cancel_process']]
                    ]
                    ];

                    if ($lastBotMessageId) {
                        $this->sendRequest('editMessageText', [
                        'chat_id' => $this->chatId,
                        'message_id' => $lastBotMessageId,
                        'text' => __('bot.creation.still_waiting'),
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode($keyboard)
                        ]);
                    }
                },

            default => function () {
                    $this->clearSession();
                    $this->showMainMenu();
                }
        };

        $handler();
    }
    /**
     * ساخت لینک ایجاد ربات جدید از طریق قابلیت Managed Bots تلگرام
     * https://t.me/newbot/{manager_bot_username}/{new_username}?name={new_name}
     */
    protected function generateBotCreationLink(): string
    {
        $managerBotUsername = config('telegram.admin.bot_username');

        // یوزرنیم پیشنهادی: منحصربه‌فرد بر اساس chat_id کاربر، قابل ویرایش توسط خودش در تلگرام
        $suggestedUsername = 'shop' . $this->chatId . 'bot';

        // نام پیشنهادی برای نمایش (قابل ویرایش)
        $suggestedName = __('bot.creation.suggested_bot_name');
        $encodedName = str_replace('%20', '+', rawurlencode($suggestedName));

        return "https://t.me/newbot/{$managerBotUsername}/{$suggestedUsername}?name={$encodedName}";
    }

    /**
     * وقتی کاربر روی «رد کردن شماره تلفن» می‌زند
     */
    protected function handleSkipPhone($messageId)
    {
        $this->clearSession();

        $this->sendRequest('editMessageText', [
            'chat_id' => $this->chatId,
            'message_id' => $messageId,
            'text' => __('bot.creation.phone_skipped'),
            'reply_markup' => json_encode(['inline_keyboard' => []])
        ]);

        $this->showMainMenu();
    }

    // ================================================================
    // 🖥️ بخش ۴: رابط کاربری (UI)
    // ================================================================

    protected function showMainMenu($editMessageId = null)
    {
        $name = $this->user->first_name ?? 'کاربر';
        $text = __('bot.welcome_message', ['name' => $name]);
        $keyboard = [
            'inline_keyboard' => [
                [['text' => __('bot.menu.add_bot'), 'callback_data' => 'menu_add_bot'], ['text' => __('bot.menu.my_bots'), 'callback_data' => 'menu_my_bots']],
                [['text' => __('bot.menu.support'), 'callback_data' => 'menu_support'], ['text' => __('bot.menu.guide'), 'callback_data' => 'menu_guide']],
                [['text' => __('bot.menu.settings'), 'callback_data' => 'menu_settings']],
                [['text' => __('bot.menu.change_lang'), 'callback_data' => 'menu_change_lang']]
            ]
        ];

        $payload = ['chat_id' => $this->chatId, 'text' => $text, 'parse_mode' => 'Markdown', 'reply_markup' => json_encode($keyboard)];

        if ($editMessageId) {
            $payload['message_id'] = $editMessageId;
            $this->sendRequest('editMessageText', $payload);
        } else {
            $this->sendRequest('sendMessage', $payload);
        }
    }

    protected function showMyBots($messageId)
    {
        // استخراج تمام ID های متعلق به این شخص (در تمام ربات‌ها)
        $userIds = User::where('chat_id', $this->chatId)->pluck('id')->toArray();

        // پیدا کردن فروشگاه‌هایی که مالک آن‌ها یکی از ID های این کاربر است
        $bots = ManagedBot::whereIn('owner_id', $userIds)->get();

        $keyboard = [];
        if ($bots->isEmpty()) {
            $text = "📂 شما هنوز هیچ فروشگاهی ثبت نکرده‌اید.";
        } else {
            $text = "🤖 لیست فروشگاه‌های شما:";
            foreach ($bots as $bot) {
                $keyboard[] = [['text' => $bot->bot_name, 'callback_data' => 'select_bot_' . $bot->id]];
            }
        }

        $keyboard[] = [['text' => __('bot.menu.back'), 'callback_data' => 'back_to_main']];

        $this->sendRequest('editMessageText', [
            'chat_id' => $this->chatId,
            'message_id' => $messageId,
            'text' => $text,
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    protected function showLanguagePicker($messageId)
    {
        $keyboard = ['inline_keyboard' => [[['text' => '🇮🇷 فارسی', 'callback_data' => 'set_lang_fa'], ['text' => '🇺🇸 English', 'callback_data' => 'set_lang_en']], [['text' => __('bot.menu.back'), 'callback_data' => 'back_to_main']]]];
        $this->sendRequest('editMessageText', ['chat_id' => $this->chatId, 'message_id' => $messageId, 'text' => __('bot.select_language'), 'reply_markup' => json_encode($keyboard)]);
    }

    protected function changeUserLanguage($lang, $messageId)
    {
        if (in_array($lang, ['fa', 'en'])) {
            $this->user->update(['language' => $lang]);
            $cacheKey = 'user_' . $this->botDatabaseId . '_' . $this->chatId;
            Cache::put($cacheKey, $this->user, 3600);
            App::setLocale($lang);
            $this->answerCallback($this->messageData['id'] ?? null, __('bot.language_changed'));
            $this->showMainMenu($messageId);
        }
    }

    protected function handleUnknownCommand($messageId = null)
    {
        $text = __('bot.unknown_command');

        if ($messageId) {
            $this->sendRequest('editMessageText', [
                'chat_id' => $this->chatId,
                'message_id' => $messageId,
                'text' => $text,
            ]);
        } else {
            $this->sendRequest('sendMessage', [
                'chat_id' => $this->chatId,
                'text' => $text,
            ]);
        }

        $this->showMainMenu();
    }


    // ================================================================
    // 🛠️ بخش ۵: ابزارهای کمکی (Utils)
    // ================================================================

    private function normalizePhoneNumber($phone)
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $phone = str_replace($persian, $english, $phone);
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // تبدیل +98 یا 98 یا 0098 به صفر
        if (str_starts_with($phone, '98') && strlen($phone) == 12) {
            $phone = '0' . substr($phone, 2);
        } elseif (str_starts_with($phone, '0098') && strlen($phone) == 14) {
            $phone = '0' . substr($phone, 4);
        }

        return $phone;
    }
    private function isValidPhoneNumber($phone)
    {
        return preg_match('/^09[0-9]{9}$/', $phone);
    }

    protected function deleteMessage($ids, $chatId = null)
    {
        $chatId = $chatId ?? $this->chatId;

        if (is_array($ids)) {

            $chunks = array_chunk($ids, 100);
            $lastResult = null;

            foreach ($chunks as $chunk) {
                $lastResult = $this->sendRequest('deleteMessages', [
                    'chat_id' => $chatId,
                    'message_ids' => $chunk
                ]);
            }
            return $lastResult;
        }

        return $this->sendRequest('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $ids
        ]);
    }

    protected function getManagedBotToken(int $botId): ?string
    {
        $token = config('telegram.admin.bot_token'); // توکن ادمین‌بات
        $url = "https://api.telegram.org/bot{$token}/getManagedBotToken";

        Log::info("getManagedBotToken: sending request", [
            'user_id' => $botId,
            'admin_token_set' => !empty($token),
            'admin_token_preview' => $token ? substr($token, 0, 10) . '...' : null,
        ]);

        try {
            $response = Http::post($url, ['user_id' => $botId]);

            Log::info("getManagedBotToken: raw response", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->successful() && ($response['ok'] ?? false)) {
                return $response['result'] ?? null;
            }

            Log::error("getManagedBotToken failed", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        } catch (\Throwable $e) {
            Log::error("getManagedBotToken threw an exception: " . $e->getMessage(), [
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return null;
        }
    }

    /**
     * تکمیل ثبت رباتی که از طریق Telegram Managed Bots ساخته شده است.
     * این متد در پاسخ به آپدیت managed_bot فراخوانی می‌شود (نه در سشن کاربر جاری).
     *
     * توجه: برخلاف فرم پنل وب (registerBot)، اینجا نیازی به OwnerVerification / لینک verify_
     * نیست، چون خود تلگرام از طریق آپدیت managed_bot مستقیماً هویت سازنده (user.id) را
     * تضمین می‌کند؛ پس بلافاصله می‌توانیم User ادمین را برای ربات جدید بسازیم.
     */
    protected function finalizeBotCreationFromManaged(string $token, array $botInfo, User $user)
    {
        Log::info("[Bot Creation] Step 4: Starting database finalization for bot {$botInfo['id']}.");

        if (ManagedBot::where('bot_id_string', $botInfo['id'])->exists()) {
            Log::warning("[Bot Creation] Aborted: Bot {$botInfo['id']} already exists in database.");
            $this->sendRequest('sendMessage', [
                'chat_id' => $user->chat_id,
                'text' => __('bot.creation.bot_exists'),
            ]);
            return;
        }

        try {
            DB::beginTransaction();

            $webhookSecret = Str::random(32);

            Log::info("[Bot Creation] Step 5: Inserting ManagedBot record.");
            $newBot = ManagedBot::create([
                'owner_id' => $user->id,
                'owner_phone' => $user->phone,
                'bot_id_string' => $botInfo['id'],
                'bot_token' => $token,
                'webhook_secret' => $webhookSecret,
                'bot_name' => $botInfo['first_name'] ?? ($botInfo['username'] ?? 'بدون نام'),
                'bot_username' => $botInfo['username'] ?? null,
                'status' => 'pending',
                'subscription_plan' => 'Business',
                'subscription_expires_at' => now()->addMonth(),
            ]);

            Log::info("[Bot Creation] Step 6: Injecting default settings for bot ID: {$newBot->id}.");
            $defaultSettings = [
                [
                    'key' => 'store_name',
                    'description' => 'نام فروشگاه',
                    'value' => $newBot->bot_name,
                    'type' => 'string',
                    'options' => null
                ],
                [
                    'key' => 'is_active',
                    'description' => 'وضعیت فعالیت فروشگاه',
                    'value' => '1',
                    'type' => 'boolean',
                    'options' => null
                ],
                [
                    'key' => 'welcome_message',
                    'description' => 'پیام خوش‌آمدگویی ربات',
                    'value' => "🎉 <b>به فروشگاه شما خوش آمدیم!</b>"
                        . "سلام {name} عزیز 👋"
                        . "از اینکه پلتفرم ما را برای راه‌اندازی فروشگاه خود انتخاب کردید، صمیمانه از شما سپاسگزاریم. ❤️"
                        . "فروشگاه <b>{bot_name}</b> با موفقیت راه‌اندازی شد و از این لحظه می‌توانید مدیریت فروشگاه خود را شروع کنید. 🚀"
                        . "⚙️ <b>اولین قدم شما:</b>"
                        . "ابتدا وارد بخش <b>تنظیمات فروشگاه</b> شوید و اطلاعات و تنظیمات فروشگاه خود را تکمیل کنید؛ از جمله نام فروشگاه، پیام خوش‌آمدگویی، اطلاعات پشتیبانی و سایر تنظیمات."
                        . "پس از تکمیل تنظیمات، می‌توانید محصولات خود را اضافه کنید و فروشگاهتان را برای مشتریان آماده کنید. 🛍️"
                        . "✨ <b>امیدواریم تجربه‌ای ساده و موفق در فروش آنلاین داشته باشید.</b>",

                    'type' => 'text',
                    'options' => null
                ],
                [
                    'key' => 'support_phone',
                    'description' => 'شماره تماس پشتیبانی',
                    'value' => '',
                    'type' => 'string',
                    'options' => null
                ],
                [
                    'key' => 'shipping_cost',
                    'description' => 'هزینه ارسال پیش‌فرض (تومان)',
                    'value' => '0',
                    'type' => 'number',
                    'options' => null
                ],
                [
                    'key' => 'channel_id',
                    'description' => 'آیدی کانال تلگرام',
                    'value' => '',
                    'type' => 'string',
                    'options' => null
                ],
                [
                    'key' => 'show_discounted_products',
                    'description' => 'نمایش دکمه محصولات تخفیف‌دار در منو',
                    'value' => '1',
                    'type' => 'boolean',
                    'options' => null
                ],
                [
                    'key' => 'support_text',
                    'description' => 'متن بخش پشتیبانی',
                    'value' => "🎧 برای پیگیری سفارشات یا طرح سوالات خود با ما در ارتباط باشید:",
                    'type' => 'text',
                    'options' => null
                ],
                [
                    'key' => 'support_telegram_id',
                    'description' => 'آیدی تلگرام پشتیبانی (بدون @)',
                    'value' => '',
                    'type' => 'string',
                    'options' => null
                ],
                [
                    'key' => 'force_join',
                    'description' => 'عضویت اجباری در کانال',
                    'value' => '0',
                    'type' => 'boolean',
                    'options' => null,
                ],
                [
                    'key' => 'bank_card_number',
                    'description' => 'شماره کارت بانکی (کارت به کارت)',
                    'value' => '',
                    'type' => 'string',
                    'options' => null,
                ],
                [
                    'key' => 'bank_card_name',
                    'description' => 'نام صاحب حساب',
                    'value' => '',
                    'type' => 'string',
                    'options' => null,
                ]
            ];

            foreach ($defaultSettings as $setting) {
                Setting::create(array_merge($setting, ['bot_id' => $newBot->id]));
            }

            Log::info("[Bot Creation] Step 7: Assigning admins.");
            // 1. اضافه کردن خود سازنده ربات به عنوان ادمین
            User::firstOrCreate(
                ['bot_id' => $newBot->id, 'chat_id' => $user->chat_id],
                [
                    'is_admin' => 1,
                    'phone' => $user->phone,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'username' => $user->username
                ]
            );

            // 2. اضافه کردن سوپر ادمین سایت
            $superAdminId = env('SUPER_ADMIN_ID');
            if ($superAdminId && (string) $user->chat_id !== (string) $superAdminId) {
                User::firstOrCreate(
                    ['bot_id' => $newBot->id, 'chat_id' => $superAdminId],
                    ['is_admin' => 1]
                );
            }

            DB::commit();
            Log::info("[Bot Creation] Step 8: Database transaction committed successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("[Bot Creation] FAILED: Database error: " . $e->getMessage());

            $this->sendRequest('sendMessage', [
                'chat_id' => $user->chat_id,
                'text' => __('bot.creation.database_error'),
            ]);
            return;
        }

        Log::info("[Bot Creation] Step 9: Setting Webhook...");
        $webhookUrl = url("/api/bot/shopbot/{$newBot->webhook_secret}");
        $res = Http::post("https://api.telegram.org/bot{$token}/setWebhook", [
            'url' => $webhookUrl,
            'secret_token' => $newBot->webhook_secret,
            'allowed_updates' => ['message', 'callback_query'],
        ]);

        if ($res['ok'] ?? false) {
            $newBot->update(['status' => 'active']);
            Log::info("[Bot Creation] Step 10: Webhook set successfully. Bot status updated to active.");

            Log::info("[Bot Creation] Step 11: Registering Bot Commands...");
            $botCommands = [
                ['command' => 'start', 'description' => 'منوی اصلی فروشگاه'],
                ['command' => 'search', 'description' => 'جستجوی محصولات'],
                ['command' => 'cart', 'description' => 'سبد خرید'],
                ['command' => 'support', 'description' => 'تماس با پشتیبانی'],
            ];

            $cmdRes = Http::post("https://api.telegram.org/bot{$token}/setMyCommands", [
                'commands' => $botCommands
            ]);

            if ($cmdRes['ok'] ?? false) {
                Log::info("[Bot Creation] Commands registered successfully.");
            } else {
                Log::warning("[Bot Creation] Commands registration failed.", ['response' => $cmdRes->json()]);
            }

            $msg = __('bot.creation.success_managed_message', [
                'name' => htmlspecialchars($newBot->bot_name),
                'username' => $newBot->bot_username,
            ]);
        } else {
            Log::error("[Bot Creation] FAILED: Webhook setting failed.", ['response' => $res->json()]);
            $msg = __('bot.creation.webhook_failed', ['error' => $res['description'] ?? 'Unknown']);
        }

        Log::info("[Bot Creation] Step 12: Sending final success message to creator.");
        // $this->sendRequest('sendMessage', [
        //     'chat_id' => $user->chat_id,
        //     'text' => $msg,
        //     'parse_mode' => 'HTML',
        // ]);

        $this->sendRequest('sendMessage', [
            'chat_id' => $user->chat_id,
            'text' => "🎉 <b>تبریک! شعبه مجازی شما آماده است.</b>\n\nبرای مدیریت محصولات وارد پنل شوید و برای تست ربات، روی دکمه ورود به فروشگاه کلیک کنید.",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => '🛍 ورود به فروشگاه من', 'url' => "https://t.me/" . $newBot->bot_username]],
                    [['text' => '💻 ورود به داشبورد مدیریت', 'url' => url('/panel/login')]]
                ]
            ])
        ]);

        // ... (کدهای قبلی: نمایش منوی اصلی و پاک کردن سشن)
        $this->clearSession();
        $this->showMainMenu();

        // 👈 بخش جدید: ارسال پیام اطلاع‌رسانی به سوپر ادمین
        $superAdminId = env('SUPER_ADMIN_ID');
        if ($superAdminId) {
            Log::info("[Bot Creation] Step 13: Notifying Super Admin.");

            $creatorName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            $creatorUsername = $user->username ? '@' . $user->username : 'ندارد';
            $creatorPhone = $user->phone ?? 'ندارد';
            $botUsernameStr = $newBot->bot_username ? '@' . $newBot->bot_username : 'ندارد';

            $notifyText = "🚨 <b>ثبت فروشگاه جدید در پلتفرم!</b> 🚨\n\n";

            $notifyText .= "👤 <b>اطلاعات سازنده:</b>\n";
            $notifyText .= "▫️ <b>نام:</b> {$creatorName}\n";
            $notifyText .= "▫️ <b>یوزرنیم:</b> {$creatorUsername}\n";
            $notifyText .= "▫️ <b>شناسه (Chat ID):</b> <code>{$user->chat_id}</code>\n";
            $notifyText .= "▫️ <b>شماره موبایل:</b> <code>{$creatorPhone}</code>\n\n";

            $notifyText .= "🤖 <b>اطلاعات فروشگاه:</b>\n";
            $notifyText .= "▫️ <b>نام فروشگاه:</b> {$newBot->bot_name}\n";
            $notifyText .= "▫️ <b>یوزرنیم ربات:</b> {$botUsernameStr}\n";
            $notifyText .= "▫️ <b>شناسه ربات در تلگرام:</b> <code>{$newBot->bot_id_string}</code>\n";
            $notifyText .= "▫️ <b>آیدی در دیتابیس:</b> {$newBot->id}\n";

            $this->sendRequest('sendMessage', [
                'chat_id' => $superAdminId,
                'text' => $notifyText,
                'parse_mode' => 'HTML',
            ]);
        }

        Log::info("[Bot Creation] FULLY COMPLETED for bot ID: {$newBot->id}.");
    }

    /**
     * پردازش توکنِ ورود از سایت (Deep Linking) و ادغام حساب‌ها
     */
    protected function handleDeepLinkToken(string $token)
    {
        // پیدا کردن کاربر سایت که این توکن را دارد
        $webUser = User::where('entry_token', $token)->first();

        if ($webUser) {
            // کاربری که در Controller ربات به صورت خودکار (بدون شماره) ساخته شده را می‌گیریم
            $ghostUser = $this->user;

            // اگر کاربر وب با کاربر فیک متفاوت است، کاربر فیک را حذف می‌کنیم تا خطای تداخل (Unique) ندهد
            if ($ghostUser && $ghostUser->id !== $webUser->id) {
                $ghostUser->delete();
            }

            // اطلاعات تلگرامی را روی کاربرِ قدرتمندِ سایت ثبت می‌کنیم
            $webUser->update([
                'chat_id' => $this->chatId,
                'bot_id' => $this->botDatabaseId,
                'username' => $this->messageData['from']['username'] ?? null,
                'first_name' => $this->messageData['from']['first_name'] ?? null,
                'last_name' => $this->messageData['from']['last_name'] ?? null,
                'entry_token' => null // باطل کردن توکن برای امنیت
            ]);

            // شیء کاربر جاری ربات را با کاربر کامل شده جایگزین می‌کنیم
            $this->user = $webUser;

            // پاک کردن کش کاربر تا در درخواست‌های بعدی اطلاعات کامل خوانده شود
            Cache::forget('user_' . $this->botDatabaseId . '_' . $this->chatId);

            // ارسال پیام موفقیت به کاربر
            $this->sendRequest('sendMessage', [
                'chat_id' => $this->chatId,
                'text' => "✅ حساب کاربری سایت شما با موفقیت به تلگرام متصل شد. حالا می‌توانید فروشگاه خود را بسازید!",
            ]);
        }
    }

    // ================================================================
    // 💡 متدهای جدید برای انتخاب روش و اتصال با توکن
    // ================================================================

    protected function showBotCreationMethods($messageId = null, $prefixText = "")
    {
        $this->updateSession(['state' => 'addBot_choose_method']);

        $text = $prefixText . "🛍 <b>راه‌اندازی فروشگاه جدید</b>\n\n";
        $text .= "🤖 <b>سریع‌ترین و ساده‌ترین روش:</b>\n";
        $text .= "فروشگاه خود را در چند مرحله کوتاه و بدون نیاز به کار با BotFather راه‌اندازی کنید.\n\n";
        $text .= "✨ <b>ساخت خودکار ربات</b>\n";
        $text .= "• راه‌اندازی سریع و آسان\n";
        $text .= "• بدون نیاز به دریافت و کپی توکن\n";
        $text .= "• تنظیمات ربات به‌صورت خودکار انجام می‌شود\n";
        $text .= "• مناسب برای شروع سریع فروشگاه\n\n";
        $text .= "🔑 اگر قبلاً ربات ساخته‌اید و توکن آن را در اختیار دارید، می‌توانید آن را نیز به سیستم متصل کنید.\n\n";
        $text .= "👇 برای شروع، روش موردنظر را انتخاب کنید:";

        $keyboard = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '🚀 ساخت سریع و خودکار (پیشنهادی)',
                        'callback_data' => 'addBot_method_managed'
                    ]
                ],
                [
                    [
                        'text' => '🔑 اتصال ربات موجود با توکن',
                        'callback_data' => 'addBot_method_token'
                    ]
                ],
                [
                    [
                        'text' => __('bot.cancel_action'),
                        'callback_data' => 'cancel_process'
                    ]
                ]
            ]
        ];


        $payload = [
            'chat_id' => $this->chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($keyboard)
        ];

        $targetMessageId = $messageId ?? $this->messageId;

        if ($targetMessageId) {
            $payload['message_id'] = $targetMessageId;
            $res = $this->sendRequest('editMessageText', $payload);
        } else {
            $res = $this->sendRequest('sendMessage', $payload);
        }

        if ($res['ok'] ?? false) {
            $this->updateSession(['message_id' => $res['result']['message_id']]);
        }
    }

    protected function promptForBotToken($messageId)
    {
        $text = "🤖 <b>اتصال ربات جدید</b>\n\n";
        $text .= "لطفاً توکن (Token) رباتی که از @BotFather دریافت کرده‌اید را به اینجا ارسال کنید:\n\n";
        $text .= "<i>نمونه توکن:</i>\n<code>123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ</code>\n\n";
        $text .= "<i>(برای بازگشت به مرحله قبل روی دکمه زیر کلیک کنید)</i>";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => __('bot.menu.back'), 'callback_data' => 'menu_add_bot']]
            ]
        ];

        $this->sendRequest('editMessageText', [
            'chat_id' => $this->chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($keyboard)
        ]);
    }

    protected function showManagedBotLink($messageId)
    {
        $link = $this->generateBotCreationLink();
        $keyboard = [
            'inline_keyboard' => [
                [['text' => __('bot.creation.create_button'), 'url' => $link]],
                [['text' => __('bot.menu.back'), 'callback_data' => 'menu_add_bot']]
            ]
        ];

        $this->sendRequest('editMessageText', [
            'chat_id' => $this->chatId,
            'message_id' => $messageId,
            'text' => "✅ <b>ساخت اتوماتیک انتخاب شد.</b>\n\n" . __('bot.creation.managed_start_prompt'),
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($keyboard)
        ]);
    }

    protected function processManualBotToken()
    {
        $token = trim($this->text);

        // حذف پیام توکن ارسالی از سمت کاربر برای حفظ امنیت چت
        if ($this->messageId) {
            $this->deleteMessage($this->messageId);
        }

        $waitRes = $this->sendRequest('sendMessage', [
            'chat_id' => $this->chatId,
            'text' => "⏳ <b>در حال بررسی توکن و ارتباط با سرورهای تلگرام...</b>",
            'parse_mode' => 'HTML'
        ]);
        $waitMessageId = $waitRes['result']['message_id'] ?? null;

        try {
            // دریافت اطلاعات ربات از تلگرام (دقیقاً مشابه ساختار آپدیت managed_bot)
            $response = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getMe");

            if (!$response->successful() || !$response->json('ok')) {
                if ($waitMessageId)
                    $this->deleteMessage($waitMessageId);
                $this->sendRequest('sendMessage', [
                    'chat_id' => $this->chatId,
                    'text' => "❌ <b>توکن نامعتبر است!</b>\n\nلطفاً توکنی که از @BotFather دریافت کرده‌اید را به درستی و کامل ارسال کنید.",
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode(['inline_keyboard' => [[['text' => __('bot.cancel_action'), 'callback_data' => 'cancel_process']]]])
                ]);
                return;
            }

            $botData = $response->json('result');

            if ($waitMessageId) {
                $this->deleteMessage($waitMessageId);
            }

            // 🚀 استفاده هوشمندانه و مجدد از متد قدرتمندی که قبلاً نوشتید!
            // چون ساختار botData دقیقاً مشابه خروجی getMe است، به درستی در متد شما کار خواهد کرد
            $this->finalizeBotCreationFromManaged($token, $botData, $this->user);

        } catch (\Exception $e) {
            if ($waitMessageId)
                $this->deleteMessage($waitMessageId);
            Log::error("Manual Bot Connection Error: " . $e->getMessage());
            $this->sendRequest('sendMessage', [
                'chat_id' => $this->chatId,
                'text' => "❌ <b>خطای ارتباط با سرور:</b>\n\nلطفاً دقایقی دیگر تلاش کنید.",
                'parse_mode' => 'HTML'
            ]);
        }
    }

    // ================================================================
    // 🎛️ بخش ۶: مدیریت منوهای جانبی و اختصاصی
    // ================================================================

    /**
     * نمایش اطلاعات و لینک‌های مدیریتی یک فروشگاه خاص
     */
    /**
     * نمایش اطلاعات و لینک‌های مدیریتی یک فروشگاه خاص
     */
    protected function manageSpecificBot($botId, $messageId)
    {
        // استخراج تمام ID های متعلق به این شخص (در تمام ربات‌ها) برای حل مشکل مالکیت
        $userIds = \App\Models\User::where('chat_id', $this->chatId)->pluck('id')->toArray();

        // پیدا کردن ربات بر اساس ID های معتبر این شخص
        $bot = \App\Models\ManagedBot::where('id', $botId)->whereIn('owner_id', $userIds)->first();

        if (!$bot) {
            $this->answerCallback($this->messageData['id'] ?? null, "❌ شعبه مورد نظر پیدا نشد یا دسترسی ندارید!");
            return;
        }

        // محاسبه روزهای باقی‌مانده از اشتراک با استفاده از Carbon
        $daysLeft = 0;
        if ($bot->subscription_expires_at) {
            $daysLeft = (int) round(now()->diffInDays(\Carbon\Carbon::parse($bot->subscription_expires_at), false));
        }

        // ترجمه نام پلن‌ها برای نمایش زیباتر
        $planName = match (strtolower($bot->subscription_plan)) {
            'starter' => 'استارتر (پایه)',
            'pro' => 'حرفه‌ای',
            'business' => 'کسب‌وکار',
            'vip' => 'ویژه (VIP)',
            'unlimited' => 'نامحدود',
            default => $bot->subscription_plan
        };

        $statusText = $bot->status === 'active' ? "✅ فعال" : "⏳ غیرفعال/منقضی";

        // ساخت متن پیام
        $text = "🛍 <b>شعبه مجازی:</b> {$bot->bot_name}\n";
        $text .= "آیدی: @{$bot->bot_username}\n\n";

        $text .= "📊 <b>آمار کلی فروشگاه:</b>\n";
        $text .= "👥 مشتریان جذب شده: <b>" . number_format($bot->total_users ?? 0) . "</b> نفر\n";
        $text .= "📦 تنوع محصولات: <b>" . number_format($bot->total_products ?? 0) . "</b> کالا\n";
        $text .= "🛒 کل سفارشات: <b>" . number_format($bot->total_orders ?? 0) . "</b> سفارش\n\n";

        $text .= "💳 <b>وضعیت اشتراک:</b>\n";
        $text .= "▫️ پلن فعلی: <b>{$planName}</b>\n";
        $text .= "▫️ وضعیت: <b>{$statusText}</b>\n";

        if ($daysLeft > 0) {
            $text .= "▫️ اعتبار: <b>{$daysLeft} روز دیگر</b>\n\n";
        } else {
            $text .= "▫️ اعتبار: <b>⚠️ پایان یافته</b>\n\n";
        }

        $text .= "💡 <i>برای مدیریت محصولات، تنظیم درگاه پرداخت و مشاهده گزارشات دقیق مالی، وارد داشبورد وب شوید.</i>";

        // دکمه‌های شیشه‌ای
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '💻 ورود به داشبورد مدیریت', 'url' => url('/panel/login')]],
                [['text' => '📱 ورود به فروشگاه من', 'url' => "https://t.me/{$bot->bot_username}"]],
                [['text' => __('bot.menu.back'), 'callback_data' => 'menu_my_bots']]
            ]
        ];

        $this->sendRequest('editMessageText', [
            'chat_id' => $this->chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($keyboard)
        ]);
    }

    /**
     * نمایش منوی پشتیبانی
     */
    protected function showSupportMenu($messageId)
    {
        $text = "🎧 <b>پشتیبانی پلتفرم</b>\n\n";
        $text .= "در صورت داشتن هرگونه سوال در مراحل راه‌اندازی، پیشنهاد یا گزارش مشکل، کارشناسان ما آماده پاسخگویی به شما هستند.";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '💬 ارتباط با پشتیبانی', 'url' => 'https://t.me/Amir_soleimani_79']], // 👈 آیدی خودتان را اینجا بگذارید
                [['text' => __('bot.menu.back'), 'callback_data' => 'back_to_main']]
            ]
        ];

        $this->sendRequest('editMessageText', [
            'chat_id' => $this->chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($keyboard)
        ]);
    }

    /**
     * نمایش منوی آموزش و راهنما
     */
    protected function showGuideMenu($messageId)
    {
        $text = "📚 <b>آکادمی و راهنمای سیستم</b>\n\n";
        $text .= "برای یادگیری سریع کار با پنل مدیریت، نحوه اتصال به زرین‌پال و ترفندهای افزایش فروش، از ویدیوهای آموزشی کانال ما استفاده کنید.";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '📺 مشاهده آموزش‌ها در کانال', 'url' => 'https://t.me/robo_shop_channel']], // 👈 آیدی کانال خود را بگذارید
                [['text' => __('bot.menu.back'), 'callback_data' => 'back_to_main']]
            ]
        ];

        $this->sendRequest('editMessageText', [
            'chat_id' => $this->chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($keyboard)
        ]);
    }

    /**
     * نمایش وضعیت تنظیمات کاربر در ربات مادر
     */
    protected function showSettingsMenu($messageId)
    {
        $text = "⚙️ <b>اطلاعات حساب کاربری</b>\n\n";
        $text .= "👤 نام: <b>{$this->user->first_name}</b>\n";
        $text .= "📱 موبایل تایید شده: <code>" . ($this->user->phone ?? 'ثبت نشده') . "</code>\n\n";
        $text .= "⚠️ <i>دسترسی به تمامی تنظیمات فروشگاه‌ها تنها از طریق داشبورد مدیریت وب امکان‌پذیر است.</i>";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '💻 ورود به داشبورد وب', 'url' => url('/panel/login')]],
                [['text' => __('bot.menu.back'), 'callback_data' => 'back_to_main']]
            ]
        ];

        $this->sendRequest('editMessageText', [
            'chat_id' => $this->chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($keyboard)
        ]);
    }
}
