<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\ProcessBroadcastJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// آپدیت آمار (هر ۴ ساعت)
Schedule::call(function () {
    Artisan::call('shop:update-stats');
})->everyFourHours();

// لغو فاکتورهای منقضی شده (هر  5دقیقه)
Schedule::call(function () {
    Artisan::call('shop:cancel-expired');
})->everyFiveMinutes();

// 🚀 اجرای سیستم بررسی اشتراک‌ها (هر شب ساعت ۰۰:۰۵ بامداد)
Schedule::call(function () {
    Artisan::call('shop:check-subscriptions');
})->dailyAt('00:05');

// 🧹 پاکسازی سبدهای خرید قدیمی (هر شب ساعت ۰۳:۰۰ بامداد)
Schedule::call(function () {
    Artisan::call('shop:clean-carts', ['--days' => 7]);
})->dailyAt('03:00');

// 🛒 یادآوری سبدهای رها شده به مشتریان (اجرا هر ساعت)
Schedule::call(function () {
    Artisan::call('shop:cart-reminder');
})->hourly();

// 🚀 حذف تخفیف‌های منقضی شده و آپدیت کانال (اجرا در هر 5 دقیقه!)
Schedule::call(function () {
    Artisan::call('shop:expire-discounts');
})
    ->everyFiveMinutes()
    ->name('expire-discounts-job')
    ->withoutOverlapping();


// اجرای گزارش‌ها هر شب ساعت 22:00
Schedule::call(function () {
    Artisan::call('shop:daily-intelligence');
})->dailyAt('22:00');


// اجرای گزارش عملکرد سیستم هر شب ساعت 23:55
Schedule::call(function () {
    Artisan::call('shop:performance-report');
})->dailyAt('23:55');


Schedule::call(function () {
    $broadcasts = \App\Models\Broadcast::where('status', 'pending')
        ->where('scheduled_at', '<=', now())
        ->get();

    foreach ($broadcasts as $broadcast) {
        $broadcast->update(['status' => 'processing']);

        ProcessBroadcastJob::dispatch($broadcast);
    }
})->everyMinute();
