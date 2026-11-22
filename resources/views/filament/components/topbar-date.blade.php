<div class="hidden md:flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 me-4">
    <svg class="w-5 h-5 text-gray-400 dark:text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
    </svg>

    <span class="font-medium">
        {{-- تبدیل کامل به شمسی (مثلاً: سه‌شنبه، ۲۶ خرداد ۱۴۰۵) --}}
        {{ verta()->format('l، j F Y') }}
    </span>

    <span class="border-l border-gray-300 dark:border-gray-700 h-4 mx-1"></span>

    <span class="font-medium tabular-nums text-gray-900 dark:text-gray-200" dir="ltr">
        {{-- ساعت دقیق به وقت تهران --}}
        {{ verta()->format('H:i') }}
    </span>
</div>
