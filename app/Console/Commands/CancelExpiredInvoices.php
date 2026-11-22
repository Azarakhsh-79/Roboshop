<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CancelExpiredInvoices extends Command
{
    // نام دستوری که لاراول قرار است اجرا کند
    protected $signature = 'shop:cancel-expired';

    protected $description = 'لغو فاکتورهای پرداخت‌نشده بعد از ۳۰ دقیقه و بازگشت موجودی به انبار';

    public function handle()
    {
        try {
            Log::channel('cron')->info('========== CancelExpiredInvoices START ==========', [
                'time' => now()->toDateTimeString(),
            ]);

            Log::channel('cron')->info('Searching for expired invoices...');

            $expiredInvoices = Invoice::with(['items', 'bot', 'user'])
                ->where('status', 'pending')
                ->where('created_at', '<', Carbon::now()->subMinutes(30))
                ->get();

            Log::channel('cron')->info('Expired invoices found.', [
                'count' => $expiredInvoices->count(),
            ]);

            $count = 0;

            foreach ($expiredInvoices as $invoice) {

                Log::channel('cron')->info('Processing invoice...', [
                    'invoice_id' => $invoice->id,
                    'tracking_code' => $invoice->tracking_code,
                    'user_id' => $invoice->user_id,
                ]);

                foreach ($invoice->items as $item) {

                    if ($item->variant_id) {

                        ProductVariant::where('id', $item->variant_id)
                            ->increment('stock', $item->quantity);

                        Log::channel('cron')->info('Variant stock restored.', [
                            'variant_id' => $item->variant_id,
                            'quantity' => $item->quantity,
                        ]);

                    } elseif ($item->product_id) {

                        Product::where('id', $item->product_id)
                            ->increment('stock', $item->quantity);

                        Log::channel('cron')->info('Product stock restored.', [
                            'product_id' => $item->product_id,
                            'quantity' => $item->quantity,
                        ]);
                    }
                }

                $invoice->update([
                    'status' => 'canceled'
                ]);

                Log::channel('cron')->info('Invoice canceled.', [
                    'invoice_id' => $invoice->id,
                ]);

                if ($invoice->user && $invoice->user->chat_id && $invoice->bot) {

                    $response = Http::post(
                        "https://api.telegram.org/bot{$invoice->bot->bot_token}/sendMessage",
                        [
                            'chat_id' => $invoice->user->chat_id,
                            'text' => "⚠️ <b>لغو خودکار سفارش</b>\n\nسفارش <code>{$invoice->tracking_code}</code> شما به دلیل عدم پرداخت در زمان مقرر (۳۰ دقیقه) لغو شد و موجودی به فروشگاه بازگشت.\n\nجهت خرید مجدد، لطفاً دوباره محصولات را به سبد خرید اضافه کنید.",
                            'parse_mode' => 'HTML'
                        ]
                    );

                    Log::channel('cron')->info('Telegram notification sent.', [
                        'invoice_id' => $invoice->id,
                        'status' => $response->status(),
                        'success' => $response->successful(),
                    ]);
                } else {

                    Log::warning('Telegram notification skipped.', [
                        'invoice_id' => $invoice->id,
                        'has_user' => (bool) $invoice->user,
                        'has_bot' => (bool) $invoice->bot,
                    ]);
                }

                $count++;
            }

            Log::channel('cron')->info('========== CancelExpiredInvoices FINISHED ==========', [
                'processed_count' => $count,
                'time' => now()->toDateTimeString(),
            ]);

            $this->info("✅ تعداد {$count} فاکتور منقضی شده با موفقیت لغو شد.");

            return Command::SUCCESS;

        } catch (\Throwable $e) {

            Log::error('========== CancelExpiredInvoices FAILED ==========', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
