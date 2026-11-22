<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SubscriptionPaymentController extends Controller
{
    private $merchantId;
    private $isSandbox;

    public function __construct()
    {
        $this->merchantId = env('ZARINPAL_MERCHANT_ID');
        $this->isSandbox = env('ZARINPAL_SANDBOX', false);
    }



    public function pay(Request $request, SubscriptionPayment $payment)
    {
        if ($payment->status === 'paid') {
            Log::warning("Zarinpal Subscription [Pay]: Attempt to pay an already paid subscription.", ['payment_id' => $payment->id]);
            return $this->showResult('این اشتراک قبلاً پرداخت و فعال شده است.', 'error', $payment);
        }

        $apiUrl = $this->isSandbox
            ? 'https://sandbox.zarinpal.com/pg/v4/payment/request.json'
            : 'https://api.zarinpal.com/pg/v4/payment/request.json';

        $callbackUrl = route('subscription.verify');
        $description = "خرید پلن {$payment->plan_name} برای ربات " . ($payment->bot->bot_username ?? 'نامشخص');

        $payload = [
            'merchant_id' => $this->merchantId,
            'amount' => $payment->amount * 10,
            'description' => $description,
            'callback_url' => $callbackUrl,
        ];

        if (!empty($payment->user->phone)) {
            $payload['metadata'] = ['mobile' => $payment->user->phone];
        }

        // لاگ شروع درخواست به زرین‌پال
        Log::info("Zarinpal Subscription [Pay]: Initiating request.", ['payment_id' => $payment->id, 'amount' => $payload['amount']]);

        $response = Http::post($apiUrl, $payload);
        $result = $response->json();

        if ($response->successful() && isset($result['data']['code']) && $result['data']['code'] == 100) {
            $authority = $result['data']['authority'];
            $payment->update(['authority' => $authority]);

            // لاگ موفقیت‌آمیز بودن ساخت توکن
            Log::info("Zarinpal Subscription [Pay]: Authority generated successfully.", [
                'payment_id' => $payment->id,
                'authority' => $authority
            ]);

            $gatewayUrl = $this->isSandbox
                ? "https://sandbox.zarinpal.com/pg/StartPay/{$authority}"
                : "https://www.zarinpal.com/pg/StartPay/{$authority}";

            return redirect($gatewayUrl);
        }

        // لاگ خطای زرین‌پال با ثبت کامل ریسپانس
        Log::error("Zarinpal Subscription [Pay]: Request Failed.", [
            'payment_id' => $payment->id,
            'response' => $result ?? [],
            'http_status' => $response->status()
        ]);

        return $this->showResult('خطا در اتصال به درگاه بانکی. لطفاً دقایقی دیگر تلاش کنید.', 'error', $payment);
    }

    public function verify(Request $request)
    {
        $authority = $request->query('Authority');
        $status = $request->query('Status');

        // لاگ اولیه از بازگشت کاربر به سایت
        Log::info("Zarinpal Subscription [Verify]: Callback received.", ['authority' => $authority, 'status' => $status]);

        if (!$authority) {
            Log::warning("Zarinpal Subscription [Verify]: Missing Authority in callback.");
            return $this->showResult('درخواست نامعتبر است.', 'error');
        }

        $payment = SubscriptionPayment::where('authority', $authority)->with(['bot', 'user'])->first();

        if (!$payment) {
            Log::error("Zarinpal Subscription [Verify]: Transaction not found for Authority.", ['authority' => $authority]);
            return $this->showResult('تراکنش یافت نشد.', 'error');
        }

        if ($payment->status === 'paid') {
            Log::info("Zarinpal Subscription [Verify]: Callback for already paid transaction.", ['payment_id' => $payment->id]);
            return $this->showResult('این تراکنش قبلاً با موفقیت تایید شده است.', 'success', $payment);
        }

        if ($status !== 'OK') {
            $payment->update(['status' => 'failed']);
            Log::notice("Zarinpal Subscription [Verify]: Payment cancelled by user or failed.", ['payment_id' => $payment->id]);
            return $this->showResult('پرداخت توسط شما لغو شد یا ناموفق بود.', 'error', $payment);
        }

        $apiUrl = $this->isSandbox
            ? 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json'
            : 'https://api.zarinpal.com/pg/v4/payment/verify.json';

        // لاگ ارسال درخواست تایید به بانک
        Log::info("Zarinpal Subscription [Verify]: Sending verify request to bank.", ['payment_id' => $payment->id]);

        $response = Http::post($apiUrl, [
            'merchant_id' => $this->merchantId,
            'amount' => $payment->amount * 10,
            'authority' => $authority,
        ]);

        $result = $response->json();

        if ($response->successful() && isset($result['data']['code']) && in_array($result['data']['code'], [100, 101])) {
            $refId = $result['data']['ref_id'];

            $payment->update([
                'status' => 'paid',
                'ref_id' => $refId
            ]);

            // لاگ پرداخت کاملاً موفق
            Log::info("Zarinpal Subscription [Verify]: Payment verified successfully.", [
                'payment_id' => $payment->id,
                'ref_id' => $refId,
                'code' => $result['data']['code']
            ]);

            $this->upgradeBotPlan($payment);
            $this->sendSuccessNotificationToAdmin($payment, $refId);

            return $this->showResult('پرداخت با موفقیت انجام شد و اشتراک شما فعال گردید.', 'success', $payment, $refId);
        }

        $payment->update(['status' => 'failed']);

        // لاگ عدم تایید توسط بانک (مغایرت)
        Log::error("Zarinpal Subscription [Verify]: Verification failed by bank.", [
            'payment_id' => $payment->id,
            'response' => $result ?? [],
            'http_status' => $response->status()
        ]);

        return $this->showResult('تراکنش توسط بانک تایید نشد.', 'error', $payment);
    }

    private function upgradeBotPlan(SubscriptionPayment $payment)
    {
        $bot = $payment->bot;

        // 👈 نام فیلدها دقیقاً مطابق با ساختار جدول managed_bots شما
        $expiresField = 'subscription_expires_at';
        $planSlugField = 'subscription_plan';

        $currentExpireStr = $bot->{$expiresField} ?? now();
        $currentExpire = Carbon::parse($currentExpireStr);

        // اگر تاریخ انقضای قبلی گذشته باشد، از همین امروز شروع می‌کنیم
        if ($currentExpire->isPast()) {
            $currentExpire = now();
        }

        // اضافه کردن روزها بر اساس فیلد duration_days
        $newExpireDate = $currentExpire->copy()->addDays($payment->duration_days);

        // لاگ برای بررسی دقیق تغییرات در فایل laravel.log
        Log::info("Bot Plan Upgrade:", [
            'bot_id' => $bot->id,
            'old_expire' => $bot->{$expiresField},
            'new_expire' => $newExpireDate->toDateTimeString(),
            'added_days' => $payment->duration_days
        ]);

        // ذخیره مستقیم در ستون‌های صحیح جدول managed_bots
        $bot->{$planSlugField} = $payment->plan_key;
        $bot->{$expiresField} = $newExpireDate;
        $bot->save();

        // پاک کردن کش تنظیمات ربات برای اعمال تغییرات لایو
        if ($bot->webhook_secret) {
            Cache::forget("bot_config:{$bot->webhook_secret}");
        }
    }
    private function sendSuccessNotificationToAdmin(SubscriptionPayment $payment, $refId)
    {
        $botToken = $payment->bot->bot_token;
        $chatId = $payment->user->chat_id;

        if (!$botToken || !$chatId)
            return;

        $jalaliExpire = \Morilog\Jalali\Jalalian::fromDateTime($payment->bot->plan_expires_at)->format('Y/m/d');
        $amount = number_format($payment->amount);

        $text = "🎉 <b>پرداخت موفق و ارتقای اشتراک</b>\n\n";
        $text .= "خرید اشتراک شما با موفقیت در سیستم ثبت شد.\n\n";
        $text .= "🔹 <b>پلن:</b> {$payment->plan_name}\n";
        $text .= "⏳ <b>مدت زمان:</b> {$payment->duration_days} روز\n";
        $text .= "💳 <b>مبلغ پرداخت شده:</b> {$amount} تومان\n";
        $text .= "🧾 <b>کد پیگیری پرداخت:</b> <code>{$refId}</code>\n\n";
        $text .= "📅 <b>تاریخ پایان اشتراک جدید:</b> {$jalaliExpire}\n\n";
        $text .= "✅ هم‌اکنون محدودیت‌های پلن جدید برای شما اعمال شده است.";

        $keyboard = ['inline_keyboard' => [[['text' => '🔙 بازگشت به پنل مدیریت', 'callback_data' => 'admin_panel']]]];

        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($keyboard)
        ]);
    }

    // 💡 متد showResult اصلاح شد تا آبجکت $payment را به ویو بفرستد
    private function showResult($message, $type = 'success', $payment = null, $refId = null)
    {
        $success = ($type === 'success');
        return view('result', compact('message', 'type', 'payment', 'refId', 'success'));
    }
}
