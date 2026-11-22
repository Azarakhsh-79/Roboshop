<x-layouts.roboshop title="ساخت فروشگاه جدید | RoboShop" mode="home"
    :links="[['صفحه اصلی', route('home')]]">

    <div class="alert-stack">
        @if (session('info'))
            <div class="alert alert-info" role="alert"><span>{{ session('info') }}</span><button type="button" aria-label="بستن" onclick="this.parentElement.remove()">×</button></div>
        @endif
        @if (session('reg_error'))
            <div class="alert alert-error" role="alert"><span>{{ session('reg_error') }}</span><button type="button" aria-label="بستن" onclick="this.parentElement.remove()">×</button></div>
        @endif
    </div>

    <main id="home" class="relative overflow-hidden py-12 sm:py-20">
        <div class="hero-grid absolute inset-0 opacity-60" aria-hidden="true"></div>
        <div class="glow -right-36 -top-36 bg-violet-500" aria-hidden="true"></div>
        <div class="glow -bottom-64 -left-40 bg-cyan-400" aria-hidden="true"></div>

        <div class="container-x relative">
            <div class="mx-auto max-w-[900px]">

                <div class="mb-9 text-center">
                    <div class="mx-auto mb-5 grid h-16 w-16 place-items-center rounded-2xl border border-cyan-300/30 bg-cyan-300/10 text-3xl">🚀</div>
                    <h1 class="section-title mb-3">راه‌اندازی <span class="gradient-text">فروشگاه جدید</span></h1>
                    <p class="mx-auto max-w-xl text-sm leading-8 muted">ساخت و مدیریت فروشگاه حالا کامل تو ربات تلگرامی ما انجام می‌شه؛ ساده و یکپارچه.</p>
                </div>

                <div class="grid items-stretch gap-5 md:grid-cols-2">

                    <!-- راهنمای ایجاد فروشگاه -->
                    <div class="glass rounded-[24px] p-6 sm:p-8">
                        <h3 class="mb-6 flex items-center gap-2 text-lg font-extrabold text-cyan-200"><span aria-hidden="true">✈️</span> راهنمای ایجاد فروشگاه</h3>

                        <ol class="m-0 flex list-none flex-col gap-5 p-0">
                            <li class="flex items-start gap-3">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-cyan-300/10 text-sm font-black text-cyan-200">۱</span>
                                <p class="text-sm leading-8 text-slate-300">روی دکمه‌ی <strong class="text-white">«ورود به ربات سازنده»</strong> کلیک کن.</p>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-violet-300/10 text-sm font-black text-violet-200">۲</span>
                                <p class="text-sm leading-8 text-slate-300">تو تلگرام، تو ربات مادر دکمه‌ی
                                    <code class="mx-1 inline-block rounded-md bg-white/10 px-2 py-0.5 text-xs text-cyan-200" dir="ltr">START</code>
                                    رو بزن.</p>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-cyan-300/10 text-sm font-black text-cyan-200">۳</span>
                                <p class="text-sm leading-8 text-slate-300">مراحل ساخت ربات و اتصالش رو قدم‌به‌قدم همون‌جا، تو خود تلگرام، انجام بده.</p>
                            </li>
                        </ol>

                        <div class="mt-6 flex items-start gap-3 rounded-2xl border border-emerald-300/20 bg-emerald-300/[.07] p-4 text-sm leading-8 text-emerald-200">
                            <span aria-hidden="true">✅</span>
                            <p>بعد از ساخت، فروشگاهت خودکار به همین پنل وصل می‌شه.</p>
                        </div>
                    </div>

                    <!-- انتقال به تلگرام -->
                    <div class="glass flex flex-col items-center justify-center rounded-[24px] p-6 text-center sm:p-8">
                        <div class="mb-5 grid h-20 w-20 place-items-center rounded-full bg-[#2AABEE]/15 text-[2.6rem]" aria-hidden="true">✈️</div>

                        <h3 class="mb-2 text-lg font-extrabold">انتقال به تلگرام</h3>
                        <p class="mb-7 text-sm leading-8 muted">برای امنیت و راحتی خودت، ادامه‌ی مراحل تو تلگرام انجام می‌شه.</p>

                        <!-- توکن ورود با ?start= پاس داده می‌شه -->
                        <a href="https://t.me/RoboShop_fatherbot?start={{ auth()->user()->entry_token }}" class="btn btn-primary w-full">
                            ورود به ربات سازنده <span aria-hidden="true">↗</span>
                        </a>
                    </div>
                </div>

                <div class="mt-8">
                    <a href="{{ route('home') }}" class="inline-flex min-h-[44px] items-center gap-2 text-sm font-bold text-cyan-300 no-underline">
                        <span aria-hidden="true">→</span> بازگشت به صفحه اصلی
                    </a>
                </div>
            </div>
        </div>
    </main>

</x-layouts.roboshop>
