<x-miniapp.layouts.master :bot="$bot" :show-bottom-nav="false">

    <!-- پردازش بک‌اند: تنوع‌ها، موجودی و وضعیت علاقه‌مندی -->
    @php
        $hasVariablePrices = false;
        if ($product->variants && $product->variants->isNotEmpty()) {
            foreach ($product->variants as $variant) {
                if ($variant->price != $product->price || $variant->discount_price != $product->discount_price) {
                    $hasVariablePrices = true;
                    break;
                }
            }
        }

        // تبدیل تنوع‌ها به JSON
        $variantsJson = $product->variants
            ? $product->variants
                ->map(function ($v) {
                    return [
                        'id' => $v->id,
                        'price' => $v->price,
                        'discount_price' => $v->discount_price,
                        'stock' => $v->stock,
                    ];
                })
                ->toJson()
            : '[]';

        // بررسی اینکه آیا این محصول در علاقه‌مندی‌های کاربر هست یا خیر
        // فرض بر این است که جدولی به نام Favorite یا relation در مدل User دارید
        $isFavorite = auth()->check()
            ? \App\Models\Favorite::where('bot_id', $bot->id)
                ->where('user_id', auth()->id())
                ->where('product_id', $product->id)
                ->exists()
        : false; @endphp

    <header
        class="fixed top-0 left-0 right-0 z-50 bg-[#121214]/60 backdrop-blur-xl border-b border-white/5 flex items-center px-4 h-14">
        <button onclick="history.back()"
            class="w-8 h-8 flex items-center justify-center bg-white/10 backdrop-blur-md border border-white/10 rounded-full text-white active:scale-90 transition-transform">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </button>
        <h1 class="flex-1 text-center font-bold text-gray-100 text-[14px] truncate px-4 drop-shadow-md">جزئیات کالا</h1>
        <div class="w-8"></div>
    </header>

    <!-- مدیریت State با Alpine.js -->
    <main class="relative bg-[#121214] min-h-screen" x-data="productStore({{ $product->price }}, {{ $product->discount_price ?? 'null' }}, {{ $product->stock ?? 0 }}, {{ $variantsJson }}, {{ $isFavorite ? 'true' : 'false' }})">

        <!-- ۱. عکس ثابت محصول با اسلایدر خودکار -->
        <div class="fixed top-0 left-0 right-0 w-full aspect-square bg-[#1E1E24] z-0">
            <div class="swiper productSwiper w-full h-full">
                <div class="swiper-wrapper">
                    @if ($product->images && $product->images->isNotEmpty())
                        @foreach ($product->images as $image)
                            <div class="swiper-slide w-full h-full">
                                <img src="{{ asset('storage/' . $image->file_id) }}" class="w-full h-full object-cover">
                            </div>
                        @endforeach
                    @else
                        <div class="swiper-slide w-full h-full flex items-center justify-center">
                            <span class="text-gray-500">بدون تصویر</span>
                        </div>
                    @endif
                </div>
                <div class="swiper-pagination"></div>
            </div>
            <div
                class="absolute bottom-0 left-0 right-0 h-20 bg-gradient-to-t from-[#121214] to-transparent z-10 pointer-events-none">
            </div>
        </div>

        <!-- ۲. محتوای اطلاعات کالا -->
        <div
            class="relative z-10 mt-[100vw] sm:mt-[32rem] bg-[#121214] rounded-t-3xl min-h-screen shadow-[0_-15px_30px_rgba(0,0,0,0.6)] border-t border-white/5 pb-[120px]">

            <div class="px-4 pt-8 pb-4">
                <div class="flex justify-between items-start gap-4">
                    <h1 class="text-white font-extrabold text-[18px] leading-[28px] flex-1 drop-shadow-sm">
                        {{ $product->name }}
                    </h1>

                    <!-- دکمه قلب (علاقه‌مندی‌ها) داینامیک -->
                    <button @click="toggleFavorite()"
                        :class="isFavorite ? 'text-red-500 bg-red-500/10 border-red-500/20' :
                            'text-gray-400 bg-[#1E1E24] border-white/5'"
                        class="w-10 h-10 shrink-0 rounded-full flex items-center justify-center active:scale-90 transition-all shadow-lg border">
                        <svg class="w-5 h-5 transition-colors" :fill="isFavorite ? 'currentColor' : 'none'"
                            stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                    </button>
                </div>

                <!-- قیمت کالا -->
                <div class="mt-5 h-[50px]">
                    <template x-if="currentDiscount">
                        <div>
                            <div class="text-gray-500 text-[12px] line-through mb-1"
                                x-text="formatPrice(currentPrice) + ' تومان'"></div>
                            <div class="text-primary font-black text-[22px] flex items-center gap-1">
                                <span x-text="formatPrice(currentDiscount)"></span> <span
                                    class="text-[12px] font-normal text-gray-400">تومان</span>
                            </div>
                        </div>
                    </template>
                    <template x-if="!currentDiscount">
                        <div class="text-primary font-black text-[22px] flex items-center gap-1 mt-3">
                            <span x-text="formatPrice(currentPrice)"></span> <span
                                class="text-[12px] font-normal text-gray-400">تومان</span>
                        </div>
                    </template>
                </div>
            </div>

            <hr class="border-white/5 mx-4">

            <!-- ۳. بخش تنوع‌ها (رنگ / مدل) و کنترل موجودی -->
            @if ($product->variants && $product->variants->isNotEmpty())
                <div class="px-4 py-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-white font-bold text-[14px]">انتخاب مدل / رنگ</h3>
                        <span class="text-red-400 text-[10px]" x-show="!selectedVariant">لطفا یک مورد را انتخاب
                            کنید</span>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        @foreach ($product->variants as $variant)
                            <button @click="selectedVariant = '{{ $variant->id }}'"
                                :disabled="{{ $variant->stock <= 0 ? 'true' : 'false' }}"
                                :class="{
                                    'opacity-40 grayscale cursor-not-allowed border-white/5 bg-[#121214] text-gray-500': {{ $variant->stock <= 0 ? 'true' : 'false' }},
                                    'border-primary bg-primary/10 text-primary shadow-[0_0_15px_rgba(var(--color-primary-rgb),0.3)]': selectedVariant === '{{ $variant->id }}',
                                    'border-white/10 bg-[#1E1E24] text-gray-300': selectedVariant !== '{{ $variant->id }}' &&
                                        {{ $variant->stock > 0 ? 'true' : 'false' }}
                                }"
                                class="px-4 py-2.5 rounded-xl border transition-all active:scale-95 flex flex-col items-center justify-center min-w-[70px]">

                                <span class="text-[12px] font-bold">{{ $variant->variant_name }}</span>
                                @if ($variant->stock <= 0)
                                    <span class="text-[9px] mt-1 text-red-400">ناموجود</span>
                                @elseif ($hasVariablePrices)
                                    <span
                                        class="text-[9px] mt-1 opacity-80">{{ number_format($variant->discount_price ?? $variant->price) }}
                                        تومان</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
                <hr class="border-white/5 mx-4">
            @endif

            <!-- ۴. توضیحات محصول -->
            <div class="px-4 py-6">
                <h3 class="text-white font-bold text-[14px] mb-3">درباره محصول</h3>
                <div class="text-gray-300 text-[13px] leading-[26px] font-light html-content">
                    {!! $product->description ?? 'توضیحاتی برای این کالا ثبت نشده است.' !!}
                </div>
            </div>

            <!-- ۵. محصولات مشابه -->
            @if (isset($similarProducts) && $similarProducts->count() > 0)
                <div class="pb-6">
                    <x-miniapp.product-slider title="محصولات مشابه" :products="$similarProducts" />
                </div>
            @endif
        </div>

        <!-- نوار چسبان پایین (Action Bar) -->
        <div
            class="fixed bottom-0 left-0 right-0 z-50 bg-[#121214]/95 backdrop-blur-xl border-t border-white/10 p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] flex gap-3 items-center shadow-[0_-10px_30px_rgba(0,0,0,0.8)]">

            <!-- دکمه‌های تعداد -->
            <div class="flex items-center bg-[#1E1E24] border border-white/10 rounded-2xl h-14 overflow-hidden"
                :class="currentStock <= 0 ? 'opacity-50 pointer-events-none' : ''">
                <button @click="if(quantity > 1) quantity--"
                    class="w-10 h-full flex items-center justify-center text-gray-400 hover:text-white active:bg-white/5">-</button>
                <span class="w-8 text-center text-white font-bold text-[14px]" x-text="quantity"></span>
                <button @click="if(quantity < currentStock) quantity++"
                    class="w-10 h-full flex items-center justify-center text-gray-400 hover:text-white active:bg-white/5">+</button>
            </div>

            <!-- دکمه ناموجود -->
            <template x-if="currentStock <= 0 && (!variants.length || selectedVariant)">
                <button disabled
                    class="flex-1 bg-gray-800 text-gray-400 border border-white/5 rounded-2xl h-14 px-3 flex items-center justify-center transition-all opacity-80 cursor-not-allowed">
                    <span class="font-bold text-[14px]">اتمام موجودی</span>
                </button>
            </template>

            <!-- دکمه افزودن به سبد -->
            <template x-if="currentStock > 0 || (variants.length > 0 && !selectedVariant)">
                <button @click="addToCart()"
                    :class="(!selectedVariant && variants.length > 0) ? 'opacity-50 grayscale' :
                    'shadow-lg shadow-primary/30 active:scale-95 hover:bg-primary-light'"
                    class="flex-1 bg-primary text-white rounded-2xl h-14 px-3 flex items-center justify-between transition-all">
                    <div class="flex flex-col items-start justify-center">
                        <span class="font-bold text-[13px]">افزودن به سبد</span>
                        <span class="text-[10px] text-white/80 font-medium"
                            x-text="formatPrice((currentDiscount || currentPrice) * quantity) + ' تومان'"></span>
                    </div>
                    <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                </button>
            </template>

        </div>
    </main>

    <style>
        .html-content p {
            margin-bottom: 0.75rem;
        }

        .html-content ul {
            list-style-type: disc;
            padding-right: 1.5rem;
            margin-bottom: 0.75rem;
        }

        .html-content ol {
            list-style-type: decimal;
            padding-right: 1.5rem;
            margin-bottom: 0.75rem;
        }

        .html-content strong,
        .html-content b {
            color: white;
            font-weight: 800;
        }

        .html-content a {
            color: var(--color-primary);
            text-decoration: underline;
        }

        .productSwiper .swiper-pagination-bullet {
            background: rgba(255, 255, 255, 0.5);
            transition: all 0.3s ease;
        }

        .productSwiper .swiper-pagination-bullet-active {
            background: var(--color-primary);
            width: 18px;
            border-radius: 6px;
        }
    </style>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('productStore', (basePrice, baseDiscount, baseStock, variants, initialFavorite) => ({
                selectedVariant: null,
                quantity: 1,
                variants: variants,
                isSubmitting: false,
                isFavorite: initialFavorite,

                formatPrice(price) {
                    if (!price) return 0;
                    return new Intl.NumberFormat('fa-IR').format(price);
                },

                get currentPrice() {
                    if (this.selectedVariant) {
                        let v = this.variants.find(x => x.id == this.selectedVariant);
                        return v ? v.price : basePrice;
                    }
                    return basePrice;
                },

                get currentDiscount() {
                    if (this.selectedVariant) {
                        let v = this.variants.find(x => x.id == this.selectedVariant);
                        return v ? v.discount_price : baseDiscount;
                    }
                    return baseDiscount;
                },

                // محاسبه موجودی لحظه‌ای بر اساس تنوع انتخاب شده
                get currentStock() {
                    if (this.variants.length > 0) {
                        if (this.selectedVariant) {
                            let v = this.variants.find(x => x.id == this.selectedVariant);
                            return v ? v.stock : 0;
                        }
                        return 1; // برای اینکه قبل از انتخاب تنوع، دکمه افزودن خاموش نشود
                    }
                    return baseStock;
                },

                // متد لایک کردن محصول
                async toggleFavorite() {
                    if (window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp
                        .HapticFeedback) {
                        window.Telegram.WebApp.HapticFeedback.impactOccurred('light');
                    }

                    // تغییر آنی در رابط کاربری (Optimistic UI)
                    this.isFavorite = !this.isFavorite;

                    try {
                        const botUsername = '{{ request()->route('bot_username') }}';
                        let response = await fetch(`/app/${botUsername}/favorites/toggle`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                product_id: {{ $product->id }}
                            })
                        });
                        let data = await response.json();

                        if (!data.success) {
                            this.isFavorite = !this.isFavorite; // برگرداندن به حالت قبل در صورت خطا
                            alert(data.message || 'خطایی رخ داد');
                        }
                    } catch (e) {
                        this.isFavorite = !this.isFavorite;
                    }
                },

                async addToCart() {
                    if (this.variants.length > 0 && !this.selectedVariant) {
                        alert('لطفا ابتدا مدل / رنگ مورد نظر را انتخاب کنید.');
                        return;
                    }

                    if (this.quantity > this.currentStock) {
                        alert('موجودی این محصول کافی نیست.');
                        return;
                    }

                    if (this.isSubmitting) return;
                    this.isSubmitting = true;

                    try {
                        const botUsername = '{{ request()->route('bot_username') }}';
                        let response = await fetch(`/app/${botUsername}/cart/add`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                product_id: {{ $product->id }},
                                variant_id: this.selectedVariant,
                                quantity: this.quantity
                            })
                        });

                        let data = await response.json();

                        if (data.success) {
                            if (window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp
                                .HapticFeedback) {
                                window.Telegram.WebApp.HapticFeedback.notificationOccurred(
                                    'success');
                            }
                            window.location.href = `/app/${botUsername}/cart`;
                        } else {
                            alert(data.message || 'خطا در افزودن به سبد خرید');
                        }
                    } catch (e) {
                        alert('خطای ارتباط با سرور');
                    }
                    this.isSubmitting = false;
                }
            }))
        })

        // راه‌اندازی Swiper با قابلیت اسلاید خودکار (Autoplay)
        document.addEventListener('DOMContentLoaded', function() {
            if (document.querySelector('.productSwiper')) {
                new Swiper('.productSwiper', {
                    pagination: {
                        el: '.swiper-pagination',
                        clickable: true
                    },
                    loop: true,
                    autoplay: {
                        delay: 3500, // هر ۳.۵ ثانیه عکس عوض می‌شود
                        disableOnInteraction: false,
                    },
                    effect: 'fade',
                    fadeEffect: {
                        crossFade: true
                    }
                });
            }
        });
    </script>
</x-miniapp.layouts.master>
