@props(['title', 'products'])

<div class="px-4 mt-8">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-white font-bold text-[16px] tracking-wide">{{ $title }}</h3>
    </div>

    <div class="flex overflow-x-auto gap-4 pb-4 hide-scrollbar snap-x snap-mandatory">
        @foreach ($products as $product)
            @php
                // بررسی وضعیت علاقه‌مندی برای هر محصول به صورت مجزا
                $isFav = auth()->check()
                    ? \App\Models\Favorite::where('bot_id', $product->bot_id)
                        ->where('user_id', auth()->id())
                        ->where('product_id', $product->id)
                        ->exists()
                    : false;
            @endphp

            <div class="w-[150px] shrink-0 snap-start bg-[#1E1E24] border border-white/5 rounded-2xl overflow-hidden shadow-lg relative flex flex-col group"
                x-data="{
                    isFav: {{ $isFav ? 'true' : 'false' }},
                    async toggleFav(event) {
                        event.preventDefault(); // جلوگیری از هدایت به صفحه محصول هنگام کلیک روی قلب
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
                }">

                <!-- لینک نامرئی کلیک روی کل کارت -->
                <a href="/app/{{ request()->route('bot_username') }}/product/{{ $product->id }}"
                    class="absolute inset-0 z-0"></a>

                <!-- دکمه علاقه‌مندی (قلب شناور) -->
                <button @click="toggleFav($event)"
                    :class="isFav ? 'text-red-500 bg-red-500/10 border-red-500/20' :
                        'text-gray-400 bg-[#121214]/60 border-white/10'"
                    class="absolute top-2 right-2 z-10 w-7 h-7 rounded-full backdrop-blur-md flex items-center justify-center transition-colors border pointer-events-auto">
                    <svg class="w-4 h-4 transition-colors" :fill="isFav ? 'currentColor' : 'none'" stroke="currentColor"
                        stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                </button>

                <!-- تصویر محصول -->
                <div class="w-full h-[140px] bg-[#121214] relative overflow-hidden">
                    @php
                        $imageUrl = 'https://placehold.co/400x400/2A2A35/FFFFFF?text=RoboShop';
                        if ($product->images && $product->images->isNotEmpty()) {
                            $firstImage = $product->images->first();
                            if (!empty($firstImage->file_id)) {
                                $imageUrl = asset('storage/' . $firstImage->file_id);
                            }
                        }
                    @endphp
                    <img src="{{ $imageUrl }}" alt="{{ $product->name }}"
                        class="w-full h-full object-cover transition-transform duration-300 group-active:scale-105 pointer-events-none">
                </div>

                <!-- اطلاعات محصول -->
                <div class="p-3 flex flex-col flex-1 pointer-events-none">
                    <h4 class="text-gray-200 text-[11px] font-bold leading-[16px] line-clamp-2 mb-2">
                        {{ $product->name }}
                    </h4>

                    <div class="mt-auto">
                        @if ($product->discount_price && $product->discount_price < $product->price)
                            <div class="text-gray-500 text-[10px] line-through mb-0.5">
                                {{ number_format($product->price) }}</div>
                            <div class="text-primary font-extrabold text-[13px] flex items-center gap-1">
                                {{ number_format($product->discount_price) }} <span
                                    class="text-[9px] font-normal text-gray-400">تومان</span>
                            </div>
                        @else
                            <div class="text-primary font-extrabold text-[13px] flex items-center gap-1 mt-3">
                                {{ number_format($product->price) }} <span
                                    class="text-[9px] font-normal text-gray-400">تومان</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- تغییر دکمه به مشاهده -->
                <div class="px-3 pb-3 relative z-10 pointer-events-auto">
                    <a href="/app/{{ request()->route('bot_username') }}/product/{{ $product->id }}"
                        class="w-full mt-3 bg-white/5 hover:bg-white/10 text-gray-300 border border-white/5 py-1.5 rounded-xl text-[11px] font-bold flex items-center justify-center gap-1.5 transition-colors active:scale-95">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        مشاهده
                    </a>
                </div>
            </div>
        @endforeach
    </div>
</div>
