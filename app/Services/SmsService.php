<?php

namespace App\Services;

use Cryptommer\Smsir\Classes\Smsir;
use Exception;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected $smsir;

    public function __construct()
    {
        $lineNumber = env('SMSIR_LINE_NUMBER');
        $apiKey = env('SMSIR_API_KEY');

        // راه‌اندازی کلاس اصلی ارتباط با وب‌سرویس
        $this->smsir = new Smsir($lineNumber, $apiKey);
    }

    /**
     * ارسال پیامک کد تایید (عبور از بلک لیست)
     */
    public function sendOtp($phone, $code)
    {
        try {
            $templateId = 235902;
            $parameters = [
                [
                    'name'  => 'CODE', // این کلمه باید دقیقاً با نام متغیر در پترن سایت SMS.ir یکی باشد
                    'value' => (string) $code
                ]
            ];

            // ارسال درخواست وریفای (سریع)
            $res = $this->smsir->Send()->Verify($phone, (int) $templateId, $parameters);
            log::info("OTP sent to $phone with code $code");
            log::debug('SMS.ir Verify Response: ' . json_encode($res));


            return true;
        } catch (Exception $e) {
            Log::error('SMS.ir Verify Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * ارسال پیامک معمولی (متن دلخواه)
     */
    public function send($phone, $message)
    {
        try {
            // ارسال گروهی (Bulk) برای متن‌های غیر پترنی
           // $this->smsir->Send()->Bulk($message, [$phone]);
            Log::info("Bulk SMS sent to $phone with message: $message");

            return true;
        } catch (Exception $e) {
            Log::error('SMS.ir Bulk Error: ' . $e->getMessage());
            return false;
        }
    }
}
