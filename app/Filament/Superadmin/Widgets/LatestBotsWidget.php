<?php

namespace App\Filament\Superadmin\Widgets;

use App\Models\ManagedBot;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestBotsWidget extends BaseWidget
{
    // این ویجت کل عرض صفحه را بگیرد
    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'آخرین فروشگاه‌های ساخته شده (تازه تاسیس)';
    protected static ?int $sort = 2; // نمایش زیر آمار کلان

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ManagedBot::query()
                    ->where('bot_id_string', '!=', 'admin_bot')
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('bot_name')
                    ->label('نام فروشگاه')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('bot_username')
                    ->label('آیدی تلگرام')
                    ->prefix('@')
                    ->copyable() // با کلیک روی آیدی، کپی می‌شود
                    ->color('primary'),

                Tables\Columns\TextColumn::make('owner_phone')
                    ->label('شماره مالک')
                    ->icon('heroicon-m-phone'),

                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'blocked' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'فعال',
                        'pending' => 'در انتظار استارت',
                        'blocked' => 'مسدود',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ ساخت')
                    ->formatStateUsing(fn ($state) => $state ? \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($state))->format('Y/m/d H:i') : '-')
                    ->color('gray'),
            ])
            ->paginated(false); // در صفحه داشبورد نیازی به صفحه‌بندی نیست
    }
}
