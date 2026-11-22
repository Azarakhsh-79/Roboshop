<x-layouts.main title="RoboShop - پلتفرم هوشمند اتوماسیون فروشگاهی">

    @if (session('reg_success'))
        <x-alert type="success" id="regAlertSuccess">{{ session('reg_success') }}</x-alert>
    @endif
    @if (session('reg_error'))
        <x-alert type="error" id="regAlertError">{{ session('reg_error') }}</x-alert>
    @endif
    @if (session('renew_success'))
        <x-alert type="success" id="renewAlertSuccess">{{ session('renew_success') }}</x-alert>
    @endif
    @if (session('renew_error'))
        <x-alert type="error" id="renewAlertError">{{ session('renew_error') }}</x-alert>
    @endif

    <x-navbar />

    <section class="hero-section">
        <div class="hero-background"></div>
        <div class="hero-container">
            <div class="hero-content">
                <div class="badge fade-in-up">
                    <i class="fa-solid fa-rocket"></i> آینده تجارت الکترونیک در دستان شماست
                </div>
                <h1 class="hero-title fade-in-up" style="animation-delay: 0.1s;">
                    فروشگاه آنلاین خود را <br>
                    <span class="text-gradient">روی خلبان خودکار تنظیم کنید!</span>
                </h1>
                <p class="hero-subtitle fade-in-up" style="animation-delay: 0.2s;">
                    دیگر نیازی به پاسخگویی دستی در دایرکت‌ها، چک کردن فیش‌های واریزی و مدیریت خسته‌کننده موجودی نیست. با پلتفرم ما، یک <b>شعبه مجازی (مینی‌اپلیکیشن)</b> قدرتمند راه‌اندازی کنید که ۲۴ ساعته برای شما می‌فروشد و پرداخت‌ها را در لحظه تایید می‌کند.
                </p>
                <div class="hero-actions fade-in-up" style="animation-delay: 0.3s;">
                    @auth
                        @if (auth()->user()->shops()->exists())
                            <a href="{{ route('panel.gateway') }}" class="btn btn-primary btn-lg">
                                <i class="fa-solid fa-gauge" style="margin-left: 8px;"></i> ورود به داشبورد مدیریت
                            </a>
                        @else
                            <a href="{{ route('bot.create') }}" class="btn btn-primary btn-lg">
                                <i class="fa-solid fa-magic" style="margin-left: 8px;"></i> راه‌اندازی شعبه مجازی من
                            </a>
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="btn btn-primary btn-lg">
                            <i class="fa-solid fa-user-plus" style="margin-left: 8px;"></i> شروع ۳۰ روز تست رایگان
                        </a>
                    @endauth

                    <a href="#features" class="btn btn-outline btn-lg">
                        <i class="fa-solid fa-play" style="margin-left: 8px;"></i> چرا روبوشاپ؟
                    </a>
                </div>
                <div class="hero-trust fade-in-up" style="animation-delay: 0.4s;">
                    <p>انتخاب هوشمندانه‌ی بیش از ۲,۵۰۰ کسب‌وکار فعال</p>
                </div>
            </div>

            <div class="hero-visual fade-in-left">
                <div class="mockup-card">
                    <div class="mockup-header">
                        <div class="dots"><span></span><span></span><span></span></div>
                        <div class="title">داشبورد هوش تجاری (زنده)</div>
                    </div>
                    <div class="mockup-body">
                        <div class="stat-row">
                            <div class="stat-box">
                                <i class="fa-solid fa-coins" style="color: #10b981;"></i>
                                <span>فروش اتوماتیک امروز</span>
                                <strong>۴,۵۰۰,۰۰۰ تومان</strong>
                            </div>
                            <div class="stat-box">
                                <i class="fa-solid fa-users" style="color: #6366f1;"></i>
                                <span>مشتریان جدید</span>
                                <strong>+۱۲۴ نفر</strong>
                            </div>
                        </div>
                        <div class="mockup-chart"></div>
                        <div class="mockup-alert">
                            <i class="fa-solid fa-check-circle" style="color: #10b981;"></i>
                            سفارش #1045 با موفقیت پرداخت و ثبت شد.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="features" class="section bg-light">
        <div class="section-container">
            <div class="section-header">
                <h2 class="title">چرا این پلتفرم نقطه عطف کسب‌وکار شماست؟</h2>
                <p class="subtitle">امکاناتی که زحمت شما را کم کرده، پرستیژ برندتان را بالا برده و فروش را چند برابر می‌کند.</p>
            </div>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon bg-blue"><i class="fa-solid fa-user-tie"></i></div>
                    <h3>فروشنده و دستیار ۲۴ ساعته</h3>
                    <p>دستیار هوشمند شما در هر ساعت از شبانه‌روز، کاتالوگ محصولات را به مشتریان نشان می‌دهد، سبد خرید می‌سازد و آن‌ها را تا مرحله پرداخت هدایت می‌کند.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon bg-green"><i class="fa-solid fa-credit-card"></i></div>
                    <h3>تایید آنی و اتصال به درگاه</h3>
                    <p>با چالش‌های کارت‌به‌کارت و ارسال فیش‌های جعلی خداحافظی کنید! اتصال مستقیم به درگاه‌های بانکی معتبر با سیستم تایید و تسویه کاملاً خودکار.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon bg-purple"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                    <h3>صدور فاکتور رسمی (PDF)</h3>
                    <p>بلافاصله پس از پرداخت، یک فاکتور حرفه‌ای و شیک با لوگوی اختصاصی فروشگاه شما صادر می‌شود که حس اعتماد و کلاس کاری شما را به شدت بالا می‌برد.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon bg-orange"><i class="fa-solid fa-boxes-stacked"></i></div>
                    <h3>کنترل دقیق و هوشمند انبار</h3>
                    <p>با ثبت هر سفارش، موجودی از انبار کسر می‌شود. در صورت اتمام موجودی یک سایز یا رنگ خاص، سیستم بلافاصله فروش آن را متوقف می‌کند تا شرمنده مشتری نشوید.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon bg-pink"><i class="fa-solid fa-chart-pie"></i></div>
                    <h3>داشبورد مدیریت و آمار فروش</h3>
                    <p>تمامی محصولات، سفارشات، سبدهای رها شده و نمودارهای دقیق مالی خود را از طریق یک پنل وب پیشرفته با موبایل یا لپ‌تاپ مدیریت کنید.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon bg-teal"><i class="fa-solid fa-headset"></i></div>
                    <h3>ارتباط یکپارچه با مشتریان</h3>
                    <p>مشتریان می‌توانند از طریق مینی‌اپلیکیشن شما با بخش پشتیبانی در تماس باشند و تیم شما در یک پنل متمرکز به سوالات و پیگیری‌های آن‌ها پاسخ دهد.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="blog" class="section">
        <div class="section-container">
            <div class="section-header">
                <h2 class="title">آکادمی رشد و توسعه</h2>
                <p class="subtitle">استراتژی‌ها و ترفندهای عملی برای افزایش چشمگیر فروش آنلاین</p>
            </div>

            <div class="blog-grid">
                @forelse ($blogPosts as $post)
                    <article class="blog-card" onclick="window.location.href='#'">
                        <div class="blog-image">
                            @if ($post->image)
                                <img src="{{ asset($post->image) }}" alt="{{ $post->title }}">
                            @else
                                <div class="blog-image-placeholder"><i class="fa-solid fa-newspaper"></i></div>
                            @endif
                        </div>
                        <div class="blog-content">
                            <div class="blog-meta">
                                <span class="date"><i class="fa-regular fa-calendar"></i>
                                    {{ $post->created_at->format('Y/m/d') }}</span>
                            </div>
                            <h3 class="blog-title">{{ $post->title }}</h3>
                            <p class="blog-excerpt">{{ Str::limit($post->excerpt ?? $post->content, 90) }}</p>
                            <span class="read-more">مطالعه مقاله <i class="fa-solid fa-arrow-left"></i></span>
                        </div>
                    </article>
                @empty
                    <p class="empty-state">هنوز مقاله‌ای منتشر نشده است.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section id="pricing" class="section bg-light">
        <div class="section-container">
            <div class="section-header">
                <h2 class="title">سرمایه‌گذاری روی رشد کسب‌وکار</h2>
                <p class="subtitle">با <span style="color: #ef4444; font-weight: bold;">تخفیف ویژه رونمایی (ظرفیت محدود)</span>؛ اول ۳۰ روز کاملاً رایگان کیفیت سیستم را لمس کنید و سپس تصمیم بگیرید!</p>
            </div>

            <div class="pricing-grid">
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

                    <div class="pricing-card {{ $isFeatured ? 'featured' : '' }}">
                        @if ($isFeatured)
                            <div class="popular-badge">محبوب‌ترین انتخاب مدیران</div>
                        @endif

                        <div class="plan-header" style="position: relative;">
                            <h3 class="plan-name">{{ $plan->name }}</h3>

                            <div class="plan-price-box" style="position: relative; margin-top: 30px;">
                                {{-- قیمت اصلی و خط خورده --}}
                                @if($strikePrice > 0)
                                    <div style="position: absolute; top: -30px; right: 0; width: 100%; text-align: center; display: flex; justify-content: center; align-items: center; gap: 8px;">
                                        <span style="background: #ef4444; color: white; padding: 3px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: bold; letter-spacing: 0.5px;">تخفیف رونمایی</span>
                                        <span style="text-decoration: line-through; color: #9ca3af; font-size: 1.1rem; font-weight: bold;">{{ number_format($strikePrice) }}</span>
                                    </div>
                                @endif

                                <span class="price-number" style="color: {{ $isFeatured ? 'var(--primary)' : '#111827' }};">{{ number_format($displayPrice) }}</span>
                                <span class="price-currency">تومان <small>/ ماهانه</small></span>
                            </div>
                        </div>

                        <ul class="plan-features-list">
                            @foreach ($featuresArr as $feature)
                                <li><i class="fa-solid fa-check"></i> {{ $feature }}</li>
                            @endforeach
                        </ul>

                        <div class="plan-footer">
                            <button class="btn {{ $isFeatured ? 'btn-primary' : 'btn-outline' }} btn-block"
                                onclick="openModal('renewModal')">
                                فعال‌سازی شعبه و ۳۰ روز رایگان
                            </button>
                        </div>
                    </div>
                @empty
                    <p class="empty-state">در حال بروزرسانی تعرفه‌ها...</p>
                @endforelse
            </div>
        </div>
    </section>

    <x-modal id="renewModal" title="ورود به سیستم و فعال‌سازی" subtitle="لطفاً آیدی شعبه مجازی (مینی‌اپلیکیشن) خود را وارد کنید">
        <form action="{{ route('bot.check_renewal') }}" method="POST">
            @csrf
            <div class="form-group">
                <label>آیدی تلگرامی فروشگاه شما (بدون @)</label>
                <input type="text" name="bot_identifier" required placeholder="مثال: MyShop_Bot"
                    style="direction: ltr; text-align: center; font-size: 1.1rem;">
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg mt-4">💳 بررسی و ورود</button>
        </form>
    </x-modal>

</x-layouts.main>
