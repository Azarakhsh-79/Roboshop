@props(['bot', 'showBottomNav' => true])

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>{{ $bot->bot_name }}</title>
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: 'var(--color-primary)',
                        'primary-light': 'var(--color-primary-light)',
                        'nav-bg': '#1E1E24',
                        'nav-active': '#2A2A35',
                    }
                }
            }
        }
    </script>

    @php
        $primaryColor = $bot->settings->where('key', 'primary_color')->first()->value ?? '#7C3AED';

        // تشخیص اتوماتیک تب فعال بر اساس URL فعلی
        $currentPath = request()->path();
        $activeTabName = 'home';
        if (Str::contains($currentPath, '/cart')) {
            $activeTabName = 'cart';
        } elseif (Str::contains($currentPath, '/category')) {
            $activeTabName = 'categories';
        } elseif (Str::contains($currentPath, '/search')) {
            $activeTabName = 'search';
        } elseif (Str::contains($currentPath, '/profile') || Str::contains($currentPath, '/login')) {
            $activeTabName = 'profile';
        }

        // به دست آوردن تعداد آیتم‌های سبد خرید برای نمایش در Badge
        $cartCount = 0;
        if (auth()->check()) {
            $cartCount = \App\Models\Cart::where('bot_id', $bot->id)->where('user_id', auth()->id())->sum('quantity');
        }
    @endphp

    <style>
        :root {
            --color-primary: {{ $primaryColor }};
            --color-primary-light: {{ $primaryColor }}30;
        }

        .pb-safe {
            padding-bottom: calc(90px + env(safe-area-inset-bottom));
        }

        .nav-item-active {
            transform: translateY(-16px);
            background-color: var(--color-primary);
            color: white;
            box-shadow: 0 10px 20px -5px var(--color-primary-light);
            border: 6px solid #121214;
        }
    </style>
</head>

<body class="bg-[#121214] text-gray-100 antialiased pb-safe" x-data="bottomNav('{{ $activeTabName }}')">

    <div id="app-content" class="min-h-screen">
        {{ $slot }}
    </div>

    @if ($showBottomNav)
        <!-- ========================================== -->
        <!-- نوار ناوبری شناور کپسولی (Floating Capsule) -->
        <!-- ========================================== -->
        <div class="fixed bottom-4 left-4 right-4 z-[60] flex justify-center pb-[env(safe-area-inset-bottom)]">
            <nav
                class="bg-nav-bg w-full max-w-md h-[68px] rounded-[34px] flex justify-around items-center px-2 shadow-2xl relative">

                <!-- تب خانه (Home) -->
                <a href="/app/{{ request()->route('bot_username') }}" @click.prevent="goTo('home', $el.href)"
                    class="relative z-10 flex flex-col items-center justify-center w-[60px] h-[68px] tap-transparent">
                    <div class="w-[52px] h-[52px] rounded-full flex items-center justify-center transition-all duration-300 ease-out"
                        :class="activeTab === 'home' ? 'nav-item-active' : 'text-gray-400 hover:text-gray-200'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                    </div>
                </a>

                <!-- تب جستجو (Search) - لینک غیرفعال با انیمیشن -->
                <button @click.prevent="mockClick('search')"
                    class="relative z-10 flex flex-col items-center justify-center w-[60px] h-[68px] tap-transparent">
                    <div class="w-[52px] h-[52px] rounded-full flex items-center justify-center transition-all duration-300 ease-out"
                        :class="activeTab === 'search' ? 'nav-item-active' : 'text-gray-400 hover:text-gray-200'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </button>

               <!-- تب دسته‌بندی (Categories) -->
                <a href="/app/{{ request()->route('bot_username') }}/category" 
                    @click.prevent="goTo('categories', $el.href)"
                    class="relative z-10 flex flex-col items-center justify-center w-[60px] h-[68px] tap-transparent">
                    <div class="w-[52px] h-[52px] rounded-full flex items-center justify-center transition-all duration-300 ease-out"
                        :class="activeTab === 'categories' ? 'nav-item-active' : 'text-gray-400 hover:text-gray-200'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                        </svg>
                    </div>
                </a>
                
                <!-- تب سبد خرید (Cart) -->
                <a href="/app/{{ request()->route('bot_username') }}/cart" @click.prevent="goTo('cart', $el.href)"
                    class="relative z-10 flex flex-col items-center justify-center w-[60px] h-[68px] tap-transparent">
                    <div class="w-[52px] h-[52px] rounded-full flex items-center justify-center transition-all duration-300 ease-out relative"
                        :class="activeTab === 'cart' ? 'nav-item-active' : 'text-gray-400 hover:text-gray-200'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        @if ($cartCount > 0)
                            <span x-show="activeTab !== 'cart'"
                                class="absolute top-1 right-2 bg-red-500 text-white text-[10px] font-bold w-4 h-4 rounded-full flex items-center justify-center border border-[#1E1E24]">
                                {{ $cartCount > 9 ? '+9' : $cartCount }}
                            </span>
                        @endif
                    </div>
                </a>

                <!-- تب حساب کاربری (Profile / Login) -->
                <a href="/app/{{ request()->route('bot_username') }}/{{ auth()->check() ? 'profile' : 'login' }}"
                    @click.prevent="goTo('profile', $el.href)"
                    class="relative z-10 flex flex-col items-center justify-center w-[60px] h-[68px] tap-transparent">
                    <div class="w-[52px] h-[52px] rounded-full flex items-center justify-center transition-all duration-300 ease-out"
                        :class="activeTab === 'profile' ? 'nav-item-active' : 'text-gray-400 hover:text-gray-200'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                </a>

            </nav>
        </div>
    @endif

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('bottomNav', (serverTab) => ({
                // مقدار اولیه را null می‌گذاریم تا هنگام لود، همه دکمه‌ها در حالت "خوابیده" باشند
                activeTab: null,

                init() {
                    // با ۵۰ میلی‌ثانیه تاخیر، تب فعال را تنظیم می‌کنیم
                    // این کار باعث می‌شود کاربر انیمیشن پاپ‌آپ (بالا پریدن دکمه) را در لحظه ورود به صفحه ببیند
                    setTimeout(() => {
                        this.activeTab = serverTab;
                    }, 50);
                },

                // متد رفتن به صفحات واقعی (مثل سبد خرید و خانه)
                goTo(tab, url) {
                    if (this.activeTab === tab) return;

                    // ۱. فقط ایجاد لرزش در گوشی (Haptic Feedback)
                    if (window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp
                        .HapticFeedback) {
                        window.Telegram.WebApp.HapticFeedback.selectionChanged();
                    }

                    // ۲. بدون هیچ مکث و تغییر استایلی، مستقیماً به صفحه جدید می‌رویم تا تیک نداشته باشد
                    window.location.href = url;
                },

                // متد دکمه‌های "به زودی..." 
                mockClick(tab) {
                    if (window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp
                        .HapticFeedback) {
                        window.Telegram.WebApp.HapticFeedback.selectionChanged();
                    }

                    let previousTab = this.activeTab;
                    this.activeTab = tab; // دکمه با انیمیشن بالا می‌آید

                    setTimeout(() => {
                        alert('این بخش به زودی فعال می‌شود...');
                        this.activeTab = previousTab; // دکمه برمی‌گردد پایین
                    }, 300);
                }
            }))
        });

        // کانفیگ وب‌اپ تلگرام
        document.addEventListener('DOMContentLoaded', () => {
            if (window.Telegram && window.Telegram.WebApp) {
                const tg = window.Telegram.WebApp;
                tg.expand();
                tg.ready();
                tg.setHeaderColor('#121214');
                tg.setBackgroundColor('#121214');
            }
        });
    </script>
</body>

</html>
