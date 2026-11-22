<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cart;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CleanOldCarts extends Command
{
    // نام دستوری که در ترمینال یا سرور اجرا می‌شود (با قابلیت دریافت تعداد روز)
    protected $signature = 'shop:clean-carts {--days=7 : تعداد روزهای مجاز برای رها شدن سبد خرید}';

    protected $description = 'پاکسازی خودکار سبدهای خرید قدیمی و رها شده از دیتابیس';

    public function handle()
    {
        Log::channel('cron')->info('========== CleanOldCarts START ==========');

        // خواندن تعداد روز از ورودی دستور (پیش‌فرض ۷ روز)
        $days = (int) $this->option('days');

        // محاسبه تاریخ انقضای سبدها
        $thresholdDate = Carbon::now()->subDays($days);

        // 🗑 پاک کردن تمام ردیف‌هایی که در $days روز گذشته هیچ آپدیتی (تغییر تعداد یا افزودن) نداشته‌اند
        $deletedCount = Cart::where('updated_at', '<', $thresholdDate)->delete();

        Log::channel('cron')->info("Deleted {$deletedCount} old cart items (older than {$days} days).");
        Log::channel('cron')->info('========== CleanOldCarts FINISHED ==========');

        $this->info("✅ تعداد {$deletedCount} آیتم رها شده در سبد خرید (قدیمی‌تر از {$days} روز) با موفقیت پاکسازی شد.");
    }
}
