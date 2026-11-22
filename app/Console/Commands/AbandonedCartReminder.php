<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cart;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AbandonedCartReminder extends Command
{
    protected $signature = 'shop:cart-reminder';
    protected $description = 'ارسال پیام یادآوری برای سبدهای خرید رها شده (پس از ۲۴ ساعت)';

    public function handle()
    {
        // استفاده از کانال لاگ اختصاصی کرون‌جاب
        Log::channel('cron')->info('========== AbandonedCartReminder START ==========');

        // استخراج زمان دقیق ۲۴ ساعت پیش
        $thresholdDate = Carbon::now()->subHours(24);

        // واکشی سبدهایی که بیشتر از ۲۴ ساعت آپدیت نشده‌اند و گروه‌بندی بر اساس مشتری
        $abandonedCarts = Cart::with(['bot', 'user', 'product'])
            ->where('updated_at', '<', $thresholdDate)
            ->get()
            ->groupBy('user_id');

        $sentCount = 0;

        foreach ($abandonedCarts as $userId => $items) {
            $user = $items->first()->user;
            $bot = $items->first()->bot;

            if (!$user || !$bot || empty($user->chat_id)) {
                continue;
            }

            // گرفتن آخرین زمان آپدیتِ سبدِ این شخص
            $lastUpdate = $items->max('updated_at');

            // 🚀 تولید کلید یکتا برای کش: اگر کاربر تغییری در سبد ندهد، این کلید همیشه ثابت است
            $cacheKey = "cart_reminder_{$bot->id}_{$userId}_{$lastUpdate->timestamp}";

            // اگر قبلا این پیام برای این سبد (با این زمان آپدیت) ارسال شده، از آن رد شو
            if (Cache::has($cacheKey)) {
                continue;
            }

            // استخراج نام ۳ محصول اول برای جذاب‌تر شدن پیام
            $productNames = $items->take(3)->pluck('product.name')->implode('، ');
            $moreText = $items->count() > 3 ? ' و ...' : '';

            $text = "🛒 <b>سبد خریدت منتظرته!</b>\n\n"
                  . "سلام {$user->first_name} عزیز 👋\n"
                  . "دیدیم که محصولاتی مثل <b>({$productNames}{$moreText})</b> رو به سبد خریدت اضافه کردی اما سفارشت رو هنوز نهایی نکردی.\n\n"
                  . "⏳ <i>ممکنه این محصولات به زودی ناموجود بشن یا قیمتشون تغییر کنه.</i>\n\n"
                  . "👇 برای مشاهده سبد خرید و تکمیل سفارش، روی دکمه زیر کلیک کن:";

            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '🛒 مشاهده و پرداخت سبد خرید', 'callback_data' => 'view_cart']]
                ]
            ];

            try {
                $response = Http::timeout(10)->post("https://api.telegram.org/bot{$bot->bot_token}/sendMessage", [
                    'chat_id' => $user->chat_id,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode($keyboard)
                ]);

                if ($response->successful()) {
                    // ذخیره در کش برای ۷ روز (چون سبدها بعد از ۷ روز توسط کرون‌جاب CleanOldCarts کلاً پاک می‌شوند)
                    Cache::put($cacheKey, true, now()->addDays(7));
                    $sentCount++;
                    Log::channel('cron')->info("Reminder sent to User ID: {$userId} for Bot: {$bot->bot_username}");
                }
            } catch (\Exception $e) {
                Log::channel('cron')->error("Failed to send reminder to user {$userId}: " . $e->getMessage());
            }
        }

        Log::channel('cron')->info("Total reminders sent: {$sentCount}");
        Log::channel('cron')->info('========== AbandonedCartReminder FINISHED ==========');

        $this->info("✅ تعداد {$sentCount} پیام یادآوری با موفقیت ارسال شد.");
    }
}
