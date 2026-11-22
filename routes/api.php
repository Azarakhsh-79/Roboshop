<?php

use App\Http\Controllers\AdminTelegramBotController;
use App\Http\Controllers\ShopBotController;
use Illuminate\Support\Facades\Route;

// --- ربات اصلی ادمین (Super Admin) ---
Route::prefix('bot/admin')->controller(AdminTelegramBotController::class)->group(function () {

    // دریافت آپدیت‌های تلگرام (فقط POST + محدودیت ریکوئست + میدلور بررسی وب‌هوک)
    Route::post('/', 'handle')
        ->middleware(['telegram.webhook', 'throttle:api'])
        ->name('admin.bot.webhook');

    // روت‌های مدیریتی ربات ادمین (تغییر به POST + محافظت با میدلور امنیتی)
    Route::middleware('admin.secret')->group(function () {
        Route::post('/set', 'setWebhook')->name('admin.bot.set');
        Route::post('/delete', 'deleteWebhook')->name('admin.bot.delete');
        Route::post('/info', 'getWebhookInfo')->name('admin.bot.info');
    });
});

// --- ربات‌های فروشگاهی (Shop Bots) ---
Route::prefix('bot/shopbot')->controller(ShopBotController::class)->group(function () {

    // --- متدهای مدیریتی (تغییر به POST + محافظت با میدلور امنیتی) ---
    // سکرت و توکن بات باید در بدنه درخواست (Body) ارسال شوند، نه در URL
    Route::middleware('admin.secret')->group(function () {
        Route::post('/set', 'setWebhook');
        Route::post('/delete', 'deleteWebhook');
        Route::post('/info', 'getWebhookInfo');
    });

    // ۱. مسیر اصلی دریافت آپدیت‌ها (Webhook URL)
    // تغییر any به post + اضافه کردن محدودیت ریکوئست
    Route::post('/{secret}', 'handle')->middleware('throttle:api');
});
