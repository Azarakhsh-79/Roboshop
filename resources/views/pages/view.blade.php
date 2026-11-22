<x-layouts.roboshop title="روبوشاپ | فروشگاه تلگرامی هوشمند، مدیریت ساده‌تر فروش" mode="landing"
    :links="[['امکانات', '#features'], ['نحوه کار', '#how'], ['چرا روبوشاپ؟', '#why'], ['تماس با ما', '#contact']]">

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
            فروش آنلاین، بدون دردسر
          </div>
          <h1 class="mb-5 text-5xl font-black leading-[1.5]">
            فروشگاه تلگرامیتو
            <span class="gradient-text block">هوشمند مدیریت کن</span>
          </h1>
          <p class="hero-copy mx-auto mb-8 max-w-2xl text-lg text-slate-300 lg:mx-0">
            روبوشاپ بهت کمک می‌کنه تو تلگرام فروشگاه بزنی و راحت مدیریتش کنی؛ از معرفی محصول و ثبت سفارش گرفته تا پرداخت، فاکتور و موجودی. همه‌چیز یه‌جا و مرتب، تا به‌جای کارهای تکراری، حواست به رشد کسب‌وکارت باشه.
          </p>
          <div class="hero-actions flex flex-wrap justify-center gap-3 lg:justify-start">
            <a href="/create-bot" class="btn btn-primary relative group overflow-hidden">
                <span class="absolute inset-0 w-full h-full -mt-1 rounded-lg opacity-30 bg-gradient-to-b from-transparent via-transparent to-black"></span>
                <span class="relative">✨ ساخت فروشگاه (۳۰ روز رایگان)</span>
            </a>
            <a href="https://t.me/demo_roboshop_bot" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">🚀 دموی روبوشاپ رو امتحان کن</a>
          </div>
          <div class="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-3 text-xs text-slate-400 lg:justify-start">
            <span class="flex items-center gap-2"><span class="text-cyan-300">✓</span> مدیریت فروش یه‌جا</span>
            <span class="flex items-center gap-2"><span class="text-cyan-300">✓</span> پنل تحت وب</span>
            <span class="flex items-center gap-2"><span class="text-cyan-300">✓</span> خرید راحت تو تلگرام</span>
          </div>
        </div>

        <!-- Product illustration / dashboard preview -->
        <div class="relative mx-auto w-full max-w-[510px]">
          <div class="absolute -inset-5 rounded-[2rem] bg-gradient-to-br from-cyan-400/15 to-violet-500/20 blur-2xl" aria-hidden="true"></div>
          <div class="mock-window relative overflow-hidden rounded-[26px] p-4 sm:p-5">
            <div class="mb-5 flex items-center justify-between gap-2 border-b border-white/10 pb-4">
              <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full bg-rose-400"></span><span class="h-2.5 w-2.5 rounded-full bg-amber-300"></span><span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
              </div>
              <span class="text-xs font-bold text-slate-300">پیش‌نمایش پنل مدیریت</span>
              <span class="text-[11px] text-cyan-300">● آنلاین</span>
            </div>
            <div class="mb-4 flex items-center justify-between">
              <div><p class="text-xs text-slate-400">داشبورد فروشگاه</p><h3 class="mt-1 text-lg font-extrabold">خلاصه وضعیت</h3></div>
              <div class="rounded-xl bg-violet-400/10 px-3 py-2 text-xs text-violet-200">امروز</div>
            </div>
            <div class="mb-4 grid grid-cols-2 gap-3">
              <div class="mock-row rounded-2xl p-4"><p class="text-xs text-slate-400">سفارش‌ها</p><p class="mt-2 text-2xl font-black">مدیریت</p><p class="mt-1 text-[11px] text-emerald-300">پیگیری راحت سفارش‌ها</p></div>
              <div class="mock-row rounded-2xl p-4"><p class="text-xs text-slate-400">محصولات</p><p class="mt-2 text-2xl font-black">کنترل</p><p class="mt-1 text-[11px] text-cyan-300">موجودی و قیمت‌ها</p></div>
            </div>
            <div class="mock-row mb-3 rounded-2xl p-4">
              <div class="mb-4 flex items-center justify-between"><span class="text-xs font-bold">آخرین سفارش‌ها</span><span class="text-[11px] text-slate-400">نمونه</span></div>
              <div class="space-y-3 text-xs">
                <div class="flex items-center justify-between"><span class="text-slate-300">سفارش مشتری</span><span class="rounded-full bg-emerald-400/10 px-3 py-1 text-emerald-300">پرداخت‌شده</span></div>
                <div class="flex items-center justify-between"><span class="text-slate-300">سفارش مشتری</span><span class="rounded-full bg-amber-400/10 px-3 py-1 text-amber-200">منتظر پرداخت</span></div>
                <div class="flex items-center justify-between"><span class="text-slate-300">سفارش مشتری</span><span class="rounded-full bg-cyan-400/10 px-3 py-1 text-cyan-200">در حال آماده‌سازی</span></div>
              </div>
            </div>
            <div class="flex items-center justify-between rounded-xl border border-cyan-300/15 bg-cyan-300/[.06] px-4 py-3 text-xs">
              <span class="text-slate-300">فروشگاهت همیشه در دسترسه</span><span class="font-bold text-cyan-200">RoboShop ✦</span>
            </div>
          </div>
          <p class="mt-3 text-center text-[11px] text-slate-500">این تصویر فقط نمونه‌ست و اطلاعاتش واقعی نیست.</p>
        </div>
      </div>
    </section>

    <!-- Pain points -->
    <section class="section-pad border-y border-white/5 bg-white/[.018] py-20">
      <div class="container-x">
        <div class="mx-auto mb-10 max-w-3xl text-center">
          <p class="section-kicker mb-3">دردسرهای فروش آنلاین</p>
          <h2 class="section-title">فروش بیشتر یعنی <span class="gradient-text">کار تکراری بیشتر</span></h2>
          <p class="mt-4 leading-8 muted">وقتی مشتری و سفارش زیاد می‌شه، مدیریت دستی هم وقت‌گیر می‌شه و هم همه‌چیز پخش‌وپلا. روبوشاپ کمکت می‌کنه فرایند فروشت رو منظم‌تر جلو ببری.</p>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <article class="glass feature-card rounded-2xl p-5"><div class="mb-4 text-2xl">💬</div><h3 class="mb-2 font-bold">پیام‌های تکراری مشتری‌ها</h3><p class="text-sm leading-7 muted">یه عالمه پیام درباره قیمت، مشخصات و موجودی که باید هی جواب بدی.</p></article>
          <article class="glass feature-card rounded-2xl p-5"><div class="mb-4 text-2xl">🧾</div><h3 class="mb-2 font-bold">ثبت و پیگیری سفارش</h3><p class="text-sm leading-7 muted">پیدا کردن وضعیت سفارش‌ها و اطلاعات هر خرید کم‌کم سخت می‌شه.</p></article>
          <article class="glass feature-card rounded-2xl p-5"><div class="mb-4 text-2xl">📦</div><h3 class="mb-2 font-bold">کنترل موجودی</h3><p class="text-sm leading-7 muted">باید همیشه حواست باشه موجودی، قیمت و اطلاعات محصولات با هم جور باشه.</p></article>
          <article class="glass feature-card rounded-2xl p-5"><div class="mb-4 text-2xl">📊</div><h3 class="mb-2 font-bold">خبر از وضعیت فروش</h3><p class="text-sm leading-7 muted">سخته که سفارش‌ها، مشتری‌ها و عملکرد فروشگاه رو یه‌جا ببینی.</p></article>
        </div>
      </div>
    </section>

    <!-- Features -->
    <section id="features" class="section-pad py-20">
      <div class="container-x">
        <div class="mb-12 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div class="max-w-2xl"><p class="section-kicker mb-3">امکانات روبوشاپ</p><h2 class="section-title">هر چی برای فروشگاهت لازمه، <span class="gradient-text">یه‌جا</span></h2></div>
          <p class="max-w-md text-sm leading-8 muted">از تجربه خرید مشتری تا مدیریت روزمره فروشگاه؛ همه‌ی امکانات برای این ساخته شدن که کار فروش برات ساده‌تر و منظم‌تر بشه.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <article class="glass feature-card rounded-2xl p-6"><div class="icon-box mb-5">🛍️</div><h3 class="mb-2 text-lg font-extrabold">فروشگاه تلگرامی اختصاصی</h3><p class="text-sm leading-8 muted">محصولاتت رو تو یه محیط ساده و در دسترس نشون بده تا مشتری راحت ببینه و خریدش رو انجام بده.</p></article>
          <article class="glass feature-card rounded-2xl p-6"><div class="icon-box mb-5">📚</div><h3 class="mb-2 text-lg font-extrabold">مدیریت محصولات و دسته‌بندی</h3><p class="text-sm leading-8 muted">مشخصات، قیمت، عکس و موجودی محصولات رو مدیریت کن و کاتالوگت رو همیشه به‌روز نگه دار.</p></article>
          <article class="glass feature-card rounded-2xl p-6"><div class="icon-box mb-5">🛒</div><h3 class="mb-2 text-lg font-extrabold">سبد خرید و ثبت سفارش</h3><p class="text-sm leading-8 muted">مشتری محصولش رو انتخاب می‌کنه و تو یه مسیر مشخص و ساده سفارشش رو ثبت می‌کنه.</p></article>
          <article class="glass feature-card rounded-2xl p-6"><div class="icon-box mb-5">💳</div><h3 class="mb-2 text-lg font-extrabold">پرداخت آنلاین و فاکتور</h3><p class="text-sm leading-8 muted">می‌تونی درگاه پرداخت وصل کنی و برای هر سفارش فاکتور بدی تا خرید شفاف‌تر باشه.</p></article>
          <article class="glass feature-card rounded-2xl p-6"><div class="icon-box mb-5">🧑‍💻</div><h3 class="mb-2 text-lg font-extrabold">پنل مدیریت تحت وب</h3><p class="text-sm leading-8 muted">محصولات، سفارش‌ها، کاربرها و بقیه‌ی بخش‌های فروشگاه رو از تو پنل مدیریت کن.</p></article>
          <article class="glass feature-card rounded-2xl p-6"><div class="icon-box mb-5">📈</div><h3 class="mb-2 text-lg font-extrabold">گزارش و آمار فروش</h3><p class="text-sm leading-8 muted">وضعیت سفارش‌ها و عملکرد فروشگاهت رو ببین تا بدونی کسب‌وکارت دقیقاً چطور پیش می‌ره.</p></article>
          <article class="glass feature-card rounded-2xl p-6"><div class="icon-box mb-5">🎟️</div><h3 class="mb-2 text-lg font-extrabold">تخفیف و کد تشویقی</h3><p class="text-sm leading-8 muted">برای کمپین‌ها و پیشنهادهای ویژه، خیلی راحت کد تخفیف بساز و مدیریتش کن.</p></article>
          <article class="glass feature-card rounded-2xl p-6"><div class="icon-box mb-5">👥</div><h3 class="mb-2 text-lg font-extrabold">مدیریت مشتری‌ها</h3><p class="text-sm leading-8 muted">اطلاعات کاربرها و سابقه‌ی خریدشون دم‌دستت باشه تا ارتباطت با مشتری‌ها منظم‌تر بشه.</p></article>
          <article class="glass feature-card rounded-2xl p-6"><div class="icon-box mb-5">🔔</div><h3 class="mb-2 text-lg font-extrabold">پیگیری وضعیت سفارش‌ها</h3><p class="text-sm leading-8 muted">وضعیت سفارش‌ها رو از پنل چک کن و رسیدگی به خرید مشتری‌ها رو راحت مدیریت کن.</p></article>
        </div>
      </div>
    </section>

    <!-- How it works -->
    <section id="how" class="section-pad border-y border-white/5 bg-white/[.018] py-20">
      <div class="container-x">
        <div class="mx-auto mb-12 max-w-2xl text-center"><p class="section-kicker mb-3">شروع کار</p><h2 class="section-title">از راه‌اندازی تا مدیریت فروشگاه، <span class="gradient-text">قدم‌به‌قدم</span></h2><p class="mt-4 leading-8 muted">استفاده از روبوشاپ ساده‌ست و خیلی زود دستت میاد.</p></div>
        <div class="grid gap-4 md:grid-cols-3 mb-10">
          <article class="glass step-card rounded-2xl p-6 text-center"><div class="mx-auto mb-4 grid h-12 w-12 place-items-center rounded-2xl bg-cyan-300/10 text-xl font-black text-cyan-200">۱</div><h3 class="mb-2 text-lg font-extrabold">راه‌اندازی فروشگاه</h3><p class="text-sm leading-8 muted">برای گرفتن راهنمای شروع و راه‌اندازی فروشگاه تلگرامیت، باهامون در تماس باش یا خودت با یک کلیک بساز.</p></article>
          <article class="glass step-card rounded-2xl p-6 text-center"><div class="mx-auto mb-4 grid h-12 w-12 place-items-center rounded-2xl bg-violet-300/10 text-xl font-black text-violet-200">۲</div><h3 class="mb-2 text-lg font-extrabold">ثبت محصولات و تنظیمات</h3><p class="text-sm leading-8 muted">اطلاعات محصولات، قیمت‌ها و تنظیمات فروشگاهت رو کامل کن.</p></article>
          <article class="glass step-card rounded-2xl p-6 text-center"><div class="mx-auto mb-4 grid h-12 w-12 place-items-center rounded-2xl bg-cyan-300/10 text-xl font-black text-cyan-200">۳</div><h3 class="mb-2 text-lg font-extrabold">شروع فروش و مدیریت</h3><p class="text-sm leading-8 muted">دموی ربات رو ببین، تجربه‌ی مشتری رو از نزدیک حس کن و سفارش‌ها رو از پنل مدیریت کن.</p></article>
        </div>

        <!-- دکمه ساخت فروشگاه اضافه شده در این بخش -->
        <div class="flex justify-center mt-8">
            <div class="relative group">
                <div class="absolute -inset-1 rounded-xl bg-gradient-to-r from-cyan-400 to-violet-500 opacity-70 blur transition duration-200 group-hover:opacity-100"></div>
                <a href="/create-bot" class="relative inline-flex items-center justify-center rounded-xl bg-slate-900 px-8 py-4 text-lg font-bold text-white transition-all hover:bg-slate-800">
                    <span class="mr-2">✨</span>
                    ساخت فروشگاه تلگرامی
                    <span class="ml-3 rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-semibold text-emerald-300 border border-emerald-500/30">۳۰ روز رایگان</span>
                </a>
            </div>
        </div>

      </div>
    </section>

    <!-- Why -->
    <section id="why" class="section-pad py-20">
      <div class="container-x grid items-center gap-12 lg:grid-cols-2">
        <div>
          <p class="section-kicker mb-3">چرا روبوشاپ؟</p>
          <h2 class="section-title mb-5">به‌جای مدیریت پراکنده، <span class="gradient-text">یه مسیر منظم برای فروش</span></h2>
          <p class="mb-7 leading-8 muted">روبوشاپ مخصوص فروشگاه‌های تلگرامی ساخته شده و ابزارهای اصلی فروش و مدیریت رو کنار هم گذاشته. هدفمون اینه که کار فروشنده راحت‌تر بشه و مشتری هم مسیر خریدش رو شفاف ببینه.</p>
          <div class="space-y-4">
            <div class="flex items-start gap-3"><span class="mt-1 text-lg text-cyan-300">✓</span><div><h3 class="font-bold">خرید راحت‌تر برای مشتری</h3><p class="mt-1 text-sm leading-7 muted">محصولات رو ببینه و سفارشش رو همون‌جا تو تلگرام ثبت کنه.</p></div></div>
            <div class="flex items-start gap-3"><span class="mt-1 text-lg text-cyan-300">✓</span><div><h3 class="font-bold">مدیریت یکپارچه برای فروشنده</h3><p class="mt-1 text-sm leading-7 muted">بخش‌های مهم فروشگاه رو از پنل تحت وب مدیریت کن.</p></div></div>
            <div class="flex items-start gap-3"><span class="mt-1 text-lg text-cyan-300">✓</span><div><h3 class="font-bold">مناسب هر اندازه‌ای از کسب‌وکار</h3><p class="mt-1 text-sm leading-7 muted">صفحه‌ی معرفی و پنل رو از موبایل، تبلت و کامپیوتر می‌تونی باز کنی.</p></div></div>
          </div>
        </div>
        <div class="glass rounded-[28px] p-6 sm:p-9">
          <div class="mb-6 flex items-center gap-3"><div class="icon-box">✦</div><div><p class="text-xs text-cyan-200">ROBO SHOP</p><h3 class="text-xl font-black">فروشگاهت رو حرفه‌ای‌تر مدیریت کن</h3></div></div>
          <p class="mb-7 text-sm leading-8 muted">پیشنهاد ما اینه که قبل از هر تصمیمی اول دموی روبوشاپ رو امتحان کنی. با محیط ربات و مراحل ثبت سفارش آشنا شو و بعدش برای اطلاعات بیشتر یا راه‌اندازی فروشگاه بهمون پیام بده.</p>
          <a href="https://t.me/demo_roboshop_bot" target="_blank" rel="noopener noreferrer" class="btn btn-primary w-full">دموی فروشگاه رو ببین <span aria-hidden="true">←</span></a>
          <p class="mt-4 text-center text-xs text-slate-500">دمو رو تو تلگرام باز کن و بخش‌های مختلفش رو بگرد.</p>
        </div>
      </div>
    </section>

    <!-- Contact -->
    <section id="contact" class="section-pad border-t border-white/5 bg-[#0b0b20] py-20">
      <div class="container-x">
        <div class="mb-10 text-center"><p class="section-kicker mb-3">کنارتیم</p><h2 class="section-title">باهامون <span class="gradient-text">در ارتباط باش</span></h2><p class="mt-3 text-sm leading-8 muted">سؤالی داری، راهنمایی می‌خوای، می‌خوای بیشتر با امکانات آشنا بشی یا همکاری رو شروع کنی؟ از هر راهی که راحت‌تری باهامون حرف بزن.</p></div>
        <div class="contact-grid mx-auto grid max-w-4xl gap-4 sm:grid-cols-2">
          <a href="tel:09334590631" class="glass contact-card flex items-center gap-4 rounded-2xl p-5 no-underline">
            <span class="icon-box">☎️</span><span class="min-w-0"><span class="mb-1 block text-xs muted">تماس تلفنی</span><strong class="contact-value block text-sm text-white">09334590631</strong></span><span class="mr-auto text-slate-500">←</span>
          </a>
          <a href="https://t.me/Amir_soleimani_79" target="_blank" rel="noopener noreferrer" class="glass contact-card flex items-center gap-4 rounded-2xl p-5 no-underline">
            <span class="icon-box">✈️</span><span class="min-w-0"><span class="mb-1 block text-xs muted">تلگرام پشتیبانی</span><strong class="contact-value block text-sm text-white">@@Amir_soleimani_79</strong></span><span class="mr-auto text-slate-500">←</span>
          </a>
          <a href="https://t.me/RoboShop_fatherbot" target="_blank" rel="noopener noreferrer" class="glass contact-card flex items-center gap-4 rounded-2xl p-5 no-underline">
            <span class="icon-box">🤖</span><span class="min-w-0"><span class="mb-1 block text-xs muted">ربات اصلی روبوشاپ</span><strong class="contact-value block text-sm text-white">@@RoboShop_fatherbot</strong></span><span class="mr-auto text-slate-500">←</span>
          </a>
          <a href="https://t.me/demo_roboshop_bot" target="_blank" rel="noopener noreferrer" class="glass contact-card flex items-center gap-4 rounded-2xl p-5 no-underline">
            <span class="icon-box">🛍️</span><span class="min-w-0"><span class="mb-1 block text-xs muted">ربات دموی فروشگاه</span><strong class="contact-value block text-sm text-white">@@demo_roboshop_bot</strong></span><span class="mr-auto text-slate-500">←</span>
          </a>
          <a href="https://roboshopapp.ir" target="_blank" rel="noopener noreferrer" class="glass contact-card flex items-center gap-4 rounded-2xl p-5 no-underline">
            <span class="icon-box">🌐</span><span class="min-w-0"><span class="mb-1 block text-xs muted">وب‌سایت رسمی</span><strong class="contact-value block text-sm text-white">roboshopapp.ir</strong></span><span class="mr-auto text-slate-500">←</span>
          </a>
          <a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer" class="glass contact-card flex items-center gap-4 rounded-2xl p-5 no-underline">
            <span class="icon-box">📸</span><span class="min-w-0"><span class="mb-1 block text-xs muted">اینستاگرام روبوشاپ</span><strong class="block text-sm text-white">صفحه رسمی اینستاگرام</strong></span><span class="mr-auto text-slate-500">←</span>
          </a>
        </div>
        <div class="mt-9 text-center">
          <p class="mb-2 text-sm font-bold">امیر سلیمانی</p>
          <p class="text-xs muted">توسعه‌دهنده و مدیر روبوشاپ</p>
        </div>
      </div>
    </section>
  </main>

</x-layouts.roboshop>
