<x-layouts.roboshop title="RoboShop - پلتفرم هوشمند اتوماسیون فروشگاهی" mode="home"
    :links="[['امکانات', '#features'], ['آکادمی', '#blog'], ['تعرفه‌ها', '#pricing']]">

    <div class="alert-stack">
        @if (session('reg_success'))
            <div class="alert alert-success" id="regAlertSuccess" role="alert"><span>{{ session('reg_success') }}</span><button type="button" aria-label="بستن" onclick="this.parentElement.remove()">×</button></div>
        @endif
        @if (session('reg_error'))
            <div class="alert alert-error" id="regAlertError" role="alert"><span>{{ session('reg_error') }}</span><button type="button" aria-label="بستن" onclick="this.parentElement.remove()">×</button></div>
        @endif
        @if (session('renew_success'))
            <div class="alert alert-success" id="renewAlertSuccess" role="alert"><span>{{ session('renew_success') }}</span><button type="button" aria-label="بستن" onclick="this.parentElement.remove()">×</button></div>
        @endif
        @if (session('renew_error'))
            <div class="alert alert-error" id="renewAlertError" role="alert"><span>{{ session('renew_error') }}</span><button type="button" aria-label="بستن" onclick="this.parentElement.remove()">×</button></div>
        @endif
    </div>

    <main id="home">
        <!-- Hero -->
        <section class="hero relative overflow-hidden py-24">
            <div class="hero-grid absolute inset-0 opacity-70" aria-hidden="true"></div>
            <div class="glow -right-36 -top-36 bg-violet-500" aria-hidden="true"></div>
            <div class="glow -bottom-64 -left-40 bg-cyan-400" aria-hidden="true"></div>
            <div class="container-x relative grid items-center gap-12 lg:gap-14 lg:grid-cols-[1.08fr_.92fr]">
                <div class="text-center lg:text-right">
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-cyan-300/20 bg-cyan-300/[.07] px-4 py-2 text-xs font-semibold text-cyan-200">
                        <span class="dot bg-cyan-300 shadow-[0_0_10px_#42e8ee]"></span>
                        🚀 آینده‌ی تجارت الکترونیک دست خودته
                    </div>
                    <h1 class="mb-5 text-5xl font-black leading-[1.5]">
                        فروشگاه آنلاینتو بذار
                        <span class="gradient-text block">رو خلبان خودکار!</span>
                    </h1>
                    <p class="hero-copy mx-auto mb-8 max-w-2xl text-lg text-slate-300 lg:mx-0">
                        دیگه لازم نیست تو دایرکت‌ها دستی جواب بدی، فیش‌های واریزی رو چک کنی یا موجودی رو یکی‌یکی بشماری. با روبوشاپ یه <b>شعبه مجازی (مینی‌اپلیکیشن)</b> قدرتمند راه بنداز که ۲۴ ساعته برات می‌فروشه و پرداخت‌ها رو همون لحظه تأیید می‌کنه.
                    </p>
                    <div class="hero-actions flex flex-wrap justify-center gap-3 lg:justify-start">
                        @auth
                            @if (auth()->user()->shops()->exists())
                                <a href="{{ route('panel.gateway') }}" class="btn btn-primary">📊 ورود به داشبورد مدیریت</a>
                            @else
                                <a href="{{ route('bot.create') }}" class="btn btn-primary relative group overflow-hidden">
                                    <span class="absolute inset-0 w-full h-full -mt-1 rounded-lg opacity-30 bg-gradient-to-b from-transparent via-transparent to-black"></span>
                                    <span class="relative">✨ ساخت فروشگاه (۳۰ روز رایگان)</span>
                                </a>
                            @endif
                        @else
                            <a href="{{ route('login') }}" class="btn btn-primary relative group overflow-hidden">
                                <span class="absolute inset-0 w-full h-full -mt-1 rounded-lg opacity-30 bg-gradient-to-b from-transparent via-transparent to-black"></span>
                                <span class="relative">🚀 شروع ۳۰ روز تست رایگان</span>
                            </a>
                        @endauth

                        <a href="#features" class="btn btn-secondary">چرا روبوشاپ؟ <span aria-hidden="true">↓</span></a>
                    </div>
                    <div class="mt-8 text-xs text-slate-400">
                        <p class="flex items-center justify-center gap-2 lg:justify-start"><span class="text-cyan-300">✓</span> انتخاب بیش از ۲,۵۰۰ کسب‌وکار فعال</p>
                    </div>
                </div>

                <!-- Dashboard preview -->
                <div class="relative mx-auto w-full max-w-[510px]">
                    <div class="absolute -inset-5 rounded-[2rem] bg-gradient-to-br from-cyan-400/15 to-violet-500/20 blur-2xl" aria-hidden="true"></div>
                    <div class="mock-window relative overflow-hidden rounded-[26px] p-4 sm:p-5">
                        <div class="mb-5 flex items-center justify-between gap-2 border-b border-white/10 pb-4">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full bg-rose-400"></span><span class="h-2.5 w-2.5 rounded-full bg-amber-300"></span><span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                            </div>
                            <span class="text-xs font-bold text-slate-300">داشبورد هوش تجاری</span>
                            <span class="text-[11px] text-cyan-300">● زنده</span>
                        </div>
                        <div class="mb-4 grid grid-cols-2 gap-3">
                            <div class="mock-row rounded-2xl p-4"><p class="text-xs text-slate-400">💰 فروش خودکار امروز</p><p class="mt-2 text-lg font-black leading-8">۴,۵۰۰,۰۰۰ <span class="text-xs font-bold text-emerald-300">تومان</span></p></div>
                            <div class="mock-row rounded-2xl p-4"><p class="text-xs text-slate-400">👥 مشتری‌های جدید</p><p class="mt-2 text-lg font-black leading-8">+۱۲۴ <span class="text-xs font-bold text-cyan-300">نفر</span></p></div>
                        </div>
                        <div class="mock-chart" aria-hidden="true"></div>
                        <div class="flex items-center gap-2 rounded-xl border border-emerald-300/20 bg-emerald-300/[.07] px-4 py-3 text-xs leading-7 text-emerald-200">
                            <span aria-hidden="true">✅</span> سفارش #1045 با موفقیت پرداخت و ثبت شد.
                        </div>
                    </div>
                    <p class="mt-3 text-center text-[11px] text-slate-500">این تصویر فقط نمونه‌ست و اطلاعاتش واقعی نیست.</p>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section id="features" class="section-pad border-y border-white/5 bg-white/[.018] py-20">
            <div class="container-x">
                <div class="mx-auto mb-12 max-w-3xl text-center">
                    <p class="section-kicker mb-3">امکانات</p>
                    <h2 class="section-title">چرا روبوشاپ <span class="gradient-text">نقطه عطف کسب‌وکارته؟</span></h2>
                    <p class="mt-4 leading-8 muted">امکاناتی که زحمتت رو کم می‌کنه، برندت رو حرفه‌ای‌تر نشون می‌ده و فروشت رو بالا می‌بره.</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <article class="glass feature-card rounded-2xl p-6"><div class="icon-box mb-5">🧑‍💼</div><h3 class="mb-2 text-lg font-extrabold">فروشنده و دستیار ۲۴ ساعته</h3><p class="text-sm leading-8 muted">دستیار هوشمندت هر ساعت از شبانه‌روز کاتالوگ محصولات رو به مشتری نشون می‌ده، سبد خرید می‌سازه و تا مرحله پرداخت همراهش می‌مونه.</p></article>
                    <article class="glass feature-card rounded-2xl p-6"><div class="icon-box mb-5">💳</div><h3 class="mb-2 text-lg font-extrabold">تأیید آنی و اتصال به درگاه</h3><p class="text-sm leading-8 muted">از دست کارت‌به‌کارت و فیش‌های جعلی راحت شو! اتصال مستقیم به درگاه‌های بانکی معتبر، با تأیید و تسویه کاملاً خودکار.</p></article>
                    <article class="glass feature-card rounded-2xl p-6"><div class="icon-box mb-5">🧾</div><h3 class="mb-2 text-lg font-extrabold">صدور فاکتور رسمی (PDF)</h3><p class="text-sm leading-8 muted">بلافاصله بعد از پرداخت، یه فاکتور شیک با لوگوی خودت صادر می‌شه که اعتماد مشتری رو حسابی بالا می‌بره.</p></article>
                    <article class="glass feature-card rounded-2xl p-6"><div class="icon-box mb-5">📦</div><h3 class="mb-2 text-lg font-extrabold">کنترل دقیق و هوشمند انبار</h3><p class="text-sm leading-8 muted">با ثبت هر سفارش، موجودی از انبار کم می‌شه. اگه یه سایز یا رنگ تموم بشه، سیستم همون لحظه فروشش رو می‌بنده تا پیش مشتری شرمنده نشی.</p></article>
                    <article class="glass feature-card rounded-2xl p-6"><div class="icon-box mb-5">📊</div><h3 class="mb-2 text-lg font-extrabold">داشبورد مدیریت و آمار فروش</h3><p class="text-sm leading-8 muted">محصولات، سفارش‌ها، سبدهای رهاشده و نمودارهای مالی رو از یه پنل وب پیشرفته، با موبایل یا لپ‌تاپ، مدیریت کن.</p></article>
                    <article class="glass feature-card rounded-2xl p-6"><div class="icon-box mb-5">🎧</div><h3 class="mb-2 text-lg font-extrabold">ارتباط یکپارچه با مشتری‌ها</h3><p class="text-sm leading-8 muted">مشتری‌ها از تو مینی‌اپلیکیشن با پشتیبانی در تماس‌ان و تیمت تو یه پنل متمرکز به همه‌ی سؤال‌ها و پیگیری‌ها جواب می‌ده.</p></article>
                </div>
            </div>
        </section>

        <!-- Blog -->
        <section id="blog" class="section-pad py-20">
            <div class="container-x">
                <div class="mx-auto mb-12 max-w-3xl text-center">
                    <p class="section-kicker mb-3">آکادمی</p>
                    <h2 class="section-title">آکادمی <span class="gradient-text">رشد و توسعه</span></h2>
                    <p class="mt-4 leading-8 muted">استراتژی‌ها و ترفندهای کاربردی برای بالا بردن فروش آنلاین.</p>
                </div>

                <div class="blog-grid grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse ($blogPosts as $post)
                        <article class="glass feature-card blog-card" onclick="window.location.href='#'">
                            <div class="blog-image">
                                @if ($post->image)
                                    <img src="{{ asset($post->image) }}" alt="{{ $post->title }}" loading="lazy">
                                @else
                                    <div class="blog-image-placeholder">📰</div>
                                @endif
                            </div>
                            <div class="blog-content">
                                <div class="blog-meta">📅 {{ $post->created_at->format('Y/m/d') }}</div>
                                <h3 class="blog-title">{{ $post->title }}</h3>
                                <p class="blog-excerpt">{{ Str::limit($post->excerpt ?? $post->content, 90) }}</p>
                                <span class="read-more">مطالعه مقاله ←</span>
                            </div>
                        </article>
                    @empty
                        <p class="empty-state">هنوز مقاله‌ای منتشر نشده.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <!-- Pricing -->
        <section id="pricing" class="section-pad border-y border-white/5 bg-white/[.018] py-20">
            <div class="container-x">
                <div class="mx-auto mb-12 max-w-3xl text-center">
                    <p class="section-kicker mb-3">تعرفه‌ها</p>
                    <h2 class="section-title">روی رشد کسب‌وکارت <span class="gradient-text">سرمایه‌گذاری کن</span></h2>
                    <p class="mt-4 leading-8 muted">با <span class="font-bold text-rose-400">تخفیف ویژه‌ی رونمایی (ظرفیت محدود)</span>؛ اول ۳۰ روز کاملاً رایگان سیستم رو امتحان کن و بعد تصمیم بگیر!</p>
                </div>

                <div class="pricing-grid grid gap-6 pt-3 md:grid-cols-2 lg:grid-cols-3">
                    @forelse ($plans as $plan)
                        @php
                            $slugLower = strtolower($plan->slug);
                            $isFeatured = ($slugLower === 'professional' || $slugLower === 'pro' || $plan->slug === 'حرفه‌ای');

                            $prices = is_array($plan->prices) ? $plan->prices : json_decode($plan->prices, true);
                            $pricesArr = $prices ?? [];

                            // استخراج قیمت یک ماهه از ساختار جدید دیتابیس
                            $displayPrice = 0;
                            foreach($pricesArr as $p) {
                                if(isset($p['duration_months']) && $p['duration_months'] == 1) {
                                    $displayPrice = $p['price'];
                                    break;
                                }
                            }
                            // Fallback اگر پیدا نشد
                            if($displayPrice == 0 && count($pricesArr) > 0) {
                                $displayPrice = reset($pricesArr)['price'];
                            }

                            // محاسبه قیمت خط خورده (اصلی) بر اساس سناریوی تخفیف رونمایی
                            $strikePrice = 0;
                            if ($displayPrice == 190000) $strikePrice = 390000;
                            elseif ($displayPrice == 490000) $strikePrice = 890000;
                            elseif ($displayPrice == 990000) $strikePrice = 1900000;
                            else $strikePrice = $displayPrice * 1.8; // فرمول جایگزین برای قیمت‌های نامشخص

                            $features = is_array($plan->features) ? $plan->features : json_decode($plan->features, true);
                            $featuresArr = $features ?? [];
                        @endphp

                        <div class="glass pricing-card {{ $isFeatured ? 'featured' : '' }}">
                            @if ($isFeatured)
                                <div class="popular-badge">⭐ محبوب‌ترین انتخاب مدیرها</div>
                            @endif

                            <div class="plan-header">
                                <h3 class="plan-name">{{ $plan->name }}</h3>

                                <div class="plan-price-box">
                                    @if ($strikePrice > 0)
                                        <div class="strike-row">
                                            <span class="discount-tag">تخفیف رونمایی</span>
                                            <span class="strike-price">{{ number_format($strikePrice) }}</span>
                                        </div>
                                    @endif

                                    <span class="price-number {{ $isFeatured ? 'gradient-text' : '' }}">{{ number_format($displayPrice) }}</span>
                                    <span class="price-currency">تومان <small>/ ماهانه</small></span>
                                </div>
                            </div>

                            <ul class="plan-features-list">
                                @foreach ($featuresArr as $feature)
                                    <li>{{ $feature }}</li>
                                @endforeach
                            </ul>

                            <div class="plan-footer">
                                <a href="https://t.me/RoboShop_fatherbot" target="_blank" rel="noopener noreferrer" class="btn {{ $isFeatured ? 'btn-primary' : 'btn-secondary' }} btn-block text-center flex items-center justify-center gap-2">
                                    <span>خرید و تمدید در ربات مدیریت</span>
                                    <span>🤖</span>
                                </a>
                            </div>
                        </div>
                    @empty
                        <p class="empty-state">در حال به‌روزرسانی تعرفه‌ها...</p>
                    @endforelse
                </div>

                <!-- CTA ساخت فروشگاه در بخش قیمت‌ها -->
                <div class="flex justify-center mt-12">
                    <div class="relative group">
                        <div class="absolute -inset-1 rounded-xl bg-gradient-to-r from-cyan-400 to-violet-500 opacity-70 blur transition duration-200 group-hover:opacity-100"></div>
                        <a href="{{ auth()->check() ? route('bot.create') : route('login') }}" class="relative inline-flex items-center justify-center rounded-xl bg-slate-900 px-8 py-4 text-lg font-bold text-white transition-all hover:bg-slate-800">
                            <span class="mr-2">✨</span>
                           ایجاد فروشگاه
                            <span class="ml-3 rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-semibold text-emerald-300 border border-emerald-500/30">۳۰ روز رایگان</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA به صفحه معرفی -->
        <section class="py-16">
            <div class="container-x">
                <div class="relative overflow-hidden rounded-[28px] border border-cyan-300/20 bg-gradient-to-l from-cyan-400/[.12] via-blue-500/[.08] to-violet-500/[.13] px-5 py-10 text-center sm:px-12">
                    <p class="section-kicker mb-3">هنوز مطمئن نیستی؟</p>
                    <h2 class="section-title mb-4">اول با روبوشاپ <span class="gradient-text">بیشتر آشنا شو</span></h2>
                    <p class="mx-auto mb-7 max-w-2xl text-sm leading-8 muted">صفحه‌ی معرفی رو ببین، دموی ربات رو امتحان کن و راه‌های ارتباط باهامون رو پیدا کن.</p>
                    <a href="{{ url('/veiw') }}" class="btn btn-secondary">معرفی کامل روبوشاپ <span aria-hidden="true">←</span></a>
                </div>
            </div>
        </section>
    </main>

</x-layouts.roboshop>
