<?php

namespace App\Filament\Superadmin\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\ManagedBot;
use App\Models\Invoice;
use App\Models\SubscriptionPayment;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;

class PlatformStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $activeBots = ManagedBot::where('status', 'active')->count();
        $totalBots = ManagedBot::where('bot_id_string', '!=', 'admin_bot')->count();

        // 🗓 محاسبه تاریخ شروع ماه شمسی جاری
        $nowJalali = Jalalian::now();
        $startOfMonthJalali = new Jalalian($nowJalali->getYear(), $nowJalali->getMonth(), 1, 0, 0, 0);
        $startOfMonthCarbon = $startOfMonthJalali->toCarbon();

        // ۱. درآمد پلتفرم از اول ماه شمسی تاکنون
        $platformRevenueThisMonth = SubscriptionPayment::whereIn('status', ['paid', 'success', 'completed'])
            ->where('created_at', '>=', $startOfMonthCarbon)
            ->sum('amount');

        $totalPlatformRevenue = SubscriptionPayment::whereIn('status', ['paid', 'success', 'completed'])
            ->sum('amount');

        // ۲. گردش مالی فروشگاه‌ها (GMV) از اول ماه شمسی تاکنون (با استفاده از ستون‌های موجود)
        $gmvThisMonth = Invoice::whereIn('status', ['paid', 'processing', 'shipped', 'delivered', 'completed'])
            ->where('created_at', '>=', $startOfMonthCarbon)
            ->sum(DB::raw('total_amount - COALESCE(discount_amount, 0)'));

        $monthName = $nowJalali->format('%B');

        return [
            Stat::make('فروشگاه‌های فعال', $activeBots . ' از ' . $totalBots)
                ->description('تعداد کل ربات‌های ثبت شده')
                ->descriptionIcon('heroicon-m-cpu-chip')
                ->color('success'),

            Stat::make("درآمد پلتفرم ({$monthName})", number_format($platformRevenueThisMonth) . ' تومان')
                ->description('کل درآمد تاریخی: ' . number_format($totalPlatformRevenue) . ' تومان')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary')
                // 👈 اتصال ۱۰۰٪ تضمینی به ریسورس ساخته شده
                ->url(\App\Filament\Superadmin\Resources\SubscriptionPaymentResource::getUrl('index'))
                ->extraAttributes([
                    'class' => 'cursor-pointer transition hover:scale-[1.01]',
                ]),

            Stat::make("فروش فروشگاه‌ها - GMV ({$monthName})", number_format($gmvThisMonth) . ' تومان')
                ->description('مجموع خرید مشتریان از اول ماه')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('warning'),
        ];
    }
}
