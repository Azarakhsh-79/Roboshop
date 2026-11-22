<x-miniapp.layouts.master :bot="$bot" :show-bottom-nav="true">

    <!-- هدر اختصاصی با تم قرمز -->
    <header
        class="sticky top-0 z-50 bg-[#1a0f14]/90 backdrop-blur-xl border-b border-red-500/20 flex items-center px-4 h-14 shadow-[0_4px_20px_rgba(239,68,68,0.1)]">
        <button onclick="history.back()"
            class="w-8 h-8 flex items-center justify-center bg-red-500/10 border border-red-500/20 rounded-full text-red-400 active:scale-90 transition-transform">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </button>
        <h1
            class="flex-1 text-center font-black text-red-500 text-[16px] truncate px-4 drop-shadow-[0_0_8px_rgba(239,68,68,0.5)]">
            🔥 پیشنهادهای شگفت‌انگیز
        </h1>
        <div class="w-8"></div>
    </header>

    <main
        class="min-h-screen bg-gradient-to-b from-[#1a0f14] via-[#121214] to-[#121214] pb-28 pt-6 px-4 relative overflow-hidden">

        <!-- افکت‌های نوری پس‌زمینه -->
        <div class="absolute top-0 right-0 w-64 h-64 bg-red-600/10 rounded-full blur-[100px] pointer-events-none"></div>
        <div class="absolute top-1/3 left-0 w-48 h-48 bg-orange-600/10 rounded-full blur-[80px] pointer-events-none">
        </div>

        @if ($discountedProducts->isNotEmpty())
            <!-- کانتینر گرید با اضافه شدن Alpine.js برای تشخیص اسکرول و نمایش آبشاری -->
            <div class="grid grid-cols-2 gap-3 relative z-10" x-data="{
                shown: false,
                initGrid() {
                    let observer = new IntersectionObserver((entries) => {
                        if (entries[0].isIntersecting) {
                            this.shown = true;
                            observer.disconnect();
                        }
                    }, { rootMargin: '50px' });
                    observer.observe(this.$el);
                }
            }" x-init="initGrid()">

                @foreach ($discountedProducts as $product)
                    @php
                        // اضافه شدن شرط امنیتی: جلوگیری از خطای تقسیم بر صفر
                        $discountPercent =
                            $product->price > 0
                                ? round((($product->price - $product->discount_price) / $product->price) * 100)
                                : 0;

                        // بررسی وضعیت علاقه‌مندی
                        $isFav = auth()->check()
                            ? \App\Models\Favorite::where('bot_id', $bot->id)
                                ->where('user_id', auth()->id())
                                ->where('product_id', $product->id)
                                ->exists()
                            : false;
                    @endphp

                    <!-- کارت محصول با تم قرمز و افکت آبشاری (هاردکد شدن کلاس‌های مخفی) -->
                    <div class="bg-gradient-to-b from-[#1E1E24] to-[#16161A] border border-red-500/20 rounded-2xl overflow-hidden shadow-[0_4px_15px_rgba(239,68,68,0.05)] relative flex flex-col group transition-all duration-700 ease-out transform opacity-0 translate-y-12"
                        :class="shown ? '!opacity-100 !translate-y-0' : ''"
                        style="transition-delay: {{ $loop->index * 150 }}ms;">

                        <!-- لینک روی کل کارت -->
                        <a href="/app/{{ request()->route('bot_username') }}/product/{{ $product->id }}"
                            class="absolute inset-0 z-0"></a>

                        <!-- بج درصد تخفیف -->
                        <div
                            class="absolute top-0 right-0 bg-red-500 text-white text-[12px] font-black px-2.5 py-1 rounded-bl-xl z-10 shadow-md shadow-red-500/30">
                            ٪{{ $discountPercent }}
                        </div>

                        <!-- دکمه علاقه‌مندی (تغییر تگ به div برای جلوگیری از تداخل HTML) -->
                        <div @click.prevent="toggleFav($event)" x-data="{
                            isFav: {{ $isFav ? 'true' : 'false' }},
                            async toggleFav(event) {
                                if (window.Telegram && window.Telegram.WebApp && window.Telegram.WebApp.HapticFeedback) {
                                    window.Telegram.WebApp.HapticFeedback.impactOccurred('light');
                                }
                                this.isFav = !this.isFav;
                                try {
                                    const botUsername = '{{ request()->route('bot_username') }}';
                                    let res = await fetch(`/app/${botUsername}/favorites/toggle`, {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                        body: JSON.stringify({ product_id: {{ $product->id }} })
                                    });
                                    let data = await res.json();
                                    if (!data.success) this.isFav = !this.isFav;
                                } catch (e) { this.isFav = !this.isFav; }
                            }
                        }"
                            :class="isFav ? 'text-red-500 bg-red-500/10 border-red-500/30' :
                                'text-gray-300 bg-[#121214]/60 border-white/10 hover:text-red-500'"
                            class="absolute top-2 left-2 z-10 w-7 h-7 rounded-full backdrop-blur-md flex items-center justify-center transition-colors border pointer-events-auto cursor-pointer">
                            <svg class="w-4 h-4 transition-colors" :fill="isFav ? 'currentColor' : 'none'"
                                stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                        </div>

                        <!-- تصویر -->
                        <div
                            class="w-full aspect-square relative overflow-hidden bg-[#121214] p-1.5 pointer-events-none">
                            @php
                                $imageUrl = 'https://placehold.co/400x400/2A2A35/FFFFFF?text=Sale';
                                if ($product->images && $product->images->isNotEmpty()) {
                                    $imageUrl = asset('storage/' . $product->images->first()->file_id);
                                }
                            @endphp
                            <img src="{{ $imageUrl }}"
                                class="w-full h-full object-cover rounded-xl transition-transform duration-300 group-active:scale-105">
                        </div>

                        <!-- اطلاعات -->
                        <div class="p-3 flex flex-col flex-1 pointer-events-none">
                            <h4 class="text-gray-200 text-[11px] font-bold leading-[18px] line-clamp-2 mb-2">
                                {{ $product->name }}
                            </h4>

                            <div class="mt-auto pt-2 border-t border-red-500/10">
                                <div class="text-gray-500 text-[10px] line-through mb-0.5">
                                    {{ number_format($product->price) }}</div>
                                <div
                                    class="text-red-500 font-black text-[14px] flex items-center gap-1 drop-shadow-[0_0_5px_rgba(239,68,68,0.3)]">
                                    {{ number_format($product->discount_price) }} <span
                                        class="text-[9px] font-normal text-gray-400">تومان</span>
                                </div>
                            </div>
                        </div>

                        <!-- دکمه خرید سریع -->
                        <div
                            class="absolute bottom-2 left-2 w-8 h-8 bg-primary text-white rounded-xl flex items-center justify-center shadow-lg shadow-primary/30 active:scale-90 transition-transform z-10 pointer-events-auto cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <!-- حالت خالی -->
            <div class="flex flex-col items-center justify-center pt-32 opacity-50 relative z-10">
                <svg class="w-16 h-16 text-gray-500 mb-4" fill="none" stroke="currentColor" stroke-width="1.5"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <p class="text-gray-400 font-medium text-[13px]">در حال حاضر هیچ پیشنهاد شگفت‌انگیزی وجود ندارد.</p>
            </div>
        @endif

    </main>

</x-miniapp.layouts.master>
