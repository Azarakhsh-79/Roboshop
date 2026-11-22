<!DOCTYPE html>
<html lang="fa" dir="rtl" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'RoboShop - پلتفرم هوشمند فروشگاهی' }}</title>
    <meta name="description" content="{{ $description ?? 'با روبوشاپ در چند دقیقه یک ربات فروشگاهی حرفه‌ای بساز؛ بدون نیاز به کدنویسی.' }}">
    <meta name="color-scheme" content="light dark">


    {{-- Open Graph (برای پیش‌نمایش لینک تو تلگرام/اینستاگرام) --}}
    <meta property="og:title" content="{{ $title ?? 'RoboShop' }}">
    <meta property="og:description" content="{{ $description ?? 'فروشگاه‌ساز تلگرامی برای کسب‌وکارهای ایرانی.' }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="fa_IR">

    <link rel="icon" href="/favicon.ico" sizes="any">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('style.css?v=' . time()) }}">

    <script>
        // جلوگیری از فلش نور موقع رفرش صفحه‌ی دارک
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
</head>
<body>

    {{ $slot }}

    <script>
        // تنظیم آیکون تم پس از لود شدن کامل المان‌های صفحه
        document.addEventListener('DOMContentLoaded', () => {
            const icon = document.getElementById('themeIcon');
            if (icon && localStorage.getItem('theme') === 'dark') {
                icon.className = 'fa-solid fa-sun';
            }
        });

        // تغییر تم سایت با کلیک
        function toggleTheme() {
            const html = document.documentElement;
            const themeIcon = document.getElementById('themeIcon');
            const currentTheme = html.getAttribute('data-theme');

            if (currentTheme === 'dark') {
                html.setAttribute('data-theme', 'light');
                if (themeIcon) themeIcon.className = 'fa-solid fa-moon';
                localStorage.setItem('theme', 'light');
            } else {
                html.setAttribute('data-theme', 'dark');
                if (themeIcon) themeIcon.className = 'fa-solid fa-sun';
                localStorage.setItem('theme', 'dark');
            }
        }

        // افکت اسکرول منو (Navbar)
        window.addEventListener('scroll', () => {
            const navbar = document.getElementById('navbar');
            if (navbar) {
                if (window.scrollY > 50) navbar.classList.add('scrolled');
                else navbar.classList.remove('scrolled');
            }
        });

        // منوی موبایل (همبرگری)
        // ⚠️ نکته: navbar فعلی‌ت از toggleMobileMenu() (تعریف‌شده داخل خودِ x-navbar) استفاده می‌کنه.
        // این تابع رو نگه داشتم چون شاید صفحه‌ی دیگه‌ای هنوز صداش بزنه؛ اگه هیچ‌جا استفاده نمیشه، بی‌خطر حذفش کن.
        function toggleMenu() {
            const navLinks = document.getElementById('navLinks');
            if (navLinks) navLinks.classList.toggle('active');

            const mobileActions = document.querySelector('.mobile-nav-actions');
            if (mobileActions) {
                mobileActions.style.display = mobileActions.style.display === 'none' ? 'flex' : 'none';
            }
        }

        // باز و بسته کردن مودال‌ها
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) modal.classList.add('active');
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) modal.classList.remove('active');
        }

        // بستن مودال با کلیک در فضای خالی بیرون از باکس
        window.onclick = function (event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>
