<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ManagedBot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CheckSubscriptions extends Command
{
    // نام دستوری که در ترمینال یا سرور اجرا می‌شود
    protected $signature = 'shop:check-subscriptions';

    protected $description = 'بررسی انقضای اشتراک ربات‌ها و ارسال اخطار یا مسدودسازی';

    public function handle()
    {
        Log::channel('cron')->info('========== CheckSubscriptions START ==========');

        $today = Carbon::today();
        $threeDaysFromNow = Carbon::today()->addDays(3);
        $yesterday = Carbon::today()->subDays(1); // 24 ساعت گذشته

        // =================================================================
        // ۱. بررسی ۳ روز مانده به انقضا
        // =================================================================
        $warningBots = ManagedBot::whereIn('status', ['active', 'pending'])
            ->whereDate('subscription_expires_at', $threeDaysFromNow)
            ->get();

        foreach ($warningBots as $bot) {
            $text = "⚠️ <b>هشدار انقضای اشتراک</b>\n\n"
                  . "مدیر گرامی، اشتراک ربات شما (<b>{$bot->bot_name}</b>) تا ۳ روز دیگر به پایان می‌رسد.\n"
                  . "برای جلوگیری از قطعی سرویس و توقف فروش، لطفاً نسبت به تمدید آن اقدام نمایید.";

            $this->notifyAdmins($bot, $text);
            Log::channel('cron')->info("Warning sent for bot: {$bot->bot_username} (3 days left)");
        }

        // =================================================================
        // ۲. بررسی روز انقضا (امروز)
        // =================================================================
        $expiringTodayBots = ManagedBot::whereIn('status', ['active', 'pending'])
            ->whereDate('subscription_expires_at', $today)
            ->get();

        foreach ($expiringTodayBots as $bot) {
            $text = "🔴 <b>پایان مهلت اشتراک</b>\n\n"
                  . "مدیر گرامی، اشتراک ربات فروشگاهی شما امروز به پایان رسیده است.\n"
                  . "سرویس‌دهی ربات شما <b>تا ۲۴ ساعت آینده متوقف خواهد شد</b>. لطفاً در اسرع وقت اقدام به تمدید کنید.";

            $this->notifyAdmins($bot, $text);
            Log::channel('cron')->info("Expiration notice sent for bot: {$bot->bot_username} (0 days left)");
        }

        // =================================================================
        // ۳. گذشته از انقضا (۲۴ ساعت بعد) -> مسدودسازی کامل
        // =================================================================
        // از <= استفاده می‌کنیم تا اگر رباتی چند روز هم گذشته بود، جا نماند و قطعا بسته شود
        $expiredBots = ManagedBot::whereIn('status', ['active', 'pending'])
            ->where('bot_id_string', '!=', 'admin_bot') // 🛡 ربات ادمین مرکزی هرگز نباید مسدود شود
            ->whereDate('subscription_expires_at', '<=', $yesterday)
            ->get();

        foreach ($expiredBots as $bot) {
            // الف) آپدیت وضعیت در دیتابیس به مسدود
            $bot->update(['status' => 'blocked']);

            // ب) انفجار کش ربات برای اعمال در لحظه در تلگرام
            if ($bot->webhook_secret) {
                Cache::forget("bot_config:{$bot->webhook_secret}");
            }

            // ج) ارسال پیام خداحافظی موقت به ادمین‌ها
            $text = "🚫 <b>ربات موقتاً غیرفعال شد</b>\n\n"
                  . "مهلت ۲۴ ساعته ربات شما به پایان رسید و فروشگاه موقتاً غیرفعال شد.\n"
                  . "مشتریان دیگر قادر به مشاهده محصولات و ثبت سفارش نخواهند بود.\n"
                  . "برای فعال‌سازی مجدد و بازگشت ربات به حالت عادی، اشتراک خود را تمدید کنید.";

            $this->notifyAdmins($bot, $text);
            Log::channel('cron')->info("Bot BLOCKED due to expired subscription: {$bot->bot_username}");
        }

        Log::channel('cron')->info('========== CheckSubscriptions FINISHED ==========');
        $this->info("✅ بررسی انقضای اشتراک‌ها با موفقیت انجام شد.");
    }

    /**
     * متد کمکی برای ارسال پیام به تمام ادمین‌های یک ربات
     */
    private function notifyAdmins(ManagedBot $bot, string $text)
    {
        // پیدا کردن تمام ادمین‌های ثبت‌شده در این ربات خاص
        $admins = User::where('bot_id', $bot->id)
            ->whereIn('is_admin', [1, 2])
            ->whereNotNull('chat_id')
            ->get();

        // 🚀 دکمه‌ای که مستقیماً آن‌ها را به بخش تمدید اشتراک در ربات هدایت می‌کند
        $keyboard = json_encode([
            'inline_keyboard' => [
                [['text' => '💳 تمدید سریع اشتراک', 'callback_data' => 'admin_subscription']]
            ]
        ]);

        foreach ($admins as $admin) {
            try {
                Http::timeout(10)->post("https://api.telegram.org/bot{$bot->bot_token}/sendMessage", [
                    'chat_id' => $admin->chat_id,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                    'reply_markup' => $keyboard
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to notify admin {$admin->chat_id} for bot {$bot->bot_username}: " . $e->getMessage());
            }
        }
    }
}
