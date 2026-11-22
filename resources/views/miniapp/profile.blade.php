<x-miniapp.layouts.master :bot="$bot" :show-bottom-nav="true">

    <!-- هدر صفحه -->
    <header class="sticky top-0 z-40 bg-[#121214]/90 backdrop-blur-xl border-b border-white/5 flex items-center justify-between px-4 h-14">
        <!-- دکمه خروج -->
        <a href="/app/{{ request()->route('bot_username') }}/logout" class="w-8 h-8 flex items-center justify-center bg-red-500/10 border border-red-500/20 rounded-full text-red-400 active:scale-90 transition-transform">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" /></svg>
        </a>
        
        <h1 class="font-bold text-gray-100 text-[15px]">حساب کاربری</h1>
        
        <!-- دکمه بازگشت (اگر نیاز بود) -->
        <div class="w-8"></div>
    </header>

    <main class="pb-28 pt-6 px-4 space-y-6">

        <!-- کارت اطلاعات شخصی و کیف پول -->
        <div class="bg-gradient-to-br from-[#1E1E24] to-[#121214] border border-white/10 rounded-3xl p-5 shadow-lg relative overflow-hidden">
            <!-- افکت‌های نوری پس‌زمینه -->
            <div class="absolute -top-10 -right-10 w-32 h-32 bg-primary/20 rounded-full blur-3xl pointer-events-none"></div>
            
            <div class="flex items-center gap-4 relative z-10">
                <!-- آواتار کاربر -->
                <div class="w-16 h-16 rounded-full bg-primary/20 border-2 border-primary/50 flex items-center justify-center text-primary font-black text-2xl shadow-lg shadow-primary/20 shrink-0">
                    {{ mb_substr($user->first_name ?? ($user->name ?? 'کاربر'), 0, 1) }}
                </div>
                
                <div class="flex-1">
                    <h2 class="text-white font-extrabold text-[18px] drop-shadow-md">
                        {{ $user->first_name ?? ($user->name ?? 'کاربر گرامی') }}
                    </h2>
                    <div class="text-gray-400 text-[12px] mt-1 dir-ltr text-right font-medium tracking-widest bg-black/20 inline-block px-2 py-1 rounded-lg">
                        {{ $user->phone ?? '---' }}
                    </div>
                </div>
            </div>

            <!-- کیف پول و آمار مختصر -->
            <div class="grid grid-cols-2 gap-3 mt-6 relative z-10">
                <div class="bg-[#121214]/60 backdrop-blur-md rounded-2xl p-3 border border-white/5">
                    <div class="text-gray-400 text-[10px] mb-1 flex items-center gap-1">
                        <svg class="w-3 h-3 text-green-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        موجودی کیف پول
                    </div>
                    <div class="text-white font-bold text-[14px]">
                        {{ number_format(max(0, (float)($user->wallet_balance ?? 0))) }} <span class="text-[9px] text-gray-500 font-normal">تومان</span>
                    </div>
                </div>
                
                <div class="bg-[#121214]/60 backdrop-blur-md rounded-2xl p-3 border border-white/5">
                    <div class="text-gray-400 text-[10px] mb-1 flex items-center gap-1">
                        <svg class="w-3 h-3 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                        سفارشات موفق
                    </div>
                    <div class="text-white font-bold text-[14px]">
                        {{ $successfulOrdersCount }} <span class="text-[9px] text-gray-500 font-normal">سفارش</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- لیست سفارشات اخیر -->
        <div>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-white font-bold text-[15px]">سفارش‌های اخیر شما</h3>
                <!-- دکمه مشاهده همه (فعلا بدون لینک) -->
                <button @click="alert('بخش تاریخچه کامل سفارشات به زودی فعال می‌شود')" class="text-primary text-[11px] font-medium hover:text-primary-light">مشاهده همه</button>
            </div>

            @if($recentOrders->isNotEmpty())
                <div class="space-y-3">
                    @foreach($recentOrders as $order)
                        @php
                            // تعیین رنگ و متن وضعیت سفارش
                            $statusColor = match($order->status) {
                                'pending' => 'text-amber-400 bg-amber-500/10 border-amber-500/20',
                                'paid' => 'text-green-400 bg-green-500/10 border-green-500/20',
                                'processing' => 'text-blue-400 bg-blue-500/10 border-blue-500/20',
                                'shipped' => 'text-indigo-400 bg-indigo-500/10 border-indigo-500/20',
                                'delivered' => 'text-primary bg-primary/10 border-primary/20',
                                'canceled', 'failed' => 'text-red-400 bg-red-500/10 border-red-500/20',
                                default => 'text-gray-400 bg-gray-500/10 border-gray-500/20'
                            };

                            $statusText = match($order->status) {
                                'pending' => 'در انتظار پرداخت',
                                'paid' => 'پرداخت شده',
                                'processing' => 'در حال پردازش',
                                'shipped' => 'ارسال شده',
                                'delivered' => 'تحویل شده',
                                'canceled' => 'لغو شده',
                                'failed' => 'پرداخت ناموفق',
                                default => 'نامشخص'
                            };
                        @endphp

                        <div class="bg-[#1E1E24] p-4 rounded-2xl border border-white/5 active:bg-[#2A2A35] transition-colors cursor-pointer" 
                             @click="alert('جزئیات سفارش {{ $order->tracking_code }} به زودی فعال می‌شود')">
                            
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <div class="text-white font-bold text-[13px] dir-ltr text-right inline-block">{{ $order->tracking_code }}</div>
                                    <div class="text-gray-500 text-[10px] mt-1">{{ \Morilog\Jalali\Jalalian::fromDateTime($order->created_at)->format('d F Y') }}</div>
                                </div>
                                <div class="px-2.5 py-1 rounded-lg border text-[10px] font-bold {{ $statusColor }}">
                                    {{ $statusText }}
                                </div>
                            </div>
                            
                            <hr class="border-white/5 border-dashed my-3">
                            
                            <div class="flex justify-between items-end">
                                <span class="text-gray-400 text-[11px]">مبلغ نهایی:</span>
                                <span class="text-white font-black text-[14px]">
                                    {{ number_format($order->final_amount ?? $order->total_amount) }} <span class="text-[9px] text-gray-500 font-normal">تومان</span>
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <!-- حالت بدون سفارش -->
                <div class="flex flex-col items-center justify-center py-10 opacity-60 bg-[#1E1E24] rounded-3xl border border-white/5">
                    <svg class="w-14 h-14 text-gray-500 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>
                    <p class="text-gray-400 text-[12px] font-medium">هنوز سفارشی ثبت نکرده‌اید.</p>
                </div>
            @endif
        </div>

    </main>

</x-miniapp.layouts.master> 