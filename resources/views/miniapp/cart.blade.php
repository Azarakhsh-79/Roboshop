<x-miniapp.layouts.master :bot="$bot" :show-bottom-nav="true">

    <!-- هدر چسبان -->
    <header class="sticky top-0 z-40 bg-[#121214]/90 backdrop-blur-xl border-b border-white/5 flex items-center px-4 h-14">
        <button onclick="history.back()" class="w-8 h-8 flex items-center justify-center bg-white/5 border border-white/10 rounded-full text-white active:scale-90 transition-transform">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </button>
        <h1 class="flex-1 text-center font-bold text-gray-100 text-[15px] truncate px-4">سبد خرید</h1>
        <button class="w-8 h-8 flex items-center justify-center text-red-400 bg-red-500/10 rounded-full active:scale-90 transition-transform" @click="clearCart()">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        </button>
    </header>

    <!-- مدیریت State با Alpine.js (تزریق کد تخفیف اعمال شده به جاوااسکریپت) -->
    <main class="pb-[180px]" x-data="cartManager('{{ $bot->bot_username }}', '{{ $appliedCode ?? '' }}')">
        
        @if(isset($cartItems) && $cartItems->isNotEmpty())
            
            <!-- لیست محصولات -->
            <div class="px-4 mt-4 space-y-3">
                @foreach($cartItems as $item)
                    <div class="flex gap-3 bg-[#1E1E24] p-3 rounded-2xl border border-white/5 relative overflow-hidden">
                        
                        <!-- تصویر محصول -->
                        <a href="/app/{{ request()->route('bot_username') }}/product/{{ $item->product_id }}" class="w-[85px] h-[85px] bg-[#121214] rounded-xl overflow-hidden shrink-0 border border-white/5">
                            @php
                                $imageUrl = 'https://placehold.co/200x200/2A2A35/FFFFFF?text=RoboShop';
                                if ($item->product && $item->product->images->isNotEmpty()) {
                                    $imageUrl = asset('storage/' . $item->product->images->first()->file_id);
                                }
                            @endphp
                            <img src="{{ $imageUrl }}" class="w-full h-full object-cover">
                        </a>

                        <!-- اطلاعات و کنترلرها -->
                        <div class="flex flex-col flex-1 py-1">
                            
                            <div class="flex justify-between items-start gap-2">
                                <a href="/app/{{ request()->route('bot_username') }}/product/{{ $item->product_id }}" class="text-white text-[12px] font-bold leading-[18px] line-clamp-2 flex-1">
                                    {{ $item->product->name }}
                                </a>
                                <!-- دکمه حذف تکی -->
                                <button @click="removeItem({{ $item->id }})" class="text-gray-500 hover:text-red-400 p-1 shrink-0 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            @if($item->variant)
                                <span class="text-gray-400 text-[10px] mt-1 flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full bg-primary/50"></span>
                                    مدل: {{ $item->variant->variant_name }}
                                </span>
                            @endif

                            <div class="flex justify-between items-end mt-auto pt-2">
                                <!-- قیمت -->
                                <div>
                                    @php
                                        // محاسبه قیمت نهایی آیتم (با در نظر گرفتن تخفیف)
                                        $basePrice = $item->variant ? $item->variant->price : $item->product->price;
                                        $discount = $item->variant ? $item->variant->discount_price : $item->product->discount_price;
                                        $finalPrice = ($discount > 0) ? $discount : $basePrice;
                                    @endphp
                                    <div class="text-primary font-black text-[13px]">{{ number_format($finalPrice) }} <span class="text-[9px] font-normal text-gray-500">تومان</span></div>
                                </div>

                                <!-- کنترلر تعداد -->
                                <div class="flex items-center bg-[#121214] border border-white/5 rounded-xl h-8 overflow-hidden">
                                    <button @click="updateQty({{ $item->id }}, {{ $item->quantity - 1 }})" class="w-8 h-full flex items-center justify-center text-gray-400 active:bg-white/5 transition-colors">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"/></svg>
                                    </button>
                                    <span class="w-6 text-center text-white font-bold text-[12px]">{{ $item->quantity }}</span>
                                    <button @click="updateQty({{ $item->id }}, {{ $item->quantity + 1 }})" class="w-8 h-full flex items-center justify-center text-gray-400 active:bg-white/5 transition-colors">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>
                @endforeach
            </div>

            <!-- کد تخفیف -->
            <div class="px-4 mt-4">
                <div class="bg-[#1E1E24] p-1.5 rounded-2xl border border-white/5 flex items-center h-14">
                    <input type="text" x-model="discountCode" placeholder="کد تخفیف دارید؟" class="flex-1 bg-transparent border-none text-white text-[12px] px-3 focus:outline-none focus:ring-0 placeholder-gray-500 dir-ltr text-right">
                    <button @click="applyDiscount" class="bg-[#121214] text-gray-300 hover:text-white px-4 h-full rounded-xl text-[11px] font-bold border border-white/5 transition-colors active:scale-95">اعمال کد</button>
                </div>
                <p x-show="discountMsg" x-text="discountMsg" :class="discountSuccess ? 'text-green-400' : 'text-red-400'" class="text-[10px] mt-2 px-2"></p>
            </div>

            <!-- فاکتور خلاصه -->
            <div class="px-4 mt-6">
                <h3 class="text-white font-bold text-[14px] mb-3 px-1">خلاصه سفارش</h3>
                <div class="bg-[#1E1E24] p-4 rounded-3xl border border-white/5 space-y-3">
                    
                    <div class="flex justify-between items-center text-[12px]">
                        <span class="text-gray-400">جمع مبلغ کالاها</span>
                        <span class="text-white font-medium">{{ number_format($totalOriginal ?? 0) }} تومان</span>
                    </div>

                    <div class="flex justify-between items-center text-[12px]">
                        <span class="text-gray-400">هزینه ارسال</span>
                        <span class="text-white font-medium">{{ isset($shippingCost) && $shippingCost > 0 ? number_format($shippingCost) . ' تومان' : 'وابسته به آدرس' }}</span>
                    </div>

                    <!-- نمایش کد تخفیف اعمال شده -->
                    @if(isset($appliedCode) && $appliedCode)
                        <div class="flex justify-between items-center text-[12px]">
                            <span class="text-gray-400">کد تخفیف (<span class="text-primary dir-ltr">{{ $appliedCode }}</span>)</span>
                            <span class="text-green-400 font-medium">{{ number_format($couponDiscountAmount ?? 0) }} تومان</span>
                        </div>
                    @endif

                    <!-- نمایش سود کاربر (سبز رنگ و متمایز) -->
                    @if(isset($totalDiscount) && $totalDiscount > 0)
                        <div class="flex justify-between items-center text-[12px] bg-green-500/10 px-3 py-2.5 rounded-xl border border-green-500/20 mt-2">
                            <span class="text-green-400 font-bold">سود شما از خرید</span>
                            <span class="text-green-400 font-black">{{ number_format($totalDiscount) }} تومان</span>
                        </div>
                    @endif

                    <hr class="border-white/5 border-dashed my-3">

                    <div class="flex justify-between items-center">
                        <span class="text-gray-300 font-bold text-[13px]">مبلغ قابل پرداخت</span>
                        <span class="text-primary font-black text-[16px]">{{ number_format($finalAmount ?? 0) }} <span class="text-[10px] text-gray-500 font-normal">تومان</span></span>
                    </div>
                </div>
            </div>

            <!-- نوار چسبان تسویه حساب -->
            <div class="fixed bottom-[85px] left-4 right-4 z-40">
                <a href="/app/{{ request()->route('bot_username') }}/checkout" class="w-full bg-primary text-white rounded-2xl h-[54px] flex items-center justify-between px-5 shadow-lg shadow-primary/30 active:scale-[0.98] transition-all">
                    <span class="font-bold text-[14px]">تکمیل خرید و پرداخت</span>
                    <div class="flex items-center gap-2">
                        <span class="font-black text-[15px]">{{ number_format($finalAmount ?? 0) }} <span class="text-[10px] font-normal opacity-80">تومان</span></span>
                        <svg class="w-4 h-4 opacity-80" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </div>
                </a>
            </div>

        @else
            <!-- حالت سبد خرید خالی -->
            <div class="flex flex-col items-center justify-center pt-24 px-6 text-center">
                <div class="w-24 h-24 bg-white/5 rounded-full flex items-center justify-center mb-6 border border-white/5">
                    <svg class="w-10 h-10 text-gray-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/></svg>
                </div>
                <h2 class="text-white font-bold text-[16px] mb-2">سبد خرید شما خالی است!</h2>
                <p class="text-gray-400 text-[12px] leading-[22px] mb-8">هنوز هیچ محصولی به سبد خرید خود اضافه نکرده‌اید. برای شروع خرید به فروشگاه برگردید.</p>
                <a href="/app/{{ request()->route('bot_username') }}" class="bg-primary/20 text-primary border border-primary/30 font-bold px-6 py-3 rounded-xl text-[13px] active:scale-95 transition-transform">
                    مشاهده محصولات فروشگاه
                </a>
            </div>
        @endif

    </main>

    <!-- توابع ارتباط با سرور برای سبد خرید -->
    <script>
        document.addEventListener('alpine:init', () => {
            // دریافت مقدار اولیه کد تخفیف از Blade
            Alpine.data('cartManager', (botUsername, initialDiscount) => ({
                discountCode: initialDiscount || '',
                discountMsg: '',
                discountSuccess: false,
                isUpdating: false,

                // متد ارسال درخواست به بک‌اند
                async apiCall(endpoint, payload) {
                    if(this.isUpdating) return;
                    this.isUpdating = true;
                    try {
                        let response = await fetch(`/app/${botUsername}/${endpoint}`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                            body: JSON.stringify(payload)
                        });
                        let data = await response.json();
                        if(data.success) {
                            window.location.reload(); // بعد از موفقیت، صفحه رفرش می‌شود تا فاکتور جدید محاسبه شود
                        } else {
                            alert(data.message || 'خطایی رخ داد');
                            this.isUpdating = false;
                        }
                    } catch (e) {
                        alert('خطای ارتباط با سرور');
                        this.isUpdating = false;
                    }
                },

                updateQty(cartId, newQty) {
                    if (newQty < 1) {
                        this.removeItem(cartId);
                        return;
                    }
                    
                    // افزودن بازخورد لمسی (لرزش) تلگرام
                    if(window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.HapticFeedback) {
                        window.Telegram.WebApp.HapticFeedback.selectionChanged();
                    }
                    
                    this.apiCall('cart/update', { cart_id: cartId, quantity: newQty });
                },

                removeItem(cartId) {
                    if(confirm('آیا از حذف این کالا مطمئن هستید؟')) {
                        this.apiCall('cart/remove', { cart_id: cartId });
                    }
                },

                clearCart() {
                    if(confirm('سبد خرید به طور کامل خالی شود؟')) {
                        this.apiCall('cart/clear', {});
                    }
                },

                async applyDiscount() {
                    if(!this.discountCode) return;
                    this.discountMsg = 'در حال بررسی...';
                    this.discountSuccess = false;
                    
                    if(window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.HapticFeedback) {
                        window.Telegram.WebApp.HapticFeedback.impactOccurred('light');
                    }
                    
                    try {
                        let response = await fetch(`/app/${botUsername}/cart/discount`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                            body: JSON.stringify({ code: this.discountCode })
                        });
                        let data = await response.json();
                        this.discountMsg = data.message;
                        this.discountSuccess = data.success;
                        
                        if(data.success) {
                            if(window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.HapticFeedback) {
                                window.Telegram.WebApp.HapticFeedback.notificationOccurred('success');
                            }
                            setTimeout(() => window.location.reload(), 1000);
                        } else {
                            if(window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.HapticFeedback) {
                                window.Telegram.WebApp.HapticFeedback.notificationOccurred('error');
                            }
                        }
                    } catch (e) {
                        this.discountMsg = 'خطای ارتباط با سرور';
                    }
                }
            }))
        })
    </script>
</x-miniapp.layouts.master>