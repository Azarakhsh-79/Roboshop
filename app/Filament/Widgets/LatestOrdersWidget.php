<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Invoice;

class LatestOrdersWidget extends BaseWidget
{
    protected static ?int $sort = 3; // نمایش زیر باکس‌های آماری

    // گرفتن عرض کامل صفحه
    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'آخرین سفارشات ثبت شده';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Invoice::query()
                    ->where('bot_id', session('current_shop_id'))
                    ->latest() // مرتب‌سازی از جدیدترین به قدیمی‌ترین
                    ->limit(5) // فقط ۵ سفارش آخر
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('شماره فاکتور')
                    ->formatStateUsing(fn($state) => '#' . $state)
                    ->weight('bold'),

                // فرض بر این است که فاکتور ریلیشنی به نام user دارد
                Tables\Columns\TextColumn::make('user.first_name')
                    ->label('مشتری')
                    ->default('ناشناس')
                    ->description(fn(Invoice $record) => $record->user?->phone ?? ''),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('مبلغ')
                    ->formatStateUsing(fn($state) => number_format($state) . ' تومان')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'paid', 'delivered' => 'success',
                        'processing' => 'warning',
                        'shipped' => 'info',
                        'canceled', 'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'paid' => 'پرداخت شده',
                        'processing' => 'در حال پردازش',
                        'shipped' => 'ارسال شده',
                        'delivered' => 'تحویل شده',
                        'canceled' => 'لغو شده',
                        'failed' => 'ناموفق',
                        'pending' => 'در انتظار',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ')
                    ->formatStateUsing(fn($state) => $state ? \Morilog\Jalali\Jalalian::fromDateTime($state)->format('Y/m/d H:i') : '-'),
            ])
            ->paginated(false)
            ->emptyStateHeading('هیچ سفارشی یافت نشد')
            ->emptyStateDescription('تاکنون هیچ سفارشی در این فروشگاه ثبت نشده است.')
            ->emptyStateIcon('heroicon-o-shopping-bag')
            ->actions([
                Tables\Actions\Action::make('view_invoice')
                    ->label('مشاهده')
                    ->icon('heroicon-m-eye')
                    // انتقال به صفحه جزئیات فاکتور (کلاس InvoiceResource باید در پروژه شما وجود داشته باشد)
                    ->url(fn(Invoice $record): string => \App\Filament\Resources\InvoiceResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
