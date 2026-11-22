@props(['title' => 'روبوشاپ', 'links' => [], 'mode' => 'home'])
@php
    $isLanding = $mode === 'landing';
    $authUser = auth()->user();
    $authName = $authUser?->name ?? 'کاربر';
    $authInitial = mb_substr($authName, 0, 1);
    $switchHref = $isLanding ? url('/') : url('/veiw');
    $switchText = $isLanding ? 'خرید اشتراک و تعرفه‌ها' : 'معرفی روبوشاپ';

    if ($isLanding) {
        $ctaHref = 'https://t.me/demo_roboshop_bot';
        $ctaShort = 'دمو';
        $ctaLong = '🚀 دموی روبوشاپ رو ببین';
    } elseif (auth()->check()) {
        if (auth()->user()->shops()->exists()) {
            $ctaHref = route('panel.gateway');
            $ctaShort = 'داشبورد';
            $ctaLong = '📊 ورود به داشبورد مدیریت';
        } else {
            $ctaHref = route('bot.create');
            $ctaShort = 'راه‌اندازی';
            $ctaLong = '🪄 راه‌اندازی شعبه مجازی من';
        }
    } else {
        $ctaHref = route('login');
        $ctaShort = 'شروع رایگان';
        $ctaLong = '🚀 شروع ۳۰ روز تست رایگان';
    }
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#080817">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="description" content="روبوشاپ؛ فروشگاه تلگرامی خودتو راحت راه بنداز و مدیریت کن. محصولات، موجودی، سفارش‌ها، پرداخت آنلاین، فاکتور و مشتری‌ها، همه تو یه پنل.">
  <title>{{ $title }}</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
    :root {
      color-scheme: dark;
      --bg: #080817;
      --panel: rgba(17, 17, 39, .72);
      --line: rgba(255,255,255,.09);
      --cyan: #42e8ee;
      --violet: #a78bfa;
      --muted: #a6a6bd;
    }
    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; scroll-padding-top: 88px; -webkit-text-size-adjust: 100%; }
    body {
      margin: 0; min-width: 320px; overflow-x: hidden; font-family: Vazirmatn, sans-serif;
      background:
        radial-gradient(ellipse at 80% 0%, rgba(100,70,220,.18), transparent 36rem),
        radial-gradient(ellipse at 0% 42%, rgba(0,210,220,.09), transparent 34rem),
        var(--bg);
      color: #f8f8ff;
    }
    a, button { -webkit-tap-highlight-color: transparent; }
    .container-x { width: 100%; max-width: 1160px; padding-left: 20px; padding-right: 20px; margin-inline: auto; }
    .glass { background: var(--panel); border: 1px solid var(--line); backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px); }
    .gradient-text { background: linear-gradient(100deg, #4ce8ec 0%, #8bbcff 48%, #c4a1ff 100%); color: transparent; background-clip: text; -webkit-background-clip: text; }
    .glow { position: absolute; width: 420px; height: 420px; border-radius: 50%; filter: blur(90px); opacity: .17; pointer-events: none; }
    .hero-grid { background-image: linear-gradient(rgba(255,255,255,.035) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.035) 1px, transparent 1px); background-size: 44px 44px; mask-image: linear-gradient(to bottom, black, transparent 85%); -webkit-mask-image: linear-gradient(to bottom, black, transparent 85%); }
    .feature-card, .contact-card, .step-card { transition: transform .22s ease, border-color .22s ease, background .22s ease; }
    .feature-card:hover, .contact-card:hover, .step-card:hover { transform: translateY(-4px); border-color: rgba(66,232,238,.35); background-color: rgba(24,25,54,.86); }
    .btn { display: inline-flex; min-height: 52px; align-items: center; justify-content: center; gap: 10px; border-radius: 15px; padding: 13px 22px; font-weight: 800; text-decoration: none; text-align: center; transition: transform .2s, filter .2s, border-color .2s; }
    .btn:active { transform: scale(.98); }
    .btn-primary { color: #06131b; background: linear-gradient(105deg,#52edf0,#72b9ff); box-shadow: 0 10px 35px rgba(45,210,230,.19); }
    .btn-primary:hover { filter: brightness(1.08); }
    .btn-secondary { color: #e8e8f5; border: 1px solid rgba(255,255,255,.16); background: rgba(255,255,255,.045); }
    .btn-secondary:hover { border-color: rgba(66,232,238,.55); }
    .section-kicker { color: var(--cyan); font-size: .78rem; font-weight: 800; letter-spacing: .12em; }
    .section-title { font-size: clamp(1.65rem, 4vw, 2.55rem); line-height: 1.55; font-weight: 900; }
    .muted { color: var(--muted); }
    .icon-box { width: 48px; height: 48px; display: grid; place-items: center; border-radius: 14px; background: rgba(66,232,238,.09); border: 1px solid rgba(66,232,238,.17); font-size: 1.35rem; flex: 0 0 auto; }
    .nav-link { color: #c7c7d7; text-decoration: none; font-size: .88rem; transition: color .2s; }
    .nav-link:hover { color: var(--cyan); }
    .contact-value { direction: ltr; text-align: left; overflow-wrap: anywhere; }
    .mock-window { background: linear-gradient(145deg,rgba(28,29,63,.96),rgba(12,13,31,.96)); border: 1px solid rgba(130,130,210,.25); box-shadow: 0 30px 90px rgba(0,0,0,.4), 0 0 60px rgba(88,90,220,.12); }
    .mock-row { background: rgba(255,255,255,.045); border: 1px solid rgba(255,255,255,.055); }
    .dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
    :focus-visible { outline: 3px solid rgba(66,232,238,.85); outline-offset: 3px; }

    /* هدر: رعایت ناچ و نوار وضعیت گوشی */
    header.site-header { padding-top: env(safe-area-inset-top, 0px); }

    /* دکمه همبرگری */
    #mobile-menu-btn {
      display: grid; place-items: center; width: 44px; height: 44px; flex: 0 0 auto;
      border-radius: 12px; border: 1px solid rgba(255,255,255,.12); background: rgba(255,255,255,.05);
      color: #fff; cursor: pointer; transition: background .2s, border-color .2s;
    }
    #mobile-menu-btn:active { background: rgba(255,255,255,.1); }
    #mobile-menu-btn[aria-expanded="true"] { border-color: rgba(66,232,238,.5); }
    #mobile-menu-btn .icon-close { display: none; }
    #mobile-menu-btn[aria-expanded="true"] .icon-open { display: none; }
    #mobile-menu-btn[aria-expanded="true"] .icon-close { display: block; }

    /* منوی موبایل */
    #mobile-menu { display: none; max-height: calc(100vh - 72px); max-height: calc(100dvh - 72px); overflow-y: auto; background-color: #080817; background-image: linear-gradient(rgba(255,255,255,.02), rgba(255,255,255,.02)); box-shadow: 0 24px 40px rgba(0,0,0,.55); }
    #mobile-menu.open { display: flex; animation: menuIn .2s ease-out; }
    @@keyframes menuIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: none; } }
    #mobile-menu .nav-link { min-height: 52px; display: flex; align-items: center; justify-content: space-between; padding: 0 6px; font-size: 1.02rem; }
    #mobile-menu .nav-link::after { content: "←"; color: #5c5c78; }

    @@media (min-width: 768px) {
      #mobile-menu, #mobile-menu.open { display: none; }
      #mobile-menu-btn { display: none; }
    }
    @@media (max-width: 767px) {
      .desktop-nav { display: none !important; }
    }
    @@media (max-width: 768px) {
      html { scroll-padding-top: 76px; }
      .container-x { padding-left: 16px; padding-right: 16px; }
      .hero { padding-top: 40px !important; padding-bottom: 56px !important; }
      .hero-grid { background-size: 30px 30px; }
      .hero h1 { font-size: clamp(2rem, 9.5vw, 3rem); line-height: 1.55; }
      .hero-copy { font-size: .97rem; line-height: 2.05; }
      .hero-actions { display: grid; grid-template-columns: 1fr; }
      .hero-actions .btn { width: 100%; }
      .glow { width: 260px; height: 260px; }
      .mock-window { margin-top: 8px; }
      .section-pad { padding-block: 56px; }
      .section-title { line-height: 1.6; }
      .feature-card { padding: 18px; }
      .feature-card:hover, .contact-card:hover, .step-card:hover { transform: none; }
      .contact-grid { grid-template-columns: 1fr; }
      .contact-card { padding: 16px; min-height: 76px; }
      .footer-inner { text-align: center; }
      footer { padding-bottom: calc(24px + env(safe-area-inset-bottom, 0px)); }
    }
    @@media (prefers-reduced-motion: reduce) {
      *,*::before,*::after { scroll-behavior: auto !important; transition-duration: .01ms !important; animation-duration: .01ms !important; }
    }


    /* ===== اضافه‌شده برای صفحه اصلی و لایه مشترک ===== */
    .alert-stack { position: fixed; top: calc(76px + env(safe-area-inset-top, 0px)); left: 16px; right: 16px; z-index: 70; display: flex; flex-direction: column; gap: 10px; align-items: center; pointer-events: none; }
    .alert { pointer-events: auto; width: 100%; max-width: 560px; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; border-radius: 14px; font-size: .9rem; line-height: 1.9; backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); box-shadow: 0 16px 40px rgba(0,0,0,.4); animation: menuIn .25s ease-out; }
    .alert-success { background: rgba(16,185,129,.16); border: 1px solid rgba(52,211,153,.45); color: #a7f3d0; }
    .alert-info { background: rgba(66,232,238,.12); border: 1px solid rgba(66,232,238,.4); color: #b8f6f8; }
    .alert-error { background: rgba(244,63,94,.16); border: 1px solid rgba(251,113,133,.45); color: #fecdd3; }
    .alert button { flex: 0 0 auto; width: 32px; height: 32px; border-radius: 10px; border: 0; background: rgba(255,255,255,.08); color: inherit; font-size: 1.1rem; cursor: pointer; }

    .blog-card { overflow: hidden; border-radius: 20px; cursor: pointer; display: flex; flex-direction: column; }
    .blog-image { aspect-ratio: 16 / 9; background: rgba(255,255,255,.04); overflow: hidden; }
    .blog-image img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s ease; }
    .blog-card:hover .blog-image img { transform: scale(1.04); }
    .blog-image-placeholder { width: 100%; height: 100%; display: grid; place-items: center; font-size: 2.4rem; }
    .blog-content { padding: 18px 20px 22px; display: flex; flex-direction: column; gap: 8px; flex: 1; }
    .blog-meta { font-size: .75rem; color: #8b8ba7; }
    .blog-title { font-weight: 800; font-size: 1.05rem; line-height: 1.8; }
    .blog-excerpt { font-size: .86rem; line-height: 1.95; color: var(--muted); }
    .read-more { margin-top: auto; padding-top: 6px; font-size: .85rem; font-weight: 800; color: var(--cyan); }
    .empty-state { grid-column: 1 / -1; text-align: center; color: var(--muted); padding: 28px 0; }

    .pricing-card { position: relative; display: flex; flex-direction: column; gap: 22px; padding: 30px 24px 24px; border-radius: 24px; }
    .pricing-card.featured { border-color: rgba(66,232,238,.55); background: linear-gradient(160deg, rgba(36,40,84,.85), rgba(17,17,39,.85)); box-shadow: 0 0 0 1px rgba(66,232,238,.15), 0 24px 70px rgba(66,232,238,.12); }
    .popular-badge { position: absolute; top: -14px; left: 50%; transform: translateX(-50%); white-space: nowrap; padding: 5px 14px; border-radius: 999px; font-size: .74rem; font-weight: 800; color: #06131b; background: linear-gradient(105deg,#52edf0,#72b9ff); }
    .plan-name { font-size: 1.3rem; font-weight: 900; text-align: center; }
    .plan-price-box { margin-top: 14px; text-align: center; }
    .strike-row { display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 8px; }
    .discount-tag { background: #ef4444; color: #fff; padding: 3px 10px; border-radius: 12px; font-size: .72rem; font-weight: 800; }
    .strike-price { text-decoration: line-through; color: #8b8ba7; font-size: 1.05rem; font-weight: 700; }
    .price-number { font-size: 2.5rem; font-weight: 900; line-height: 1.3; }
    .price-currency { display: block; font-size: .85rem; color: var(--muted); }
    .price-currency small { font-size: .75rem; }
    .plan-features-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 12px; font-size: .9rem; line-height: 1.8; color: #d6d6e6; }
    .plan-features-list li { display: flex; gap: 10px; align-items: flex-start; }
    .plan-features-list li::before { content: "✓"; color: var(--cyan); font-weight: 900; flex: 0 0 auto; }
    .plan-footer { margin-top: auto; }
    .btn-block { width: 100%; }

    .modal { position: fixed; inset: 0; z-index: 80; display: none; align-items: center; justify-content: center; padding: 20px; background: rgba(4,4,12,.72); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); }
    .modal.open { display: flex; animation: menuIn .2s ease-out; }
    .modal-box { position: relative; width: 100%; max-width: 440px; border-radius: 24px; padding: 28px 22px 24px; background: #0f1030; border: 1px solid rgba(130,130,210,.28); box-shadow: 0 30px 90px rgba(0,0,0,.6); }
    .modal-close { position: absolute; top: 14px; left: 14px; width: 40px; height: 40px; border-radius: 12px; border: 1px solid rgba(255,255,255,.12); background: rgba(255,255,255,.05); color: #fff; font-size: 1.2rem; cursor: pointer; }
    .modal-title { font-size: 1.25rem; font-weight: 900; margin: 6px 0 6px; padding-inline: 44px; text-align: center; }
    .modal-subtitle { font-size: .86rem; line-height: 1.9; color: var(--muted); text-align: center; margin-bottom: 20px; }
    .form-group label { display: block; font-size: .85rem; font-weight: 700; margin-bottom: 8px; color: #d6d6e6; }
    .form-group input { width: 100%; min-height: 52px; padding: 12px 14px; border-radius: 14px; border: 1px solid rgba(255,255,255,.16); background: rgba(255,255,255,.05); color: #fff; font-family: inherit; }
    .form-group input:focus { outline: none; border-color: rgba(66,232,238,.7); }
    .form-group input::placeholder { color: #6e6e8c; }

    .nav-link-switch { color: var(--cyan); font-weight: 700; }
    .mock-chart { height: 74px; border-radius: 14px; margin-bottom: 12px; background: linear-gradient(180deg, rgba(66,232,238,.16), transparent), repeating-linear-gradient(90deg, rgba(66,232,238,.35) 0 6px, transparent 6px 20px); -webkit-mask-image: linear-gradient(to top, black 30%, transparent); mask-image: linear-gradient(to top, black 30%, transparent); }

  </style>
</head>

<body>
  <!-- Header -->
  <header class="site-header sticky top-0 z-50 border-b border-white/5 bg-[#080817]/90 backdrop-blur-xl">
    <div class="container-x flex h-[64px] items-center justify-between gap-3 md:h-[72px]">
      <a href="#home" class="flex min-w-0 items-center gap-2.5 no-underline" aria-label="روبوشاپ، صفحه اصلی">
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-cyan-300/30 bg-cyan-300/10 text-xl">✦</span>
        <span class="min-w-0">
          <strong class="block text-lg font-black leading-tight">روبوشاپ</strong>
          <span class="block text-[9px] font-bold tracking-[.24em] text-cyan-300">ROBOSHOP</span>
        </span>
      </a>

      <!-- ناوبری دسکتاپ -->
      <nav class="desktop-nav flex items-center gap-7" aria-label="ناوبری اصلی">
        @foreach ($links as $link)
          <a class="nav-link" href="{{ $link[1] }}">{{ $link[0] }}</a>
        @endforeach
        <a class="nav-link nav-link-switch" href="{{ $switchHref }}">{{ $switchText }}</a>
      </nav>

      <div class="flex shrink-0 items-center gap-2.5">
        <!-- ورود / کاربر (دسکتاپ) -->
        <div class="hidden items-center gap-2 md:flex">
          @auth
            <span class="flex max-w-[190px] items-center gap-2 rounded-xl border border-white/10 bg-white/5 py-1.5 pl-3 pr-1.5 text-sm font-bold" title="{{ $authName }}">
              <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-cyan-300/15 text-sm font-black text-cyan-200">{{ $authInitial }}</span>
              <span class="truncate">{{ $authName }}</span>
            </span>
            <form method="POST" action="{{ route('logout') }}" class="m-0">
              @csrf
              <button type="submit" class="btn btn-secondary !min-h-[44px] !rounded-xl !px-4 !py-2 !text-sm">خروج</button>
            </form>
          @else
            <a href="{{ route('login') }}" class="btn btn-secondary !min-h-[44px] !rounded-xl !px-4 !py-2 !text-sm">ورود</a>
          @endauth
        </div>

        <a href="{{ $ctaHref }}" @if($isLanding) target="_blank" rel="noopener noreferrer" @endif class="btn btn-primary !min-h-[44px] !rounded-xl !px-4 !py-2 !text-sm">{{ $ctaShort }} <span aria-hidden="true">←</span></a>

        <!-- دکمه منوی موبایل -->
        <button id="mobile-menu-btn" type="button" aria-label="باز کردن منو" aria-expanded="false" aria-controls="mobile-menu">
          <svg class="icon-open h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
          </svg>
          <svg class="icon-close h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6l12 12M18 6L6 18"></path>
          </svg>
        </button>
      </div>
    </div>

    <!-- منوی موبایل -->
    <div id="mobile-menu" class="absolute left-0 top-full z-50 w-full flex-col border-b border-white/5 px-5 pb-5 pt-2">
      <nav class="flex flex-col" aria-label="منوی موبایل">
        @foreach ($links as $link)
          <a class="nav-link border-b border-white/5" href="{{ $link[1] }}">{{ $link[0] }}</a>
        @endforeach
        <a class="nav-link nav-link-switch border-b border-white/5" href="{{ $switchHref }}">{{ $switchText }}</a>
        @auth
          <div class="mt-4 flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 p-3">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-cyan-300/15 text-lg font-black text-cyan-200">{{ $authInitial }}</span>
            <span class="min-w-0 flex-1">
              <span class="block text-[11px] text-slate-400">وارد شدی با نام</span>
              <strong class="block truncate text-sm">{{ $authName }}</strong>
            </span>
            <form method="POST" action="{{ route('logout') }}" class="m-0">
              @csrf
              <button type="submit" class="btn btn-secondary !min-h-[44px] !rounded-xl !px-4 !py-2 !text-sm">خروج</button>
            </form>
          </div>
        @else
          <a href="{{ route('login') }}" class="btn btn-secondary mt-4 w-full">ورود به حساب</a>
        @endauth
        <a href="{{ $ctaHref }}" @if($isLanding) target="_blank" rel="noopener noreferrer" @endif class="btn btn-primary mt-5 w-full">{{ $ctaLong }}</a>
      </nav>
    </div>
  </header>

  {{ $slot }}

  <footer class="border-t border-white/5 py-6">
    <div class="container-x footer-inner flex flex-col items-center justify-between gap-3 sm:flex-row">
      <p class="text-xs leading-7 text-slate-500">تمامی حقوق مادی و معنوی برای پلتفرم روبوشاپ محفوظ است © ۲۰۲۶</p>
      <div class="flex items-center gap-5 text-xs font-bold">
        <a href="{{ $switchHref }}" class="text-cyan-300 no-underline">{{ $switchText }}</a>
        <a href="#home" class="text-cyan-300 no-underline">بالای صفحه ↑</a>
      </div>
    </div>
  </footer>

  <script>
    const btn = document.getElementById('mobile-menu-btn');
    const menu = document.getElementById('mobile-menu');

    function toggleMenu(forceClose) {
      const open = forceClose === true ? false : !menu.classList.contains('open');
      menu.classList.toggle('open', open);
      btn.setAttribute('aria-expanded', String(open));
      btn.setAttribute('aria-label', open ? 'بستن منو' : 'باز کردن منو');
    }

    btn.addEventListener('click', function () { toggleMenu(); });
    menu.addEventListener('click', function (e) { if (e.target.closest('a')) toggleMenu(true); });
    document.addEventListener('click', function (e) {
      if (menu.classList.contains('open') && !menu.contains(e.target) && !btn.contains(e.target)) toggleMenu(true);
    });
    window.addEventListener('resize', function () { if (window.innerWidth >= 768) toggleMenu(true); });

    function openModal(id) {
      const m = document.getElementById(id);
      if (!m) return;
      m.classList.add('open');
      document.body.style.overflow = 'hidden';
      const input = m.querySelector('input:not([type=hidden])');
      if (input) setTimeout(function () { input.focus(); }, 60);
    }
    function closeModal(id) {
      const m = document.getElementById(id);
      if (!m) return;
      m.classList.remove('open');
      document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      if (menu.classList.contains('open')) { toggleMenu(true); btn.focus(); }
      document.querySelectorAll('.modal.open').forEach(function (m) { closeModal(m.id); });
    });

    // پیام‌های فلش بعد از چند ثانیه خودکار بسته می‌شن
    document.querySelectorAll('.alert').forEach(function (a) {
      setTimeout(function () { a.remove(); }, 7000);
    });
  </script>
</body>
</html>
