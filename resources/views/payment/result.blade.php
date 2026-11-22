<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نتیجه پرداخت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style> body { font-family: Tahoma, sans-serif; background-color: #f3f4f6; } </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">

    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-8 text-center border-t-4 {{ $success ? 'border-green-500' : 'border-red-500' }}">

        @if($success)
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-800 mb-2">پرداخت موفق</h1>
        @else
            <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-800 mb-2">پرداخت ناموفق</h1>
        @endif

        <p class="text-gray-600 mb-6 leading-relaxed">{{ $message }}</p>

        <div class="bg-gray-50 rounded-lg p-4 mb-6 text-sm text-right space-y-2">
            <p>شماره فاکتور: <strong>{{ $invoice->tracking_code }}</strong></p>
            @if($success && isset($refId))
                <p>کد پیگیری: <strong>{{ $refId }}</strong></p>
            @endif
        </div>

        <a href="https://t.me/{{ $invoice->bot->bot_username }}" class="inline-block w-full {{ $success ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-800 hover:bg-gray-900' }} text-white font-bold py-3 px-4 rounded-xl transition duration-200">
            بازگشت به ربات تلگرام
        </a>
    </div>

</body>
</html>
