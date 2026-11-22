<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;

class PaymentController extends Controller
{
    /**
     * 🛡 متد کمکی برای جلوگیری از باگ تزریق HTML در تلگرام
     */
    protected function safeHTML(?string $text): string
    {
        if (empty($text)) return '';
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * 🌐 نمایش صفحه وب فاکتور به کاربر
     */
    public function show(Request $request, Invoice $invoice)
    {
        if ($invoice->status === 'paid') {
            return view('payment.result', ['invoice' => $invoice, 'success' => true, 'message' => 'این فاکتور قبلاً با موفقیت پرداخت شده است.']);
        }

        $address = \App\Models\UserAddress::find($invoice->user_address_id);

        return view('payment.show', compact('invoice', 'address'));
    }

    /**
     * 🚀 ارسال درخواست به زرین‌پال و ثبت تراکنش اولیه
     */
    public function request(Request $request, Invoice $invoice)
    {
        if ($invoice->status === 'paid') {
            return back()->with('error', 'فاکتور قبلاً پرداخت شده است.');
        }

        $isSandbox = env('ZARINPAL_SANDBOX', true);
        $merchantId = env('ZARINPAL_MERCHANT_ID', 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx');

        $apiUrl = $isSandbox ? 'https://sandbox.zarinpal.com/pg/v4/payment/request.json' : 'https://api.zarinpal.com/pg/v4/payment/request.json';
        $startPayUrl = $isSandbox ? 'https://sandbox.zarinpal.com/pg/StartPay/' : 'https://www.zarinpal.com/pg/StartPay/';

        $address = \App\Models\UserAddress::find($invoice->user_address_id);

        $data = [
            'merchant_id' => $merchantId,
            'amount' => (int) ($invoice->final_amount * 10), // 💡 تبدیل به Integer برای جلوگیری از باگ‌های Float
            // 💡 نیازی به ارسال invoice در URL کال‌بک نیست (امنیت بیشتر)
            'callback_url' => route('payment.callback'),
            'description' => "پرداخت سفارش " . $invoice->tracking_code,
            'metadata' => [
                'mobile' => $address ? $address->phone : '',
            ],
        ];

        // 💡 withoutVerifying() حذف شد تا روی سرور واقعی ایمن باشد
        $response = Http::timeout(30)->post($apiUrl, $data);
        $result = $response->json();

        if ($response->successful() && isset($result['data']['code']) && $result['data']['code'] == 100) {
            $authority = $result['data']['authority'];

            \App\Models\Transaction::create([
                'bot_id' => $invoice->bot_id,
                'user_id' => $invoice->user_id,
                'invoice_id' => $invoice->id,
                'amount' => $invoice->final_amount,
                'gateway' => 'zarinpal',
                'reference_id' => $authority,
                'status' => 'pending',
                'description' => 'هدایت کاربر به درگاه',
                'gateway_response' => $result
            ]);

            return redirect($startPayUrl . $authority);
        }

        Log::error('Zarinpal Request Failed', ['response' => $result]);
        return back()->with('error', 'خطا در ارتباط با درگاه پرداخت. لطفاً دقایقی دیگر تلاش کنید.');
    }

    /**
     * 🔄 بازگشت از بانک، بروزرسانی تراکنش و فاکتور
     */
    public function callback(Request $request)
    {
        $authority = $request->query('Authority');
        $status = $request->query('Status');

        // 🛡 پیدا کردن تراکنش بر اساس توکن غیرقابل جعل زرین‌پال (رفع باگ IDOR)
        $transaction = \App\Models\Transaction::where('reference_id', $authority)->first();

        if (!$transaction) {
            return view('payment.result', ['success' => false, 'message' => 'تراکنش یافت نشد یا نامعتبر است.']);
        }

        $invoice = Invoice::findOrFail($transaction->invoice_id);

        // 🛡 جلوگیری از تایید دوباره در صورت Refresh شدن صفحه (Idempotency)
        if ($invoice->status === 'paid' || $transaction->status === 'success') {
            return view('payment.result', [
                'invoice' => $invoice,
                'success' => true,
                'message' => 'این پرداخت قبلاً در سیستم ثبت شده است.',
                'refId' => $transaction->tracking_code
            ]);
        }

        // اگر کاربر انصراف داده بود
        if ($status !== 'OK') {
            $transaction->update(['status' => 'failed', 'description' => 'انصراف کاربر یا خطای درگاه']);
            $invoice->update(['status' => 'failed']);
            $this->notifyUserViaTelegram($invoice, false);
            return view('payment.result', ['invoice' => $invoice, 'success' => false, 'message' => 'پرداخت توسط شما لغو شد یا درگاه با خطا مواجه گردید.']);
        }

        $isSandbox = env('ZARINPAL_SANDBOX', true);
        $merchantId = env('ZARINPAL_MERCHANT_ID', 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx');

        $verifyUrl = $isSandbox ? 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json' : 'https://api.zarinpal.com/pg/v4/payment/verify.json';

        $data = [
            'merchant_id' => $merchantId,
            'amount' => (int) ($invoice->final_amount * 10),
            'authority' => $authority,
        ];

        $response = Http::timeout(30)->post($verifyUrl, $data);
        $result = $response->json();

        // در صورت موفقیت آمیز بودن پرداخت
        if ($response->successful() && isset($result['data']['code'])) {
            $code = $result['data']['code'];

            if ($code == 100 || $code == 101) {
                $refId = $result['data']['ref_id'];

                $transaction->update([
                    'status' => 'success',
                    'tracking_code' => $refId,
                    'description' => 'پرداخت موفق',
                    'gateway_response' => $result
                ]);

                $invoice->update(['status' => 'paid']);

                $this->notifyUserViaTelegram($invoice, true, $refId);
                return view('payment.result', ['invoice' => $invoice, 'success' => true, 'message' => 'پرداخت شما با موفقیت تایید شد.', 'refId' => $refId]);
            }
        }

        // در صورت ناموفق بودن تایید درگاه
        $transaction->update(['status' => 'failed', 'gateway_response' => $result]);
        $invoice->update(['status' => 'failed']);
        $this->notifyUserViaTelegram($invoice, false);

        return view('payment.result', ['invoice' => $invoice, 'success' => false, 'message' => 'خطا در تایید پرداخت. در صورت کسر وجه، مبلغ تا ۷۲ ساعت آینده بازمی‌گردد.']);
    }

    /**
     * 📢 تولید فاکتور PDF، ذخیره در سرور و ارسال به چت تلگرام کاربر
     */
    protected function notifyUserViaTelegram(Invoice $invoice, $isSuccess, $refId = null)
    {
        $botToken = $invoice->bot->bot_token;
        $chatId = $invoice->user->chat_id;

        if (!$chatId) return;

        if ($isSuccess) {
            $text = "🎉 <b>پرداخت شما با موفقیت انجام شد!</b>\n\n";
            $text .= "شماره فاکتور: <code>{$invoice->tracking_code}</code>\n";
            $text .= "کد پیگیری درگاه: <code>{$refId}</code>\n";
            $text .= "مبلغ پرداختی: <b>" . number_format($invoice->final_amount) . " تومان</b>\n\n";
            $text .= "سفارش شما در سیستم ثبت شد و فاکتور رسمی شما در قالب فایل PDF به این پیام پیوست شده است. از خرید شما سپاسگزاریم 🌹";

            try {
                $address = \App\Models\UserAddress::find($invoice->user_address_id);
                $items = $invoice->items;

                $pdf = PDF::loadView('payment.pdf', [
                    'invoice' => $invoice,
                    'address' => $address,
                    'items' => $items,
                    'refId' => $refId
                ]);

                $pdfContent = $pdf->output();
                $fileName = 'invoices/' . $invoice->tracking_code . '.pdf';
                Storage::disk('public')->put($fileName, $pdfContent);

                $invoice->update(['receipt_file_id' => $fileName]);

                $response = Http::attach(
                    'document',
                    $pdfContent,
                    "Invoice-{$invoice->tracking_code}.pdf"
                )->post("https://api.telegram.org/bot{$botToken}/sendDocument", [
                    'chat_id' => $chatId,
                    'caption' => $text,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [[['text' => '🔙 بازگشت به فروشگاه', 'callback_data' => 'main_menu']]]
                    ])
                ]);

                $this->notifyAdminsViaTelegram($invoice);

            } catch (\Exception $e) {
                Log::error('PDF Generation Error: ' . $e->getMessage());
                Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'HTML'
                ]);
            }
        } else {
            $text = "❌ <b>عملیات پرداخت ناموفق بود!</b>\n\n";
            $text .= "شماره فاکتور: <code>{$invoice->tracking_code}</code>\n\n";
            $text .= "<i>در صورتی که مبلغ از حساب شما کسر شده است، ظرف ۷۲ ساعت از طرف بانک به حساب شما عودت داده خواهد شد.</i>";

            Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [[['text' => '🔙 بازگشت به فروشگاه', 'url' => "https://t.me/" . $invoice->bot->bot_username]]]
                ])
            ]);
        }
    }

    /**
     * 📢 ارسال نوتیفیکیشن به تمام ادمین‌های ربات
     */
    protected function notifyAdminsViaTelegram(Invoice $invoice)
    {
        $botToken = $invoice->bot->bot_token;

        $admins = \App\Models\User::where('bot_id', $invoice->bot_id)
            ->whereIn('is_admin', [1, 2])
            ->whereNotNull('chat_id')
            ->get();

        if ($admins->isEmpty()) return;

        $address = \App\Models\UserAddress::find($invoice->user_address_id);

        // 🛡 ایمن‌سازی اطلاعات مشتری برای جلوگیری از کرش شدن تلگرام
        $safeFirstName = $this->safeHTML($invoice->user->first_name ?? 'بدون نام');
        $safeLastName  = $this->safeHTML($invoice->user->last_name ?? '');
        $safeUsername  = $this->safeHTML($invoice->user->username ?? 'ندارد');
        $safeAddress   = $this->safeHTML(mb_substr($address ? $address->address : 'ندارد', 0, 30));

        $text = "🛍 <b>یک سفارش جدید ثبت شد!</b>\n\n";
        $text .= "🔸 کد پیگیری: <code>{$invoice->tracking_code}</code>\n";
        $text .= "👤 خریدار: {$safeFirstName} {$safeLastName} (@{$safeUsername})\n";
        $text .= "💳 مبلغ پرداختی: <b>" . number_format($invoice->final_amount) . " تومان</b>\n";
        $text .= "📍 شهر/استان: {$safeAddress}...\n\n";
        $text .= "👇 برای مشاهده جزئیات و تایید سفارش روی دکمه زیر کلیک کنید:";

        $keyboard = json_encode([
            'inline_keyboard' => [
                [['text' => '👁 مشاهده و مدیریت سفارش', 'callback_data' => "admin_view_order_{$invoice->id}"]]
            ]
        ]);

        foreach ($admins as $admin) {
            Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $admin->chat_id,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => $keyboard
            ]);
        }
    }
}
