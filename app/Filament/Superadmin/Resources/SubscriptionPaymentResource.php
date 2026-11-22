<?php

namespace App\Filament\Superadmin\Resources;

use App\Filament\Superadmin\Resources\SubscriptionPaymentResource\Pages;
use App\Models\SubscriptionPayment;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Morilog\Jalali\Jalalian;

class SubscriptionPaymentResource extends Resource
{
    protected static ?string $model = SubscriptionPayment::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationLabel = 'گزارشات مالی و اشتراک‌ها';
    protected static ?string $modelLabel = 'تراکنش';
    protected static ?string $pluralModelLabel = 'تراکنش‌های پلتفرم';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('شناسه')->sortable(),

                Tables\Columns\TextColumn::make('bot.bot_name')
                    ->label('فروشگاه / ربات')
                    ->searchable()
                    ->description(fn ($record) => '@' . ($record->bot?->bot_username ?? 'حذف شده')),

                Tables\Columns\TextColumn::make('plan_name')->label('نوع پلن')->badge()->color('info'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('مبلغ پرداختی')
                    ->formatStateUsing(fn ($state) => number_format($state) . ' تومان')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('وضعیت تراکنش')
                    ->colors([
                        'success' => ['paid', 'success', 'completed'],
                        'warning' => ['pending', 'awaiting_approval'],
                        'danger' => ['failed', 'rejected'],
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'paid', 'success', 'completed' => 'موفق و پرداخت شده',
                        'pending', 'awaiting_approval' => 'در انتظار تایید',
                        'failed', 'rejected' => 'ناموفق / رد شده',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ تراکنش')
                    ->formatStateUsing(fn ($state) => $state ? Jalalian::fromCarbon(\Carbon\Carbon::parse($state))->format('Y/m/d H:i') : '-')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('وضعیت پرداخت')
                    ->options([
                        'paid' => 'موفق',
                        'awaiting_approval' => 'در انتظار تایید',
                        'failed' => 'شکست خورده',
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptionPayments::route('/'),
        ];
    }

    // جلوگیری از ساخت تراکنش دستی توسط ادمین
    public static function canCreate(): bool
    {
        return false;
    }
}
