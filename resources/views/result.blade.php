<x-layouts.main title="نتیجه پرداخت">

    <div class="auth-wrapper" style="padding: 120px 20px 40px; min-height: 80vh; display: flex; align-items: center; justify-content: center;">
        <div class="auth-card" style="text-align: center; max-width: 500px; padding: 50px 30px;">

            @if($type === 'success')
                <div style="font-size: 5rem; color: #10b981; margin-bottom: 20px;">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <h2 style="font-weight: 800; margin-bottom: 15px; color: var(--text-primary);">پرداخت موفقیت‌آمیز بود!</h2>
                <p style="color: var(--text-secondary); margin-bottom: 25px; line-height: 1.8;">{{ $message }}</p>

                <div style="background: var(--bg-sunken); padding: 15px; border-radius: 12px; margin-bottom: 25px; text-align: right;">
                    {{-- نمایش شماره سفارش از روی آیدی پرداخت --}}
                    @if(isset($payment) && $payment->id)
                        <p style="margin: 5px 0;"><strong>شماره سفارش:</strong> <code>{{ $payment->id }}</code></p>
                    @endif

                    @if($success && isset($refId))
                        <p style="margin: 5px 0;"><strong>شماره پیگیری بانک:</strong> <code>{{ $refId }}</code></p>
                    @endif
                </div>

            @else
                <div style="font-size: 5rem; color: #ef4444; margin-bottom: 20px;">
                    <i class="fa-solid fa-circle-xmark"></i>
                </div>
                <h2 style="font-weight: 800; margin-bottom: 15px; color: var(--text-primary);">پرداخت ناموفق!</h2>
                <p style="color: var(--text-secondary); margin-bottom: 25px; line-height: 1.8;">{{ $message }}</p>

                {{-- نمایش شماره سفارش در صورت خطا --}}
                @if(isset($payment) && $payment->id)
                    <p style="margin-bottom: 25px;">شماره سفارش: <code>{{ $payment->id }}</code></p>
                @endif
            @endif

            {{-- استخراج هوشمند یوزرنیم ربات اختصاصی کاربر (در صورت وجود) یا ارجاع به ربات مادر --}}
            @php
                $botUrl = (isset($payment) && $payment->bot && $payment->bot->bot_username)
                    ? "tg://resolve?domain=" . $payment->bot->bot_username
                    : "tg://resolve?domain=" .'RoboShop_fatherbot';
            @endphp

            <a href="{{ $botUrl }}" class="btn btn-primary btn-block btn-lg" style="justify-content: center;">
                <i class="fa-brands fa-telegram" style="margin-left: 8px;"></i> بازگشت به تلگرام
            </a>

        </div>
    </div>

</x-layouts.main>
