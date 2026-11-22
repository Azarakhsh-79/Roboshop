@props(['title', 'products'])

<div class="px-4 mt-8">
    <!-- باکس اصلی با تم متمایز -->
    <div
        class="bg-gradient-to-br from-[#ef4444]/20 to-[#1E1E24] border border-red-500/20 rounded-3xl p-4 relative overflow-hidden shadow-[0_0_20px_rgba(239,68,68,0.05)]">

        <!-- هاله نور تزئینی پس‌زمینه -->
        <div class="absolute -top-10 -right-10 w-32 h-32 bg-red-500/20 rounded-full blur-3xl pointer-events-none"></div>

        <!-- هدر بخش تخفیف‌دارها -->
        <div class="flex items-center justify-between mb-4 relative z-10">
            <div class="flex items-center gap-1.5">
                <span class="text-xl">🔥</span>
                <h3 class="text-white font-extrabold text-[16px] tracking-wide">{{ $title }}</h3>
            </div>
            <!-- تبدیل button به a و ارجاع به صفحه discounts -->
            <a href="/app/{{ request()->route('bot_username') }}/discounts"
                class="text-red-400 text-[11px] font-medium hover:text-red-300 flex items-center gap-1 active:scale-95 transition-transform">
                مشاهده همه
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
        </div>

        <!-- اسکرول افقی -->
        <div
            class="flex flex-row flex-nowrap overflow-x-auto gap-3 pb-2 hide-scrollbar snap-x snap-mandatory relative z-10">

            @foreach ($products as $product)
                <!-- تبدیل div اصلی کارت به a (برای کلیک شدن کل کارت) -->
                <a href="/app/{{ request()->route('bot_username') }}/product/{{ $product->id }}"
                    class="w-[140px] shrink-0 snap-start bg-[#121214]/80 backdrop-blur-sm border border-red-500/10 rounded-2xl overflow-hidden relative flex flex-col group block">

                    @php
                        // محاسبه درصد تخفیف (علامت منفی اصلاح شد)
                        $discountPercent = 0;
                        if ($product->price > 0 && $product->discount_price > 0) {
                            $discountPercent = round(
                                (($product->price - $product->discount_price) / $product->price) * 100,
                            );
                        }
                    @endphp

                    <!-- بج درصد تخفیف داینامیک -->
                    @if ($discountPercent > 0)
                        <div
                            class="absolute top-0 left-0 bg-red-500 text-white text-[11px] font-black px-2 py-1 rounded-br-xl z-10 shadow-md shadow-red-500/30 pointer-events-none">
                            ٪{{ $discountPercent }}
                        </div>
                    @endif

                    <!-- تصویر محصول -->
                    <div class="w-full h-[130px] relative overflow-hidden p-2 pointer-events-none">
                        @php
                            $imageUrl = 'https://placehold.co/400x400/2A2A35/FFFFFF?text=Sale';
                            if ($product->images && $product->images->isNotEmpty()) {
                                $firstImage = $product->images->first();
                                if (!empty($firstImage->file_id)) {
                                    $imageUrl = asset('storage/' . $firstImage->file_id);
                                }
                            }
                        @endphp
                        <img src="{{ $imageUrl }}" alt="{{ $product->name }}"
                            class="w-full h-full object-cover rounded-xl transition-transform duration-300 group-active:scale-105">
                    </div>

                    <!-- اطلاعات محصول -->
                    <div class="p-3 flex flex-col flex-1 pointer-events-none">
                        <h4 class="text-gray-200 text-[11px] font-bold leading-[18px] line-clamp-2 mb-2"
                            title="{{ $product->name }}">
                            {{ $product->name }}
                        </h4>

                        <div class="mt-auto pt-1 border-t border-white/5">
                            <!-- قیمت اصلی (خط‌خورده) -->
                            <div class="text-gray-500 text-[10px] line-through mb-0.5">
                                {{ number_format($product->price) }}
                            </div>
                            <!-- قیمت با تخفیف -->
                            <div class="text-red-400 font-extrabold text-[13px] flex items-center gap-1">
                                {{ number_format($product->discount_price) }} <span
                                    class="text-[9px] font-normal text-gray-400">تومان</span>
                            </div>
                        </div>
                    </div>
                </a>
            @endforeach

        </div>
    </div>
</div>
