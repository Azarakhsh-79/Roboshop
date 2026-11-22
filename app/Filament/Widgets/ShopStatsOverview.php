<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;

class ShopStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    protected static ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $botId = session('current_shop_id');

        $stats = Invoice::where('bot_id', $botId)
            ->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])
            ->selectRaw('SUM(total_amount) as total_sum, SUM(discount_amount) as discount_sum')
            ->first();

        $totalRevenue = ($stats->total_sum ?? 0) - ($stats->discount_sum ?? 0);

        $todayOrders = Invoice::where('bot_id', $botId)
            ->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])
            ->whereDate('created_at', today())
            ->count();

        // 🚀 محاسبه سبدهای رها شده (پرداخت نشده)
        $abandonedCarts = Invoice::where('bot_id', $botId)->where('status', 'pending')->count();

        $totalProducts = Product::where('bot_id', $botId)->count();
        $totalUsers = User::where('bot_id', $botId)->count();

        return [
            Stat::make('درآمد خالص', number_format($totalRevenue) . ' تومان')
                ->description('پس از کسر تخفیف‌ها')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart([10, 25, 15, 40, 30, 50, 60]),

            Stat::make('سفارشات امروز', number_format($todayOrders))
                ->description('تایید شده امروز')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('success'),

            Stat::make('سبدهای رها شده', number_format($abandonedCarts))
                ->description('فاکتورهای پرداخت نشده')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('danger') // رنگ قرمز برای جلب توجه ادمین
                ->chart([5, 2, 8, 3, 5, 2]),

            Stat::make('تنوع کالاها', number_format($totalProducts))
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('warning'),

            Stat::make('کل مشتریان', number_format($totalUsers))
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),
        ];
    }
}
