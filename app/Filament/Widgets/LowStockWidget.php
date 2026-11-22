<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use App\Models\Product;

class LowStockWidget extends BaseWidget
{
    protected static ?int $sort = 3; // هم‌ردیف با سفارشات زنده
    protected int|string|array $columnSpan = 1; // نصف صفحه
    protected static ?string $heading = '⚠️ هشدار اتمام موجودی انبار';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->where('bot_id', session('current_shop_id'))
                    ->where('stock', '<=', 5) // فقط موجودی‌های ۵ و کمتر را نشان بده
                    ->orderBy('stock', 'asc') // مرتب‌سازی از اونی که صفر شده به بالا
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('نام محصول')
                    ->limit(30),

                Tables\Columns\TextColumn::make('stock')
                    ->label('موجودی فعلی')
                    ->badge()
                    ->color(fn(int $state): string => $state === 0 ? 'danger' : 'warning'), // صفر باشه قرمز، کمتر از ۵ زرد
            ])
            ->paginated(false)
            ->emptyStateHeading('موجودی کالاها تکمیل است ✅')
            ->emptyStateDescription('هیچ محصولی با موجودی در حال اتمام (۵ عدد یا کمتر) یافت نشد.')
            ->emptyStateIcon('heroicon-o-check-badge')
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('شارژ انبار')
                    ->icon('heroicon-m-pencil')
                    ->url(fn(Product $record): string => \App\Filament\Resources\ProductResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
