<x-layouts.main title="انتخاب فروشگاه | RoboShop">

    <div class="auth-wrapper" style="padding: 40px 20px;">
        <div class="auth-card" style="max-width: 800px; width: 100%;">

            <div class="auth-logo">
                <i class="fa-solid fa-store" style="color: var(--c-green);"></i>
            </div>

            <h1 class="auth-title">مدیریت فروشگاه‌ها</h1>
            <p class="auth-subtitle">وضعیت ربات‌های خود را بررسی کرده و وارد پنل شوید.</p>

            @if(session('telegram_link'))
                <div class="fade-in-up" style="background-color: #e6f4ea; padding: 25px; border-radius: 12px; border: 2px solid #1e8e3e; margin-top: 20px; text-align: center;">
                    <h3 style="color: #1e8e3e; margin-bottom: 10px; font-size: 1.5rem;"><i class="fa-solid fa-circle-check"></i> ربات با موفقیت متصل شد!</h3>
                    <p style="margin-bottom: 20px; font-size: 1.1rem; color: #333;">برای <strong>شناسایی خودکار شما به عنوان ادمین</strong> و فعال‌سازی پنل، لطفاً روی دکمه زیر کلیک کنید تا وارد ربات شوید و دکمه <strong>Start</strong> را بزنید.</p>
                    <a href="{{ session('telegram_link') }}" target="_blank" class="btn btn-primary btn-lg" style="background-color: #0088cc; border-color: #0088cc; padding: 15px 30px; font-size: 1.2rem; width: 100%;">
                        <i class="fa-brands fa-telegram" style="margin-right: 8px; font-size: 1.5rem;"></i> ورود به تلگرام و تکمیل ثبت‌نام
                    </a>
                </div>
            @endif

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 30px;">

                @foreach($shops as $shop)
                    <div style="border: 2px solid var(--border-color, #eee); border-radius: 12px; padding: 20px; text-align: center; display: flex; flex-direction: column; justify-content: space-between;">

                        <div>
                            <div style="font-size: 3rem; color: var(--c-green); margin-bottom: 15px;">
                                <i class="fa-solid fa-robot"></i>
                            </div>

                            <h3 style="font-size: 1.3rem; margin-bottom: 10px; color: var(--text-main);">
                                {{ $shop->bot_name ?? 'بدون نام' }}
                            </h3>
                            <div style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 5px; direction: ltr;">
                                {{ '@' . $shop->bot_username }}
                            </div>

                            <span style="display: inline-block; font-size: 0.8rem; background-color: {{ $shop->status === 'active' ? '#e6f4ea' : '#fff3cd' }}; color: {{ $shop->status === 'active' ? '#1e8e3e' : '#856404' }}; padding: 4px 8px; border-radius: 4px; margin-bottom: 20px;">
                                {{ $shop->status === 'active' ? 'فعال' : 'در انتظار تایید تلگرام' }}
                            </span>
                        </div>

                        @if($shop->status === 'pending')
                            <button disabled class="btn btn-outline" style="width: 100%; border-color: #ffc107; color: #856404; background-color: #fff3cd; cursor: not-allowed;">
                                <i class="fa-solid fa-hourglass-half"></i> منتظر تایید در تلگرام
                            </button>
                            <p style="font-size: 0.8rem; margin-top:10px; color: #888;">پس از استارت در تلگرام، صفحه را رفرش کنید.</p>
                        @else
                            <form action="{{ route('panel.select', $shop->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-primary" style="width: 100%;">
                                    ورود به پنل <i class="fa-solid fa-arrow-left" style="margin-right: 5px;"></i>
                                </button>
                            </form>
                        @endif
                    </div>
                @endforeach

            </div>

            <div class="auth-footer" style="margin-top: 30px;">
                <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="auth-back text-danger">
                    خروج از حساب کاربری <i class="fa-solid fa-right-from-bracket"></i>
                </a>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                    @csrf
                </form>
            </div>

        </div>
    </div>

</x-layouts.main>
