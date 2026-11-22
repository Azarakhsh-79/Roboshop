<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\UserAddress;
use App\Models\Transaction;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;

class InvoiceController extends Controller
{
    public function streamPdf(Invoice $invoice)
    {
        // لود کردن ریلیشن‌های مورد نیاز برای جلوگیری از کوئری‌های اضافی
        $invoice->load(['items', 'user', 'bot']);

        // پیدا کردن آدرس
        $addressId = $invoice->user_info['address_id'] ?? null;
        $address = $addressId ? UserAddress::find($addressId) : null;

        $items = $invoice->items;

        // پیدا کردن کد رهگیری تراکنش در صورت وجود
        $transaction = Transaction::where('invoice_id', $invoice->id)->where('status', 'success')->first();
        $refId = $transaction ? $transaction->reference_id : null;

        // ساخت PDF از فایل Blade که در مرحله قبل طراحی کردیم
        $pdf = PDF::loadView('payment.pdf', [
            'invoice' => $invoice,
            'address' => $address,
            'items'   => $items,
            'refId'   => $refId
        ]);

        // استفاده از stream باعث می‌شود PDF در مرورگر باز شود (نه اینکه مستقیم دانلود شود)
        return $pdf->stream('Invoice-' . $invoice->tracking_code . '.pdf');
    }
}
