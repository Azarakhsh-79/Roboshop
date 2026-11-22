<x-filament-panels::page>

    {{-- 📅 باکس هوشمند و جمع‌وجورِ فیلتر تاریخ --}}
    <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 mb-6 p-4">
        <div class="flex flex-col lg:flex-row gap-6 items-center justify-between">
            <div class="w-full lg:w-1/2">
                {{ $this->form }}
            </div>
            <div class="w-full lg:w-1/2 text-sm bg-gray-50 dark:bg-gray-800/50 p-3 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-2 mb-2">
                    <x-heroicon-m-calendar-days class="w-5 h-5 text-primary-500" />
                    <span class="font-bold text-gray-800 dark:text-gray-200">بازه فعلی:</span>
                    <span class="text-gray-600 dark:text-gray-400">{{ $currentPeriodText }}</span>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <x-heroicon-m-arrow-path class="w-4 h-4 text-gray-400" />
                    <span class="font-semibold text-gray-500">مقایسه با دوره قبل:</span>
                    <span class="text-gray-500">{{ $previousPeriodText }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- 📑 منوی تب‌ها --}}
    <x-filament::tabs label="Tabs" class="mb-6">
        <x-filament::tabs.item :active="$activeTab === 'overview'" wire:click="setTab('overview')" icon="heroicon-m-squares-2x2">
            خلاصه و فروش
        </x-filament::tabs.item>
        <x-filament::tabs.item :active="$activeTab === 'customers'" wire:click="setTab('customers')" icon="heroicon-m-users">
            مشتریان و وفاداری
        </x-filament::tabs.item>
        <x-filament::tabs.item :active="$activeTab === 'products'" wire:click="setTab('products')" icon="heroicon-m-cube">
            موجودی و کالاها
        </x-filament::tabs.item>
    </x-filament::tabs>

    {{-- 📊 محتوای تب‌ها --}}
    <div>
        @if ($activeTab === 'overview')

            {{-- 💰 کارت‌های KPI --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <x-filament::section>
                    <div class="text-sm font-medium text-gray-500 mb-1">فروش خالص</div>
                    <div class="text-2xl font-bold">{{ number_format($kpi['net_sales']['value']) }} <span class="text-sm font-normal text-gray-500">تومان</span></div>
                    <div class="mt-2 text-sm font-bold flex items-center gap-1 {{ $kpi['net_sales']['is_positive'] ? 'text-success-600' : 'text-danger-600' }}">
                        {{ $kpi['net_sales']['is_positive'] ? '+' : '' }}{{ $kpi['net_sales']['trend'] }}%
                        <span class="text-xs font-normal text-gray-500">نسبت به قبل</span>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="text-sm font-medium text-gray-500 mb-1">سفارشات موفق</div>
                    <div class="text-2xl font-bold">{{ number_format($kpi['total_orders']['value']) }} <span class="text-sm font-normal text-gray-500">سفارش</span></div>
                    <div class="mt-2 text-sm font-bold flex items-center gap-1 {{ $kpi['total_orders']['is_positive'] ? 'text-success-600' : 'text-danger-600' }}">
                        {{ $kpi['total_orders']['is_positive'] ? '+' : '' }}{{ $kpi['total_orders']['trend'] }}%
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="text-sm font-medium text-gray-500 mb-1">ارزش سبد (AOV)</div>
                    <div class="text-2xl font-bold">{{ number_format($kpi['aov']['value']) }} <span class="text-sm font-normal text-gray-500">تومان</span></div>
                    <div class="mt-2 text-sm font-bold flex items-center gap-1 {{ $kpi['aov']['is_positive'] ? 'text-success-600' : 'text-danger-600' }}">
                        {{ $kpi['aov']['is_positive'] ? '+' : '' }}{{ $kpi['aov']['trend'] }}%
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <div class="text-sm font-medium text-gray-500 mb-1">تخفیف‌های داده شده</div>
                    <div class="text-2xl font-bold">{{ number_format($kpi['total_discount']['value']) }} <span class="text-sm font-normal text-gray-500">تومان</span></div>
                    <div class="mt-2 text-sm font-bold flex items-center gap-1 {{ $kpi['total_discount']['is_positive'] ? 'text-success-600' : 'text-warning-600' }}">
                        {{ $kpi['total_discount']['is_positive'] ? '-' : '+' }}{{ $kpi['total_discount']['trend'] }}%
                    </div>
                </x-filament::section>
            </div>

            {{-- 🧲 قیف فروش و وضعیت سفارشات --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <x-filament::section icon="heroicon-o-funnel" heading="قیف فروش و نرخ تبدیل (Conversion)">
                    <div class="space-y-4 mt-2">
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-bold text-gray-700 dark:text-gray-300">کل سبدهای تشکیل شده</span>
                                <span class="text-gray-500">{{ $funnel['total_created'] }} عدد</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                                <div class="bg-gray-500 h-2.5 rounded-full" style="width: 100%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-bold text-success-600 dark:text-success-400">پرداخت موفق (نرخ تبدیل)</span>
                                <span class="font-bold text-success-600">{{ $funnel['successful'] }} عدد ({{ $funnel['conversion_rate'] }}%)</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                                <div class="bg-success-500 h-2.5 rounded-full" style="width: {{ $funnel['conversion_rate'] }}%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="font-bold text-warning-600 dark:text-warning-400">سبدهای رها شده (پرداخت نشده)</span>
                                <span class="font-bold text-warning-600">{{ $funnel['abandoned'] }} عدد ({{ $funnel['abandonment_rate'] }}%)</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                                <div class="bg-warning-500 h-2.5 rounded-full" style="width: {{ $funnel['abandonment_rate'] }}%"></div>
                            </div>
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section icon="heroicon-o-chart-pie" heading="تفکیک وضعیت سفارشات موفق">
                    <div class="grid grid-cols-2 gap-4 h-full content-center mt-2">
                        <div class="p-4 rounded-lg bg-success-50 dark:bg-success-900/20 border border-success-100 dark:border-success-800 text-center">
                            <x-heroicon-o-check-circle class="w-8 h-8 mx-auto text-success-500 mb-2" />
                            <div class="text-xs text-success-600 dark:text-success-400 mb-1">پرداخت شده (جدید)</div>
                            <div class="text-xl font-bold text-success-700 dark:text-success-300">{{ $statusBreakdown['paid'] ?? 0 }}</div>
                        </div>
                        <div class="p-4 rounded-lg bg-warning-50 dark:bg-warning-900/20 border border-warning-100 dark:border-warning-800 text-center">
                            <x-heroicon-o-arrow-path class="w-8 h-8 mx-auto text-warning-500 mb-2" />
                            <div class="text-xs text-warning-600 dark:text-warning-400 mb-1">در حال پردازش</div>
                            <div class="text-xl font-bold text-warning-700 dark:text-warning-300">{{ $statusBreakdown['processing'] ?? 0 }}</div>
                        </div>
                        <div class="p-4 rounded-lg bg-info-50 dark:bg-info-900/20 border border-info-100 dark:border-info-800 text-center">
                            <x-heroicon-o-truck class="w-8 h-8 mx-auto text-info-500 mb-2" />
                            <div class="text-xs text-info-600 dark:text-info-400 mb-1">ارسال شده (پستی)</div>
                            <div class="text-xl font-bold text-info-700 dark:text-info-300">{{ $statusBreakdown['shipped'] ?? 0 }}</div>
                        </div>
                        <div class="p-4 rounded-lg bg-primary-50 dark:bg-primary-900/20 border border-primary-100 dark:border-primary-800 text-center">
                            <x-heroicon-o-home class="w-8 h-8 mx-auto text-primary-500 mb-2" />
                            <div class="text-xs text-primary-600 dark:text-primary-400 mb-1">تحویل مشتری شده</div>
                            <div class="text-xl font-bold text-primary-700 dark:text-primary-300">{{ $statusBreakdown['delivered'] ?? 0 }}</div>
                        </div>
                    </div>
                </x-filament::section>
            </div>

            {{-- 🏆 جداول برترین‌ها --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <x-filament::section icon="heroicon-o-fire" heading="محصولات پرسود در این بازه">
                    <div class="divide-y divide-gray-200 dark:divide-white/5 mt-2">
                        @forelse($topProducts as $product)
                            <div class="py-3 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <x-filament::badge color="warning" size="sm">{{ $loop->iteration }}</x-filament::badge>
                                    <div>
                                        <div class="font-bold text-sm">{{ $product->name_at_purchase }}</div>
                                        <div class="text-xs text-gray-500 mt-1">تعداد فروش: {{ number_format($product->total_sold) }} عدد</div>
                                    </div>
                                </div>
                                <div class="font-bold text-sm text-success-600 dark:text-success-400">
                                    {{ number_format($product->total_revenue) }} تومان
                                </div>
                            </div>
                        @empty
                            <div class="py-4 text-center text-sm text-gray-500">محصولی یافت نشد.</div>
                        @endforelse
                    </div>
                </x-filament::section>

                <x-filament::section icon="heroicon-o-star" heading="مشتریان VIP در این بازه">
                    <div class="divide-y divide-gray-200 dark:divide-white/5 mt-2">
                        @forelse($topCustomers as $customer)
                            <div class="py-3 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <x-filament::badge color="primary" size="sm">
                                        {{ mb_substr(trim($customer->first_name . ' ' . $customer->last_name) ?: ($customer->username ?: 'U'), 0, 1) }}
                                    </x-filament::badge>
                                    <div>
                                        <div class="font-bold text-sm">
                                            {{ trim($customer->first_name . ' ' . $customer->last_name) ?: ($customer->username ? '@' . $customer->username : 'کاربر ناشناس') }}
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">تعداد فاکتور موفق: {{ $customer->successful_orders }}</div>
                                    </div>
                                </div>
                                <div class="font-bold text-sm text-success-600 dark:text-success-400">
                                    {{ number_format($customer->total_spent) }} تومان
                                </div>
                            </div>
                        @empty
                            <div class="py-4 text-center text-sm text-gray-500">مشتری طلایی یافت نشد.</div>
                        @endforelse
                    </div>
                </x-filament::section>
            </div>

        @elseif($activeTab === 'customers')

            {{-- 📊 کارت‌های KPI وفاداری مشتریان --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <x-filament::section>
                    <div class="text-sm font-medium text-gray-500 mb-1">کل خریداران منحصربه‌فرد</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($customerMetrics['total_buyers']) }} <span class="text-sm font-normal text-gray-500">نفر</span></div>
                    <div class="mt-2 text-xs text-gray-500">کاربرانی که حداقل یک سفارش موفق ثبت کرده‌اند</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-sm font-medium text-gray-500 mb-1">نرخ مشتریان بازگشتی (Repeat Rate)</div>
                    <div class="text-2xl font-bold text-primary-600">{{ $customerMetrics['repeat_rate'] }}%</div>
                    <div class="mt-2 text-xs text-gray-500">{{ number_format($customerMetrics['repeat_buyers']) }} نفر بیش از یک بار خرید کرده‌اند</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-sm font-medium text-gray-500 mb-1">ارزش طول عمر مشتری (CLV)</div>
                    <div class="text-2xl font-bold text-success-600">{{ number_format($customerMetrics['clv']) }} <span class="text-sm font-normal text-gray-500">تومان</span></div>
                    <div class="mt-2 text-xs text-gray-500">میانگین ارزش پولی هر مشتری برای فروشگاه</div>
                </x-filament::section>
            </div>

            {{-- 💤 مشتریان غیرفعال --}}
            <x-filament::section icon="heroicon-o-user-minus" heading="مشتریان غیرفعال (بیش از ۳۰ روز بدون خرید)">
                <x-slot name="description">
                    این کاربران سابقه خرید دارند اما در ۳۰ روز گذشته سفارشی ثبت نکرده‌اند. بهترین فرصت برای ارسال کد تخفیف بازگشت است!
                </x-slot>
                <div class="divide-y divide-gray-200 dark:divide-white/5 mt-4">
                    @forelse($inactiveCustomers as $customer)
                        <div class="py-3 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <x-filament::badge color="danger" size="sm">
                                    {{ mb_substr(trim($customer->first_name . ' ' . $customer->last_name) ?: ($customer->username ?: 'U'), 0, 1) }}
                                </x-filament::badge>
                                <div>
                                    <div class="font-bold text-sm">
                                        {{ trim($customer->first_name . ' ' . $customer->last_name) ?: ($customer->username ? '@'.$customer->username : 'کاربر بدون نام') }}
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">تلفن: {{ $customer->phone ?: 'ثبت نشده' }} | چت آیدی: {{ $customer->chat_id }}</div>
                                </div>
                            </div>
                            <div class="text-left">
                                <span class="text-xs font-semibold text-gray-500 bg-gray-100 dark:bg-gray-800 px-2.5 py-1 rounded-full">
                                    آخرین خرید: {{ $customer->last_purchase_date ? \Morilog\Jalali\Jalalian::fromDateTime($customer->last_purchase_date)->ago() : '-' }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-sm text-gray-500">هیچ مشتری غیرفعالی در این بازه پیدا نشد. عالیه!</div>
                    @endforelse
                </div>
            </x-filament::section>

            {{-- 💎 و 🛒 بخشِ گرید دو ستونه برای سگمنت‌ها و سبدهای رها شده --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                <x-filament::section icon="heroicon-o-chart-pie" heading="بخش‌بندی مشتریان (ارزش مالی)">
                    <div class="space-y-4 mt-2">
                        @foreach ($segments as $key => $segment)
                            <div class="flex items-center justify-between p-3 rounded-lg border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/50">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 rounded-full bg-{{ $segment['color'] }}-100 dark:bg-{{ $segment['color'] }}-900/30 text-{{ $segment['color'] }}-600 dark:text-{{ $segment['color'] }}-400">
                                        <x-dynamic-component :component="$segment['icon']" class="w-5 h-5" />
                                    </div>
                                    <div>
                                        <div class="font-bold text-sm text-gray-900 dark:text-white">{{ $segment['label'] }}</div>
                                        <div class="text-xs text-gray-500 mt-0.5">
                                            @if ($key === 'vip') خرید بالای ۵ میلیون تومان
                                            @elseif($key === 'loyal') خرید بین ۱ تا ۵ میلیون تومان
                                            @else خرید زیر ۱ میلیون تومان @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="text-lg font-bold text-{{ $segment['color'] }}-600 dark:text-{{ $segment['color'] }}-400">
                                    {{ $segment['count'] }} <span class="text-sm font-normal text-gray-500">نفر</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>

                <x-filament::section icon="heroicon-o-shopping-cart" heading="آخرین سبدهای رها شده (نیاز به پیگیری)">
                    <x-slot name="description">کاربرانی که در این بازه زمانی تا مرحله پرداخت رفتند اما فاکتور را تسویه نکردند.</x-slot>
                    <div class="divide-y divide-gray-200 dark:divide-white/5 mt-4">
                        @forelse($abandonedCarts as $cart)
                            <div class="py-3 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-danger-100 dark:bg-danger-900/30 flex items-center justify-center text-danger-600 dark:text-danger-400 font-bold text-sm">
                                        {{ mb_substr(trim($cart->user->first_name . ' ' . $cart->user->last_name) ?: ($cart->user->username ?: 'U'), 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-sm">
                                            {{ trim($cart->user->first_name . ' ' . $cart->user->last_name) ?: ($cart->user->username ? '@' . $cart->user->username : 'کاربر بدون نام') }}
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            مبلغ: {{ number_format($cart->total_amount - $cart->discount_amount) }} تومان
                                        </div>
                                    </div>
                                </div>
                                <div class="text-left text-xs text-gray-500">
                                    {{ \Morilog\Jalali\Jalalian::fromDateTime($cart->updated_at)->ago() }}
                                </div>
                            </div>
                        @empty
                            <div class="py-6 text-center text-sm text-gray-500">هیچ سبد رها شده‌ای در این بازه یافت نشد.</div>
                        @endforelse
                    </div>
                </x-filament::section>
            </div>

        @elseif($activeTab === 'products')

            {{-- 📦 کارت‌های آماری انبار --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <x-filament::section>
                    <div class="text-sm font-medium text-gray-500 mb-1">ارزش ریالی موجودی انبار</div>
                    <div class="text-2xl font-bold text-success-600">{{ number_format($inventoryStats['inventory_value']) }} <span class="text-sm font-normal text-gray-500">تومان</span></div>
                    <div class="mt-2 text-xs text-gray-500">سرمایه خوابیده در محصولات موجود</div>
                </x-filament::section>

                <x-filament::section>
                    <div class="text-sm font-medium text-gray-500 mb-1">کل محصولات فعال</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($inventoryStats['total_products']) }} <span class="text-sm font-normal text-gray-500">قلم کالا</span></div>
                    <div class="mt-2 text-xs text-gray-500">در حال نمایش در فروشگاه</div>
                </x-filament::section>

                <x-filament::section>
                    <div class="text-sm font-medium text-gray-500 mb-1">کالاهای ناموجود (صفر)</div>
                    <div class="text-2xl font-bold text-danger-600">{{ number_format($inventoryStats['out_of_stock']) }} <span class="text-sm font-normal text-gray-500">قلم کالا</span></div>
                    <div class="mt-2 text-xs text-gray-500">از دست دادن فرصت فروش!</div>
                </x-filament::section>

                <x-filament::section>
                    <div class="text-sm font-medium text-gray-500 mb-1">در آستانه اتمام (زیر ۵ عدد)</div>
                    <div class="text-2xl font-bold text-warning-600">{{ number_format($inventoryStats['low_stock']) }} <span class="text-sm font-normal text-gray-500">قلم کالا</span></div>
                    <div class="mt-2 text-xs text-gray-500">نیازمند شارژ مجدد در انبار</div>
                </x-filament::section>
            </div>

            {{-- 🚨 و 📉 جداول هشدارهای انبار --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                <x-filament::section icon="heroicon-o-exclamation-triangle" heading="هشدار موجودی (کالاهای رو به اتمام)">
                    <div class="divide-y divide-gray-200 dark:divide-white/5 mt-4">
                        @forelse($lowStockProducts as $product)
                            <div class="py-3 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <x-filament::badge :color="$product->stock === 0 ? 'danger' : 'warning'" size="sm">
                                        {{ $product->stock === 0 ? 'ناموجود' : $product->stock . ' عدد' }}
                                    </x-filament::badge>
                                    <div>
                                        <div class="font-bold text-sm text-gray-900 dark:text-white">{{ $product->name }}</div>
                                        <div class="text-xs text-gray-500 mt-1">قیمت: {{ number_format($product->price) }} تومان</div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="py-6 text-center text-sm text-gray-500 flex flex-col items-center">
                                <x-heroicon-o-check-circle class="w-8 h-8 text-success-500 mb-2" />
                                عالی! هیچ کالایی در آستانه اتمام نیست.
                            </div>
                        @endforelse
                    </div>
                </x-filament::section>

                <x-filament::section icon="heroicon-o-archive-box-x-mark" heading="کالاهای راکد (بدون فروش در این بازه)">
                    <x-slot name="description">این کالاها در انبار موجودند اما در این بازه زمانی هیچ فروشی نداشته‌اند. (بهترین گزینه برای اعمال تخفیف)</x-slot>
                    <div class="divide-y divide-gray-200 dark:divide-white/5 mt-4">
                        @forelse($stagnantProducts as $product)
                            <div class="py-3 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-500 border border-gray-200 dark:border-gray-700">
                                        <x-heroicon-o-archive-box class="w-5 h-5" />
                                    </div>
                                    <div>
                                        <div class="font-bold text-sm text-gray-900 dark:text-white">{{ $product->name }}</div>
                                        <div class="text-xs text-gray-500 mt-1">موجودی انبار: <b class="text-gray-700 dark:text-gray-300">{{ $product->stock }} عدد</b></div>
                                    </div>
                                </div>
                                <div class="text-left text-xs font-semibold text-gray-500">
                                    ارزش: {{ number_format($product->stock * $product->price) }} تومان
                                </div>
                            </div>
                        @empty
                            <div class="py-6 text-center text-sm text-gray-500">
                                تمام کالاهای موجود در انبار در این بازه فروش داشته‌اند.
                            </div>
                        @endforelse
                    </div>
                </x-filament::section>
            </div>

        @endif
    </div>

</x-filament-panels::page>
