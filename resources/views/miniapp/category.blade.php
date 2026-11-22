<x-miniapp.layouts.master :bot="$bot" :show-bottom-nav="true">

    <!-- هدر چسبان صفحه دسته‌بندی (z-40) -->
    <header class="sticky top-0 z-40 bg-[#121214]/90 backdrop-blur-xl border-b border-white/5 flex items-center px-4 h-14">
        <button onclick="history.back()" class="w-8 h-8 flex items-center justify-center bg-white/5 border border-white/10 rounded-full text-white active:scale-90 transition-transform">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </button>
        <h1 class="flex-1 text-center font-bold text-gray-100 text-[15px] truncate px-4">
            {{ $category->name }}
        </h1>
        <div class="w-8"></div>
    </header>

    <!-- اضافه کردن x-data برای تشخیص اسکرول -->
    <main class="pb-28" x-data="scrollAccordion()">
        
        <!-- ردیف اول: اسکرول افقی تمام دسته‌های اصلی (z-30) -->
        @if(isset($mainCategories) && $mainCategories->isNotEmpty())
            <div class="py-3 bg-[#121214] border-b border-white/5 sticky top-14 z-30 shadow-lg transition-transform duration-500 ease-in-out"
                 :class="isScrollingDown ? '-translate-y-[120px] delay-100' : 'translate-y-0 delay-0'">
                
                <div class="flex overflow-x-auto gap-3 hide-scrollbar snap-x px-4" id="main-categories-container">
                    @foreach($mainCategories as $mainCat)
                        @php
                            $isMainActive = $category->id == $mainCat->id;
                        @endphp
                        
                        <a href="/app/{{ request()->route('bot_username') }}/category/{{ $mainCat->id }}" 
                           @if($isMainActive) id="active-main-category" @endif
                           class="flex items-center gap-2 px-3.5 py-2 rounded-2xl border transition-all shrink-0 snap-center active:scale-95
                           {{ $isMainActive ? 'bg-primary/20 border-primary text-white shadow-[0_0_15px_rgba(var(--color-primary-rgb),0.2)]' : 'bg-[#1E1E24] border-white/5 text-gray-400 hover:text-gray-200' }}">
                            
                            <div class="w-6 h-6 rounded-lg bg-black/40 flex items-center justify-center text-[11px] font-bold text-primary">
                                {{ mb_substr($mainCat->name, 0, 1) }}
                            </div>
                            <span class="text-[12px] font-bold whitespace-nowrap">{{ $mainCat->name }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- ردیف دوم: اسکرول افقی زیردسته‌ها (z-20) -->
        @if($subcategories->isNotEmpty())
            <div class="py-2.5 bg-[#121214]/80 backdrop-blur-md border-b border-white/5 sticky top-[105px] z-20 transition-transform duration-500 ease-in-out"
                 :class="isScrollingDown ? '-translate-y-[160px] delay-0' : 'translate-y-0 delay-150'">
                 
                <div class="flex overflow-x-auto gap-2 hide-scrollbar snap-x px-4" id="subcategories-container">
                    @foreach($subcategories as $sub)
                        @php
                            $isSubActive = $activeSubcategory && $activeSubcategory->id == $sub->id;
                        @endphp
                        
                        <a href="?sub={{ $sub->id }}" 
                           @if($isSubActive) id="active-sub-category" @endif
                           class="px-3.5 py-1.5 rounded-full border text-[11px] font-medium whitespace-nowrap snap-center active:scale-95 transition-all
                           {{ $isSubActive ? 'bg-primary text-white border-primary shadow-[0_0_10px_rgba(var(--color-primary-rgb),0.3)]' : 'bg-[#1E1E24] border-white/10 text-gray-300' }}">
                            {{ $sub->name }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- گرید محصولات -->
        @if($products->isNotEmpty())
            <div class="pt-2 relative z-10 bg-[#121214]">
                <x-miniapp.product-grid :title="$activeSubcategory ? 'محصولات ' . $activeSubcategory->name : 'محصولات'" :products="$products" />
            </div>
        @else
            <div class="flex flex-col items-center justify-center h-64 mt-10 opacity-50 relative z-10 bg-[#121214]">
                <svg class="w-16 h-16 text-gray-500 mb-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                <p class="text-gray-400 text-[13px] font-medium">محصولی برای این بخش یافت نشد.</p>
            </div>
        @endif

    </main>

    <!-- اسکریپت‌های صفحه -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('scrollAccordion', () => ({
                isScrollingDown: false,
                lastScroll: 0,
                
                init() {
                    window.addEventListener('scroll', () => {
                        let currentScroll = window.pageYOffset;
                        
                        if (currentScroll <= 60) {
                            this.isScrollingDown = false;
                            this.lastScroll = currentScroll;
                            return;
                        }

                        // خطای تایپی اینجا اصلاح شده است
                        if (Math.abs(currentScroll - this.lastScroll) < 15) return;

                        if (currentScroll > this.lastScroll) {
                            this.isScrollingDown = true; 
                        } else {
                            this.isScrollingDown = false;
                        }
                        
                        this.lastScroll = currentScroll;
                    });
                }
            }));
        });

        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(() => {
                const activeMain = document.getElementById('active-main-category');
                if (activeMain) {
                    activeMain.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                }

                const activeSub = document.getElementById('active-sub-category');
                if (activeSub) {
                    activeSub.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                }
            }, 100);
        });
    </script>

</x-miniapp.layouts.master>