@props([
    'title' => 'محصولات', 
    'products' => collect()
])

<!-- کانتینر اصلی -->
<div class="px-4 mt-8" 
     x-data="{ 
        shown: false, 
        initGrid() {
            // حالا چون کارت‌ها از همان اول مخفی هستند، نیازی به setTimeout نداریم
            let observer = new IntersectionObserver((entries) => {
                if(entries[0].isIntersecting) {
                    this.shown = true;
                    observer.disconnect(); 
                }
            }, { rootMargin: '50px' }); 
            
            observer.observe(this.$el);
        }
     }" 
     x-init="initGrid()">

    <!-- هدر بخش گرید -->
    <!-- دقت کنید: opacity-0 و translate-y-4 در خود کلاس نوشته شده تا از لحظه اول مخفی باشد -->
    <div class="flex items-center justify-between mb-4 transition-all duration-700 transform ease-out opacity-0 translate-y-4"
         :class="shown ? '!opacity-100 !translate-y-0' : ''">
        <h3 class="text-white font-bold text-[16px] tracking-wide">{{ $title }}</h3>

        <!-- دکمه فیلتر -->
        <button class="text-gray-300 bg-white/5 border border-white/10 px-2.5 py-1.5 rounded-lg text-[10px] font-medium flex items-center gap-1 hover:text-white hover:bg-white/10 transition-colors active:scale-95">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
            </svg>
            فیلتر
        </button>
    </div>

    <!-- گرید ۲ ستونه محصولات -->
    <div class="grid grid-cols-2 gap-3">

        @foreach ($products as $product)
            @php
                $isFav = auth()->check() ? \App\Models\Favorite::where('bot_id', $product->bot_id)->where('user_id', auth()->id())->where('product_id', $product->id)->exists() : false;
            @endphp

            <!-- کارت محصول -->
            <!-- دقت کنید: opacity-0 و translate-y-12 در خود کلاس هاردکد شده‌اند -->
            <!-- با اضافه شدن !opacity-100 از طریق Alpine، استایل اولیه لغو شده و انیمیشن اجرا می‌شود -->
            <a href="/app/{{ request()->route('bot_username') }}/product/{{ $product->id }}"
                class="bg-[#1E1E24] border border-white/5 rounded-2xl overflow-hidden shadow-sm relative flex flex-col group block transition-all duration-700 ease-out transform opacity-0 translate-y-12"
                :class="shown ? '!opacity-100 !translate-y-0' : ''"
                style="transition-delay: {{ $loop->index * 150 }}ms;"> <!-- فاصله ۱۵۰ میلی‌ثانیه برای هر کارت -->
                
                <!-- دکمه علاقه‌مندی -->
                <div @click.prevent="toggleFav($event)" 
                     x-data="{
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
                                if(!data.success) this.isFav = !this.isFav;
                            } catch(e) { this.isFav = !this.isFav; }
                        }
                     }"
                     :class="isFav ? 'text-red-500 bg-red-500/10 border-red-500/20' : 'text-gray-400 bg-[#121214]/60 border-white/10 hover:text-red-500'"
                     class="absolute top-2 right-2 z-10 w-7 h-7 rounded-full backdrop-blur-md flex items-center justify-center transition-colors border pointer-events-auto cursor-pointer">
                    <svg class="w-4 h-4 transition-colors" :fill="isFav ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                </div>

                <!-- تصویر محصول -->
                <div class="w-full aspect-square bg-[#121214] relative overflow-hidden pointer-events-none">
                    @php
                        $imageUrl = 'https://placehold.co/400x400/2A2A35/FFFFFF?text=RoboShop';
                        if ($product->images && $product->images->isNotEmpty()) {
                            $firstImage = $product->images->first();
                            if (!empty($firstImage->file_id)) {
                                $imageUrl = asset('storage/' . $firstImage->file_id);
                            }
                        }
                    @endphp
                    <img src="{{ $imageUrl }}" alt="{{ $product->name }}" class="w-full h-full object-cover transition-transform duration-300 group-active:scale-105">
                </div>

                <!-- اطلاعات محصول -->
                <div class="p-3 flex flex-col flex-1 relative pb-10 pointer-events-none">

                    <h4 class="text-gray-200 text-[11px] font-bold leading-[18px] line-clamp-2 mb-1" title="{{ $product->name }}">
                        {{ $product->name }}
                    </h4>

                    <div class="mt-auto pt-2">
                        @if ($product->discount_price && $product->discount_price < $product->price)
                            <div class="text-gray-500 text-[9px] line-through mb-0.5">{{ number_format($product->price) }}</div>
                            <div class="text-primary font-extrabold text-[12px] flex items-center gap-1">
                                {{ number_format($product->discount_price) }} <span class="text-[8px] font-normal text-gray-400">تومان</span>
                            </div>
                        @else
                            <div class="text-primary font-extrabold text-[12px] flex items-center gap-1 mt-3">
                                {{ number_format($product->price) }} <span class="text-[8px] font-normal text-gray-400">تومان</span>
                            </div>
                        @endif
                    </div>

                </div>

                <!-- دکمه خرید سریع -->
                <div class="absolute bottom-2 left-2 w-8 h-8 bg-primary text-white rounded-xl flex items-center justify-center shadow-lg shadow-primary/30 active:scale-90 transition-transform z-10 pointer-events-auto cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                </div>

            </a>
        @endforeach

    </div>
</div>