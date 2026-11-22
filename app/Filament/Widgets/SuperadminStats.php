<?php

namespace App\Filament\Superadmin\Widgets;

use App\Models\ManagedBot;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SuperadminStats extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('کل فروشگاه‌های فعال', ManagedBot::where('status', 'active')->count())
                ->description('ربات‌های در حال کار')
                ->descriptionIcon('heroicon-m-play')
                ->color('success'),

            Stat::make('کل مشتریان در کل سیستم', User::count())
                ->description('کاربران عضو شده در تمام ربات‌ها')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),

            Stat::make('درآمد کل پلتفرم', number_format(SubscriptionPayment::where('status', 'paid')->sum('amount')) . ' تومان')
                ->description('از فروش اشتراک‌ها به مغازه‌داران')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),
        ];
    }
}
