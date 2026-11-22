<x-miniapp.layouts.master :bot="$bot">

    <x-miniapp.layouts.header :shop-name="$bot->bot_name" :bot-avatar="$botAvatar" />


    <main class="space-y-4 pt-1 pb-28">

        <x-miniapp.search-bar />

        @if ($sliderProducts->count() > 0)
            <x-miniapp.hero-banner :products="$sliderProducts" />
        @endif

        @if ($categories->count() > 0)
            <x-miniapp.categories :categories="$categories" />
        @endif

        <!-- بخش پیشنهادهای ویژه (اسلایدر عادی) -->
        @if ($specialProducts->count() > 0)
            <x-miniapp.product-slider title="پیشنهادهای ویژه" :products="$specialProducts" />
        @endif

        <!-- بخش کاملاً متمایز محصولات تخفیف‌دار -->
        @if ($discountedProducts->count() > 0)
            <x-miniapp.discount-slider title="تخفیف‌های شگفت‌انگیز" :products="$discountedProducts" />
        @endif

        <!-- استفاده مجدد از اسلایدر عادی برای جدیدترین‌ها -->
        @if ($latestProducts->count() > 0)
            <!-- فاصله بیشتر از بالا برای جدا شدن بخش‌ها -->
            <div class="pt-4">
                <x-miniapp.product-slider title="جدیدترین محصولات" :products="$latestProducts" />
            </div>
        @endif

    </main>

</x-miniapp.layouts.master>
