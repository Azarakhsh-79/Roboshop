<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\Invoice;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    // 🚀 اضافه کردن تب‌های وضعیت با شمارشگر زنده
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('همه سفارشات'),

            'paid' => Tab::make('پرداخت شده (جدید)')
                ->badge(Invoice::where('status', 'paid')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'paid')),

            'processing' => Tab::make('در حال پردازش')
                ->badge(Invoice::where('status', 'processing')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'processing')),

            'shipped' => Tab::make('ارسال شده')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'shipped')),

            'canceled' => Tab::make('لغو شده')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'canceled')),
        ];
    }
}
