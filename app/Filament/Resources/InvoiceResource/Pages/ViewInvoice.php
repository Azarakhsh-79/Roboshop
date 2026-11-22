<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        $invoice = $this->getRecord();

        // 👁 اگر سفارش پرداخت شده است و ادمین الان روی "مشاهده" کلیک کرده
        if ($invoice->status === 'paid') {

            // آپدیت کردن وضعیت فاکتور به "در حال پردازش"
            $invoice->update(['status' => 'processing']);

            // نیازی به نوشتن کد ارسال تلگرام اینجا نیست، متد booted در مدل Invoice کارش را انجام می‌دهد!
        }
    }
}
