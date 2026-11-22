<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PanelGatewayController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SubscriptionPaymentController;
use App\Http\Controllers\InvoiceController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\MiniAppController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::post('/check-renewal', [HomeController::class, 'checkRenewal'])
    ->name('bot.check_renewal');


Route::get('/veiw', function () {
    return view('pages.view'); // فایل در مسیر resources/views/pages/view.blade.php قرار می‌گیرد
});

Route::get('/invoice/{invoice}/pdf', [InvoiceController::class, 'streamPdf'])->name('invoice.pdf');
/*
|--------------------------------------------------------------------------
| Invoice Payment
|--------------------------------------------------------------------------
*/

Route::middleware('signed')->group(function () {
    Route::get('/payment/invoice/{invoice}', [PaymentController::class, 'show'])
        ->name('payment.show');

    Route::get('/subscription/payment/{payment}', [SubscriptionPaymentController::class, 'pay'])
        ->name('subscription.payment');

    Route::get('/magic-login/{user}', function (Request $request, User $user) {
        abort_unless(
            $request->hasValidSignature(),
            403,
            'لینک ورود شما منقضی شده یا نامعتبر است. لطفاً از ربات لینک جدیدی دریافت کنید.'
        );

        // ۱. لاگین کردن کاربر با پارامتر true (برای فعال‌سازی Remember Me)
        Auth::login($user, true);

        // ۲. بازسازی سشن (بسیار مهم برای مرورگر تلگرام جهت جلوگیری از گم شدن لاگین)
        $request->session()->regenerate();

        // ۳. هدایت به صفحه Gateway به جای پنل مستقیم
        // سیستم اتوماتیک فروشگاه کاربر را تنظیم کرده و خودش او را به پنل می‌برد
        return redirect()->route('panel.gateway');

    })->name('bot.magic.login');

    Route::get('/panel/magic-login/{bot_id}', function ($bot_id) {
        // بررسی امنیتی (فقط سوپرادمین می‌تواند این مسیر را فراخوانی کند)
        // فرض می‌کنیم شماره موبایل شما سوپرادمین است، یا از یک Middleware سوپرادمین استفاده کنید
        if (!Auth::check() || Auth::user()->phone !== '09334590631') { // شماره موبایل خودتان را جایگزین کنید
            abort(403, 'دسترسی غیرمجاز');
        }

        $bot = ManagedBot::findOrFail($bot_id);

        // سشن فروشگاه فعلی را روی ربات انتخاب شده تنظیم کن
        session(['current_shop_id' => $bot->id]);

        // انتقال به پنل مدیریت
        return redirect()->route('panel.gateway');
    })->name('panel.magic_login')->middleware('web');
});

Route::post('/payment/request/{invoice}', [PaymentController::class, 'request'])
    ->name('payment.request');

Route::get('/payment/callback', [PaymentController::class, 'callback'])
    ->name('payment.callback');

Route::get('/subscription/verify', [SubscriptionPaymentController::class, 'verify'])
    ->name('subscription.verify');

/*
|--------------------------------------------------------------------------
| Guest Routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'sendOtp'])->name('login.send');

    Route::get('/verify', [AuthController::class, 'showVerify'])->name('verify');
    Route::post('/verify', [AuthController::class, 'verifyOtp'])->name('verify.check');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])
        ->name('logout');

    // Gateway
    Route::get('/gateway', [PanelGatewayController::class, 'index'])
        ->name('panel.gateway');

    Route::post('/gateway/select/{id}', [PanelGatewayController::class, 'select'])
        ->name('panel.select');

    // Create Bot
    Route::get('/create-bot', [HomeController::class, 'showCreateBotForm'])
        ->name('bot.create');

    Route::post('/create-bot', [HomeController::class, 'registerBot'])
        ->name('bot.register');
});

/*
|--------------------------------------------------------------------------
| Development
|--------------------------------------------------------------------------
*/

Route::get('/clear-all-cache', function () {

    $commands = [
        'route:clear' => 'Route Cache',
        'config:clear' => 'Config Cache',
        'cache:clear' => 'Application Cache',
        'view:clear' => 'View Cache',
        'filament:optimize-clear' => 'Filament Cache',
        'optimize:clear' => 'Laravel Optimize Cache',
        'livewire:discover' => 'livewire'

    ];

    $results = [];
    $startedAt = microtime(true);

    foreach ($commands as $command => $title) {

        $commandStartedAt = microtime(true);

        try {

            $exitCode = Artisan::call($command);

            $output = trim(Artisan::output());

            $results[] = [
                'command' => $command,
                'title' => $title,
                'status' => $exitCode === 0 ? 'success' : 'failed',
                'exit_code' => $exitCode,
                'message' => $output ?: 'بدون خروجی',
                'duration' => round(microtime(true) - $commandStartedAt, 3) . 's',
            ];

        } catch (\Throwable $e) {

            $results[] = [
                'command' => $command,
                'title' => $title,
                'status' => 'error',
                'exit_code' => null,
                'message' => $e->getMessage(),
                'duration' => round(microtime(true) - $commandStartedAt, 3) . 's',
            ];
        }
    }

    $success = collect($results)->where('status', 'success')->count();
    $failed = count($results) - $success;

    return response()->json([

        'success' => $failed === 0,

        'message' => $failed === 0
            ? 'تمام Cache ها با موفقیت پاک شدند.'
            : 'پاک‌سازی انجام شد، اما برخی عملیات با خطا مواجه شدند.',

        'summary' => [
            'total' => count($results),
            'success' => $success,
            'failed' => $failed,
            'duration' => round(microtime(true) - $startedAt, 3) . 's',
        ],

        'system' => [
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'env' => app()->environment(),
        ],

        'results' => $results,

        'timestamp' => now()->toDateTimeString(),
    ], $failed === 0 ? 200 : 500);
})->middleware('auth');

Route::get('/clear-bot-caches-safely', function () {
    // پاکسازی کش اصلی لاراول (شامل تمام کش‌های ربات و سشن‌های shop_session و bot_config)
    Artisan::call('cache:clear');

    // پاکسازی کش کانفیگ، روت و ویوها
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('filament:optimize-clear');
    Artisan::call('optimize:clear');


    // پاکسازی کامل کل درایور کش (برای اطمینان از حذف تمام کلیدهای سفارشی)
    \Illuminate\Support\Facades\Cache::flush();

    return '✅ تمام کش‌های ربات‌ها، سشن‌ها و داده‌های موقت با موفقیت و به طور کامل پاکسازی شدند!';
});

Route::get('/publish-assets', function () {
    try {
        // ۱. انتشار فایل‌های جاوااسکریپت و CSS فیلامنت (برای تقویم شمسی و افزونه‌ها)
        Artisan::call('filament:assets');

        // ۲. پاک کردن کش ویوها و لاراول برای شناسایی فایل‌های جدید
        Artisan::call('view:clear');
        Artisan::call('optimize:clear');

        return '✅ فایل‌های تقویم شمسی با موفقیت پابلیش و کش‌ها پاکسازی شدند! حالا داشبورد را در مرورگر رفرش کنید.';
    } catch (\Exception $e) {
        return '❌ خطا در اجرای عملیات: ' . $e->getMessage();
    }
});



Route::get('/run-cmd/{command}', function ($command) {
    //  https://your-domain.com/run-cmd/shop:daily-intelligence?secret=azarakhsh1252
    // 🛡 لایه امنیتی ۱: بررسی کلید مخفی (فقط ادمین سیستم اجازه دارد)
    $secret = request('secret');
    if ($secret !== env('WEBHOOK_SECRET', 'azarakhsh1252')) {
        abort(403, '⛔ Access Denied! (Secret key is invalid)');
    }

    // 🛡 لایه امنیتی ۲: لیست کامندهای مجاز (Whitelist)
    // هر کامند جدیدی که می‌سازید و می‌خواهید از مرورگر اجرا شود را باید اینجا اضافه کنید
    $allowedCommands = [
        'shop:daily-intelligence',
        'shop:cancel-expired',
        'shop:check-subscriptions',
        'shop:clean-carts',
        'shop:expire-discounts',
        'shop:update-stats',
        'cache:clear',
        'config:clear',
        'route:clear',
        'view:clear',
        'shop:performance-report'
    ];

    if (!in_array($command, $allowedCommands)) {
        return response()->json([
            'success' => false,
            'error' => "⛔ اجرای این کامند ({$command}) از طریق مرورگر مجاز نیست!"
        ], 403);
    }

    // 🚀 اجرای کامند
    try {
        $exitCode = Artisan::call($command);
        $output = Artisan::output();

        return response()->json([
            'success' => $exitCode === 0,
            'command' => $command,
            'output' => $output ? trim($output) : '✅ با موفقیت اجرا شد (بدون خروجی متنی)'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});


Route::get('/app/{bot_username}', [MiniAppController::class, 'index'])->name('miniapp.home');

Route::get('/app/{bot_username}/product/{id}', [MiniAppController::class, 'show'])->name('miniapp.product');
Route::get('/app/{bot_username}/category/{id?}', [App\Http\Controllers\MiniAppController::class, 'category'])->name('miniapp.category');

// مسیرهای احراز هویت مینی‌اپلیکیشن (ورود یکپارچه و پیامکی)
Route::post('/app/{bot_username}/auth', [App\Http\Controllers\MiniAppController::class, 'telegramAuth'])->name('miniapp.auth');
Route::get('/app/{bot_username}/login', [App\Http\Controllers\MiniAppController::class, 'showLogin'])->name('miniapp.login');
Route::post('/app/{bot_username}/send-otp', [App\Http\Controllers\MiniAppController::class, 'sendWebOtp'])->name('miniapp.send_otp');
Route::post('/app/{bot_username}/verify-otp', [App\Http\Controllers\MiniAppController::class, 'verifyWebOtp'])->name('miniapp.verify_otp');
Route::get('/app/{bot_username}/logout', [App\Http\Controllers\MiniAppController::class, 'logout'])->name('miniapp.logout');
Route::get('/app/{bot_username}/discounts', [App\Http\Controllers\MiniAppController::class, 'discounts'])->name('miniapp.discounts');

Route::middleware('auth')->group(function () {
    Route::get('/app/{bot_username}/cart', [App\Http\Controllers\MiniAppController::class, 'cart'])->name('miniapp.cart');
    Route::post('/app/{bot_username}/cart/add', [App\Http\Controllers\MiniAppController::class, 'addToCart']);
    Route::post('/app/{bot_username}/cart/update', [App\Http\Controllers\MiniAppController::class, 'updateCart']);
    Route::post('/app/{bot_username}/cart/remove', [App\Http\Controllers\MiniAppController::class, 'removeCart']);
    Route::post('/app/{bot_username}/cart/clear', [App\Http\Controllers\MiniAppController::class, 'clearCart']);
    Route::post('/app/{bot_username}/cart/discount', [App\Http\Controllers\MiniAppController::class, 'applyDiscount']);
    Route::get('/app/{bot_username}/profile', [App\Http\Controllers\MiniAppController::class, 'profile'])->name('miniapp.profile');
    Route::post('/app/{bot_username}/favorites/toggle', [App\Http\Controllers\MiniAppController::class, 'toggleFavorite']);

});