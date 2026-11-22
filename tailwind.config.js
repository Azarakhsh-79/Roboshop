import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Vazirmatn', ...defaultTheme.fontFamily.sans], // فرض بر استفاده از فونت وزیرمتن
            },
            colors: {
                // اتصال رنگ‌های تیلویند به متغیرهای CSS
                primary: 'var(--color-primary)',
                'primary-light': 'var(--color-primary-light)',
            },
        },
    },
    plugins: [],
};