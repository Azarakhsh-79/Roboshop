<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Actions\Action;

class Dashboard extends BaseDashboard
{
    // 🚀 اضافه کردن دکمه‌های دسترسی سریع به هدر (بالای داشبورد)
    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_product')
                ->label('محصول جدید')
                ->icon('heroicon-o-plus')
                ->color('success')
                ->url(fn () => \App\Filament\Resources\ProductResource::getUrl('create')),

            Action::make('users_list')
                ->label('مدیریت مشتریان')
                ->icon('heroicon-o-users')
                ->color('info')
                ->url(fn () => \App\Filament\Resources\UserResource::getUrl('index')),

            Action::make('settings')
                ->label('تنظیمات ربات')
                ->icon('heroicon-o-cog-8-tooth')
                ->color('gray')
                ->url(fn () => \App\Filament\Resources\SettingResource::getUrl('index')),
        ];
    }
}
