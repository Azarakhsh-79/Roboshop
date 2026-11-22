<?php

namespace App\Filament\Resources\SettingResource\Pages;

use App\Filament\Resources\SettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSettings extends ListRecords
{
    protected static string $resource = SettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\CreateAction::make(), // دکمه ساخت تنظیمات جدید را بستیم چون سیستم داینامیک است
        ];
    }

    // 🚀 جادوی تب‌بندی حرفه‌ای بر اساس موضوع تنظیمات
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('همه موارد')
                ->icon('heroicon-m-squares-2x2'),

            'general' => Tab::make('عمومی و فروشگاه')
                ->icon('heroicon-m-building-storefront')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('key', [
                    'store_name', 'is_active', 'welcome_message', 'show_discounted_products'
                ])),

            'support' => Tab::make('پشتیبانی و ارتباطات')
                ->icon('heroicon-m-phone-arrow-up-right')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('key', [
                    'support_phone', 'support_text', 'support_telegram_id'
                ])),

            'telegram' => Tab::make('تلگرام و کانال')
                ->icon('heroicon-m-paper-airplane')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('key', [
                    'channel_id', 'force_join'
                ])),

            'finance' => Tab::make('مالی و ارسال')
                ->icon('heroicon-m-banknotes')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('key', [
                    'shipping_cost'
                ])),
        ];
    }
}
