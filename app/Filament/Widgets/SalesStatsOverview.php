<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesStatsOverview extends BaseWidget
{
    protected static bool $isDiscovered = false; // 👈 این خط جادویی را اضافه کنید
    // این ویجت را فول‌عرض نمی‌کنیم تا در کنار بقیه ویجت‌ها جا بگیرد
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $botId = session('current_shop_id'); // 🔒 امنیت: استخراج آمار فقط برای همین فروشگاه

        $successfulStatuses = ['paid', 'processing', 'shipped', 'delivered'];

        // 🧮 محاسبه هوشمند درآمد با استفاده از ستون‌های واقعی دیتابیس
        $invoiceStats = Invoice::where('bot_id', $botId)
            ->whereIn('status', $successfulStatuses)
            ->selectRaw('SUM(total_amount) as total_sum, SUM(discount_amount) as discount_sum')
            ->first();

        // درآمد کل = جمع مبالغ کل - جمع تخفیف‌ها
        $totalRevenue = ($invoiceStats->total_sum ?? 0) - ($invoiceStats->discount_sum ?? 0);

        $totalOrders = Invoice::where('bot_id', $botId)
            ->whereIn('status', $successfulStatuses)
            ->count();

        // محصولات و کاربران (فقط همین فروشگاه)
        $totalProducts = Product::where('bot_id', $botId)
            ->where('is_active', true)
            ->count();

        $totalCustomers = User::where('bot_id', $botId)
            ->whereNotNull('chat_id')
            ->count();

        return [
            Stat::make('درآمد کل فروشگاه', number_format($totalRevenue) . ' تومان')
                ->description('مجموع فروش موفق تا این لحظه')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart([7, 2, 10, 3, 15, 4, 17]),

            Stat::make('سفارشات موفق', number_format($totalOrders) . ' سفارش')
                ->description('فاکتورهای پرداخت شده')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary'),

            Stat::make('محصولات فعال', number_format($totalProducts) . ' کالا')
                ->description('موجود در ویترین فروشگاه')
                ->descriptionIcon('heroicon-m-cube')
                ->color('info'),

            Stat::make('مشتریان ربات', number_format($totalCustomers) . ' نفر')
                ->description('کل کاربران جذب شده')
                ->descriptionIcon('heroicon-m-users')
                ->color('warning'),
        ];
    }
}
