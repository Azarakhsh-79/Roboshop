@props(['categories'])

<div class="px-4 mt-6">
    <!-- هدر بخش دسته‌بندی -->
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-white font-bold text-[15px] tracking-wide">دسته‌بندی‌ها</h3>
        <button class="text-primary text-[11px] font-medium hover:text-primary-light transition-colors">مشاهده
            همه</button>
    </div>

    <!-- اسکرول افقی دسته‌ها -->
    <div class="flex overflow-x-auto gap-4 pb-2 hide-scrollbar snap-x snap-mandatory">

        @foreach ($categories as $category)
            <!-- اضافه شدن w-[72px] و shrink-0 برای جلوگیری از به هم ریختگی -->
            
            <a href="/app/{{ request()->route('bot_username') }}/category/{{ $category->id }}" class="flex flex-col items-center gap-2 w-[72px] shrink-0 snap-center group block">
                <!-- کادر آیکون/تصویر -->
                <div
                    class="w-[60px] h-[60px] rounded-2xl bg-[#1E1E24] border border-white/5 flex items-center justify-center shadow-sm group-active:scale-95 transition-transform duration-200 overflow-hidden relative">
                    @php
                        $hasImage = false;
                    @endphp

                    @if ($hasImage)
                        <img src="{{ asset('storage/' . $category->image) }}" class="w-full h-full object-cover">
                    @else
                        <div class="absolute inset-0 bg-gradient-to-br from-gray-700 to-gray-800 opacity-50"></div>
                        <span class="text-2xl relative z-10 drop-shadow-md text-gray-300">
                            {{ mb_substr($category->name, 0, 1) }}
                        </span>
                    @endif
                </div>

                <!-- نام دسته‌بندی (اصلاح شده) -->
                <!-- حذف whitespace-nowrap و اضافه شدن خط‌شکن (line-clamp-2) -->
                <span class="text-gray-300 text-[10px] font-medium text-center w-full leading-[14px] line-clamp-2 px-1"
                    title="{{ $category->name }}">
                    {{ $category->name }}
                </span>

            </a>
        @endforeach

    </div>
</div>

<style>
    /* مخفی کردن اسکرول‌بار زشت مرورگر اما حفظ قابلیت اسکرول */
    .hide-scrollbar::-webkit-scrollbar {
        display: none;
    }

    .hide-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
</style>
