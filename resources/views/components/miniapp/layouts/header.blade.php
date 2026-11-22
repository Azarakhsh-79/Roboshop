@props(['shopName', 'botAvatar' => null])

<!--
  راه‌اندازی Alpine.js برای مدیریت باز و بسته شدن منو
  و همچنین تشخیص اتوماتیک کاربر از طریق API تلگرام (Telegram WebApp)
-->
<div x-data="{
    isMenuOpen: false,
    tgUser: null,
    isLoggedIn: {{ auth()->check() ? 'true' : 'false' }}, // اضافه شدن وضعیت لاگین برای بررسی SSO
    async initMenu() {
        // بررسی می‌کنیم که آیا در محیط تلگرام هستیم و کاربر شناسایی شده یا خیر
        if (typeof window.Telegram !== 'undefined' && window.Telegram.WebApp.initDataUnsafe && window.Telegram.WebApp.initDataUnsafe.user) {
            this.tgUser = window.Telegram.WebApp.initDataUnsafe.user;

            // اگر قبلا در لاراول لاگین نیست، سعی کن اتوماتیک لاگینش کنی
            if (!this.isLoggedIn) {
                try {
                    let response = await fetch('/app/{{ request()->route('bot_username') }}/auth', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            initData: window.Telegram.WebApp.initData
                        })
                    });

                    let result = await response.json();

                    if (result.success && result.status === 'logged_in') {
                        // لاگین اتوماتیک موفقیت آمیز بود! رفرش صفحه برای اعمال لاگین لاراول
                        window.location.reload();
                    }
                } catch (error) {
                    console.error('Auto-login failed', error);
                }
            }
        }
    }
}" x-init="initMenu()">

    <header
        class="sticky top-0 z-40 w-full bg-[#121214]/80 backdrop-blur-2xl border-b border-white/[0.05] shadow-[0_4px_20px_rgba(0,0,0,0.2)]">
        <div class="flex justify-between items-center px-4 py-3 max-w-lg mx-auto">

            <!-- سمت راست: آواتار فروشگاه -->
            <div class="flex items-center">
                <div
                    class="relative w-10 h-10 rounded-full p-[2px] bg-gradient-to-tr from-primary/80 to-primary-light shadow-lg shadow-primary/20">
                    <div
                        class="w-full h-full rounded-full overflow-hidden bg-[#1E1E24] flex items-center justify-center border border-[#121214]">
                        @if ($botAvatar)
                            <img src="{{ $botAvatar }}" alt="{{ $shopName }}" class="w-full h-full object-cover">
                        @else
                            <span class="text-primary font-black text-sm">{{ mb_substr($shopName, 0, 1) }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- وسط: نام فروشگاه -->
            <div class="flex-1 px-4 text-center">
                <h1 class="text-[15px] font-extrabold text-white tracking-wide truncate drop-shadow-md">
                    {{ $shopName }}
                </h1>
                <p class="text-[9px] text-gray-500 font-medium tracking-wider uppercase mt-0.5">فروشگاه رسمی</p>
            </div>

            <!-- سمت چپ: دکمه همبرگری -->
            <button @click="isMenuOpen = true"
                class="w-10 h-10 flex items-center justify-center rounded-full bg-white/5 text-gray-300 hover:text-white hover:bg-white/10 transition-all duration-200 active:scale-90 border border-white/5">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h10M4 17h16" />
                </svg>
            </button>

        </div>
    </header>

    <!-- ========================================== -->
    <!-- منوی کشویی سایدبار (Sidebar) -->
    <!-- ========================================== -->

    <!-- هاله مشکی پس‌زمینه -->
    <div x-show="isMenuOpen" x-transition:enter="transition-opacity ease-linear duration-300"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-300" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0" @click="isMenuOpen = false"
        class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[60]" style="display: none;">
    </div>

    <!-- بدنه منو -->
    <div x-show="isMenuOpen" x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-300" x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="fixed top-0 left-0 bottom-0 w-[280px] bg-[#121214] border-r border-white/10 z-[70] flex flex-col shadow-[10px_0_30px_rgba(0,0,0,0.5)]"
        style="display: none;">

        <!-- ======================= -->
        <!-- بخش هدرِ منو: اطلاعات کاربر -->
        <!-- ======================= -->
        <div class="px-5 py-6 border-b border-white/5 bg-gradient-to-b from-[#1E1E24]/50 to-transparent relative">

            <!-- دکمه بستن منو (گوشه بالا) -->
            <button @click="isMenuOpen = false"
                class="absolute top-4 left-4 w-7 h-7 flex items-center justify-center bg-white/5 rounded-full text-gray-400 active:scale-90 transition-transform">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            @auth
                <!-- حالت ۱: کاربر لاگین کرده در دیتابیس لاراول شما -->
                <div class="flex items-center gap-3 mt-2">
                    <div
                        class="w-12 h-12 rounded-full bg-primary/20 border border-primary flex items-center justify-center text-primary font-bold shadow-lg shadow-primary/20">
                        {{ mb_substr(auth()->user()->first_name ?? (auth()->user()->name ?? 'ک'), 0, 1) }}
                    </div>
                    <div>
                        <h3 class="text-white font-bold text-[14px]">
                            {{ auth()->user()->first_name ?? (auth()->user()->name ?? 'کاربر گرامی') }}</h3>
                        <!-- نمایش شماره تلفن (از دیتابیس) -->
                        <p class="text-gray-400 text-[12px] font-medium mt-1 tracking-wider dir-ltr text-right">
                            {{ auth()->user()->phone ?? '---' }}
                        </p>
                    </div>
                </div>
            @else
                <!-- حالت ۲ و ۳: کاربر هنوز لاگین نکرده (مهمان) -->

                <!-- الف) کاربر در مرورگر است (خارج از تلگرام) -->
                <div x-show="!tgUser" class="flex items-center gap-3 mt-2">
                    <div
                        class="w-12 h-12 rounded-full bg-[#1E1E24] border border-white/10 flex items-center justify-center text-gray-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-white font-bold text-[13px]">کاربر مهمان</h3>
                        <!-- اصلاح لینک لاگین -->
                        <a href="/app/{{ request()->route('bot_username') }}/login"
                            class="mt-1.5 inline-flex bg-primary hover:bg-primary-light text-white text-[10px] font-bold px-3 py-1.5 rounded-lg transition-colors items-center gap-1 active:scale-95 shadow-lg shadow-primary/30">
                            ورود با شماره موبایل
                        </a>
                    </div>
                </div>

                <!-- ب) کاربر داخل مینی‌اپ تلگرام است (نامش را داریم ولی شماره‌اش را نه) -->
                <div x-show="tgUser" style="display: none;" class="flex items-center gap-3 mt-2">
                    <!-- نمایش عکس تلگرام کاربر یا حرف اول اسمش -->
                    <div
                        class="w-12 h-12 rounded-full overflow-hidden bg-[#1E1E24] border border-white/10 flex items-center justify-center text-primary font-bold">
                        <!-- اصلاح 바ین딩 عکس تلگرام (استفاده از template x-if برای جلوگیری از ارور تصویر خالی) -->
                        <template x-if="tgUser?.photo_url">
                            <img :src="tgUser.photo_url" class="w-full h-full object-cover">
                        </template>
                        <template x-if="!tgUser?.photo_url">
                            <span x-text="tgUser?.first_name ? tgUser.first_name.charAt(0) : 'U'"></span>
                        </template>
                    </div>
                    <div class="flex-1">
                        <!-- خوش‌آمدگویی با نام تلگرامی کاربر -->
                        <h3 class="text-white font-bold text-[13px]" x-text="(tgUser?.first_name || '')"></h3>
                        <!-- اصلاح لینک تایید تلفن -->
                        <a href="/app/{{ request()->route('bot_username') }}/login"
                            class="mt-1.5 inline-flex bg-white/10 hover:bg-white/20 text-white border border-white/10 text-[10px] font-bold px-3 py-1.5 rounded-lg transition-colors items-center gap-1 active:scale-95">
                            تایید شماره تلفن
                        </a>
                    </div>
                </div>
            @endauth
        </div>

        <!-- لینک‌های منو -->
        <div class="flex-1 overflow-y-auto px-4 py-4 space-y-1">

            <a href="/app/{{ request()->route('bot_username') }}"
                class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-200 hover:bg-white/5 hover:text-white transition-colors">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span class="text-[13px] font-medium">صفحه اصلی فروشگاه</span>
            </a>

            <a href="#"
                class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-200 hover:bg-white/5 hover:text-white transition-colors">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                <span class="text-[13px] font-medium">سفارشات من</span>
            </a>

            <a href="#"
                class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-200 hover:bg-white/5 hover:text-white transition-colors">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                </svg>
                <span class="text-[13px] font-medium">علاقه‌مندی‌ها</span>
            </a>

            <hr class="border-white/5 my-2">

            @auth
                <!-- دکمه خروج فقط وقتی لاگین است نشان داده شود -->
                <!-- اصلاح لینک لاگ‌اوت -->
                <a href="/app/{{ request()->route('bot_username') }}/logout"
                    class="flex items-center gap-3 px-4 py-3 rounded-xl text-red-400 hover:bg-red-500/10 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                    </svg>
                    <span class="text-[13px] font-medium">خروج از حساب</span>
                </a>
            @endauth
        </div>

    </div>
