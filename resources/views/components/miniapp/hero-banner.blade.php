@props(['products'])

<div class="px-4 mt-2">
    <!-- کانتینر اصلی Swiper -->
    <div
        class="swiper heroSwiper w-full h-[180px] rounded-2xl overflow-hidden shadow-lg border border-white/5 relative bg-[#1E1E24]">

        <div class="swiper-wrapper">
            @foreach ($products as $product)
                <!-- تبدیل div به a برای قابل کلیک شدن کل اسلاید -->
                <a href="/app/{{ request()->route('bot_username') }}/product/{{ $product->id }}" class="swiper-slide relative w-full h-full block group">

                    <!-- تصویر محصول -->
                    @php
                        $imageUrl = 'https://placehold.co/600x400/2A2A35/FFFFFF?text=RoboShop';

                        if ($product->images && $product->images->isNotEmpty()) {
                            $firstImage = $product->images->first();

                            if (!empty($firstImage->file_id)) {
                                $imageUrl = asset('storage/' . $firstImage->file_id);
                            }
                        }
                    @endphp

                    <img src="{{ $imageUrl }}" alt="{{ $product->name }}" class="w-full h-full object-cover transition-transform duration-500 group-active:scale-105">

                    <!-- گرادیانت روی عکس -->
                    <div class="absolute inset-0 bg-gradient-to-t from-[#121214] via-[#121214]/50 to-transparent pointer-events-none"></div>

                    <!-- اطلاعات محصول -->
                    <div class="absolute bottom-0 left-0 right-0 p-4 flex justify-between items-end pointer-events-none">
                        <div class="flex-1 pr-2">
                            <span class="inline-block bg-primary/20 text-primary text-[9px] font-bold px-2 py-0.5 rounded-full mb-1 backdrop-blur-sm border border-primary/30">
                                🔥 پرفروش‌ترین
                            </span>
                            <h2 class="text-white font-bold text-[14px] truncate">{{ $product->name }}</h2>
                            <p class="text-gray-300 font-medium text-[12px] mt-1">{{ number_format($product->price) }} تومان</p>
                        </div>

                        <!-- دکمه که حالا زیرمجموعه لینک اصلی است -->
                        <div class="bg-primary text-white text-[10px] font-bold px-4 py-2 rounded-xl shadow-lg shadow-primary/30 active:scale-95 transition-transform shrink-0">
                            مشاهده کالا
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="swiper-pagination"></div>
    </div>
</div>

<style>
    .heroSwiper .swiper-pagination-bullet {
        background-color: rgba(255, 255, 255, 0.4);
        width: 6px;
        height: 6px;
        transition: all 0.3s ease;
    }

    .heroSwiper .swiper-pagination-bullet-active {
        background-color: var(--color-primary);
        width: 16px;
        border-radius: 4px;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const swiper = new Swiper('.heroSwiper', {
            slidesPerView: 1,
            spaceBetween: 10,
            loop: true,
            autoplay: {
                delay: 3500,
                disableOnInteraction: false,
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            effect: 'fade',
            fadeEffect: {
                crossFade: true
            },
        });
    });
</script>