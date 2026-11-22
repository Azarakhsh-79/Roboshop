<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ManagedBot;
use App\Models\User;
use App\Models\Product;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UpdateBotStats extends Command
{
    // نام دستوری که در ترمینال یا کرون‌جاب فراخوانی می‌شود
    protected $signature = 'shop:update-stats';

    protected $description = 'به‌روزرسانی آمار و ارقام داشبورد تمام ربات‌های فروشگاهی';

    public function handle()
    {
        $this->info('🚀 در حال شروع به‌روزرسانی آمار ربات‌ها...');
        Log::channel('cron')->info('Bot stats update cron started.');

        // استخراج ربات‌های فعال و در انتظار
        $bots = ManagedBot::whereIn('status', ['active', 'pending'])->where('bot_id_string', '!=','admin_bot')->get();
        $today = Carbon::today();

        $bar = $this->output->createProgressBar(count($bots));
        $bar->start();

        foreach ($bots as $bot) {
            $botId = $bot->id;

            // 👥 ۱. آمار کاربران
            $totalUsers = User::where('bot_id', $botId)->count();
            $newUsersToday = User::where('bot_id', $botId)->whereDate('created_at', $today)->count();

            // کاربران فعال امروز (کسانی که امروز فاکتور آپدیت‌شده‌ای داشتند)
            $activeUsersToday = Invoice::where('bot_id', $botId)
                ->whereDate('updated_at', $today)
                ->distinct('user_id')
                ->count('user_id');

            // محاسبه درصد رشد کاربران (جلوگیری از خطای تقسیم بر صفر و سرریز دیتابیس)
            $usersYesterday = $totalUsers - $newUsersToday;
            $userGrowthPercent = 0;
            if ($usersYesterday > 0) {
                $userGrowthPercent = ($newUsersToday / $usersYesterday) * 100;
            } elseif ($newUsersToday > 0) {
                $userGrowthPercent = 100;
            }
            $userGrowthPercent = min(999.99, max(0, $userGrowthPercent));

            // 📦 ۲. آمار محصولات
            $totalProducts = Product::where('bot_id', $botId)->count();
            $newProductsToday = Product::where('bot_id', $botId)->whereDate('created_at', $today)->count();
            $topProduct = Product::where('bot_id', $botId)->orderByDesc('sales_count')->value('name');

            // 🛒 ۳. آمار سفارشات
            $totalOrders = Invoice::where('bot_id', $botId)->count();
            $completedOrders = Invoice::where('bot_id', $botId)
                ->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])
                ->count();
            $pendingInvoices = Invoice::where('bot_id', $botId)
                ->whereIn('status', ['pending', 'awaiting_approval'])
                ->count();

            // 💰 ۴. آمار مالی
            // محاسبه بر اساس فاکتورهای موفق (مبلغ کل منهای تخفیف)
            $successfulInvoices = Invoice::where('bot_id', $botId)
                ->whereIn('status', ['paid', 'processing', 'shipped', 'delivered']);

            $totalRevenue = (clone $successfulInvoices)->sum(DB::raw('total_amount - discount_amount'));
            $todaysRevenue = (clone $successfulInvoices)->whereDate('created_at', $today)->sum(DB::raw('total_amount - discount_amount'));

            // میانگین خرید هر کاربر
            $usersWithPurchases = (clone $successfulInvoices)->distinct('user_id')->count('user_id');
            $avgUserPurchase = $usersWithPurchases > 0 ? ($totalRevenue / $usersWithPurchases) : 0;

            // 🔄 ۵. آپدیت دیتابیس ربات
            $bot->update([
                'total_users' => $totalUsers,
                'new_users_today' => $newUsersToday,
                'active_users_today' => $activeUsersToday,
                'total_products' => $totalProducts,
                'new_products_today' => $newProductsToday,
                'user_growth_percent' => $userGrowthPercent,
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'pending_invoices' => $pendingInvoices,
                'todays_revenue' => $todaysRevenue,
                'total_revenue' => $totalRevenue,
                'avg_user_purchase' => $avgUserPurchase,
                'top_product' => $topProduct,
                'stats_last_updated_at' => now(),
            ]);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('✅ آمار تمام ربات‌ها با موفقیت آپدیت شد.');
        Log::channel('cron')->info('Bot stats update cron finished successfully.');
    }
}
