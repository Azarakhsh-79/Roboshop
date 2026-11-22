<?php

namespace App\Filament\Resources\UserResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\User;

class UserStatsOverview extends BaseWidget
{
    // این متد باعث می‌شود ویجت‌ها در موبایل ۱ ستونه و در دسکتاپ ۳ ستونه باشند
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $botId = session('current_shop_id');

        // محاسبه آمارها بر اساس فروشگاه فعلی
        $totalUsers = User::where('bot_id', $botId)->count();

        $newUsers = User::where('bot_id', $botId)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $blockedUsers = User::where('bot_id', $botId)
            ->whereIn('status', ['blocked', 'baned'])
            ->count();

        return [
            Stat::make('کل مشتریان فروشگاه', $totalUsers)
                ->description('تعداد کل افراد ثبت‌نامی')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('مشتریان جدید', $newUsers)
                ->description('اضافه شده در ۳۰ روز اخیر')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->chart([7, 2, 10, 3, 15, 4, 17]), // یک گراف کوچک و زیبا زیر عدد نشان می‌دهد

            Stat::make('کاربران محدود شده', $blockedUsers)
                ->description('مسدود یا اخراج شده')
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color('danger'),
        ];
    }
}
