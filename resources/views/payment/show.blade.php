<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تایید و پرداخت فاکتور</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style> body { font-family: Tahoma, sans-serif; background-color: #f3f4f6; } </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">

    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 border-t-4 border-blue-500">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">پیش‌فاکتور سفارش</h1>
            <p class="text-sm text-gray-500 mt-1">کد پیگیری: {{ $invoice->tracking_code }}</p>
        </div>

        @if(session('error'))
            <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-4 text-sm text-center">
                {{ session('error') }}
            </div>
        @endif

        <div class="space-y-4 mb-6">
            <div class="flex justify-between border-b pb-2">
                <span class="text-gray-600">جمع مبلغ محصولات:</span>
                <span class="font-semibold">{{ number_format($invoice->total_amount) }} تومان</span>
            </div>

            @if($invoice->discount_amount > 0)
            <div class="flex justify-between border-b pb-2 text-green-600">
                <span>سود شما از این خرید:</span>
                <span class="font-semibold">{{ number_format($invoice->discount_amount) }} تومان</span>
            </div>
            @endif

            <div class="flex justify-between border-b pb-2">
                <span class="text-gray-600">هزینه ارسال:</span>
                <span class="font-semibold">{{ $invoice->shipping_cost > 0 ? number_format($invoice->shipping_cost).' تومان' : 'رایگان' }}</span>
            </div>

            <div class="flex justify-between text-lg font-bold text-blue-700 pt-2">
                <span>مبلغ نهایی پرداخت:</span>
                <span>{{ number_format($invoice->final_amount) }} تومان</span>
            </div>
        </div>

        <div class="bg-gray-50 p-4 rounded-lg mb-6 text-sm text-gray-600">
            <p><strong>گیرنده:</strong> {{ $address ? $address->address : 'ثبت نشده' }}</p>
            <p class="mt-1"><strong>تلفن:</strong> {{ $address ? $address->phone : 'ثبت نشده' }}</p>
        </div>

        <form action="{{ route('payment.request', $invoice->id) }}" method="POST">
            @csrf
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-xl transition duration-200 flex justify-center items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                </svg>
                انتقال امن به درگاه پرداخت
            </button>
        </form>
    </div>

</body>
</html>
