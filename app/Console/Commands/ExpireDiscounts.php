<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Services\TelegramProductService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireDiscounts extends Command
{
    protected $signature = 'shop:expire-discounts';
    protected $description = 'بررسی هر دقیقه‌ی تخفیف‌های منقضی شده، حذف آن‌ها و آپدیت پست کانال';

    public function handle()
    {
        // 🔒 ایجاد قفل دستی ۶۰ ثانیه‌ای برای جلوگیری از تداخل در هاست‌های ضعیف
        if (!\Illuminate\Support\Facades\Cache::add('expire_discounts_lock', true, 60)) {
            // اگر قفل از قبل وجود داشت (یعنی اجرای قبلی هنوز تمام نشده)، این اجرا را لغو کن
            return Command::SUCCESS;
        }
        // ۱. ابتدا فقط آیدی محصولاتی که تخفیفشان گذشته را می‌گیریم (بدون قفل کردن کل جدول)
        $expiredProductIds = Product::whereNotNull('discount_expires_at')
            ->where('discount_expires_at', '<=', Carbon::now())
            ->pluck('id');

        if ($expiredProductIds->isEmpty()) {
            return Command::SUCCESS;
        }

        $processedCount = 0;

        foreach ($expiredProductIds as $id) {
            $productToUpdateChannel = null;

            // =========================================================
            // 🛡 مرحله اول: تراکنش دیتابیس با قفل اختصاصی (Pessimistic Lock)
            // =========================================================
            DB::transaction(function () use ($id, &$productToUpdateChannel) {
                // قفل کردن این ردیف خاص برای جلوگیری از تداخل با پنل ادمین (Race Condition)
                $product = Product::with('variants')->where('id', $id)->lockForUpdate()->first();

                // دابل‌چک کردن زمان انقضا (شاید در همین چند میلی‌ثانیه ادمین تاریخ را تمدید کرده باشد!)
                if ($product && $product->discount_expires_at && $product->discount_expires_at <= Carbon::now()) {

                    // حذف تخفیف از محصول اصلی
                    $product->discount_price = null;
                    $product->discount_expires_at = null;
                    $product->save();

                    // حذف تخفیف از تمام متغیرهای محصول (رنگ، سایز و...)
                    foreach ($product->variants as $variant) {
                        $variant->discount_price = null;
                        $variant->save();
                    }

                    // آماده‌سازی محصول برای ارسال به تلگرام (خارج از تراکنش)
                    $productToUpdateChannel = $product;
                }
            });

            // =========================================================
            // 🚀 مرحله دوم: آپدیت کانال تلگرام (خارج از تراکنش دیتابیس)
            // =========================================================
            // این کار باعث می‌شود اگر تلگرام کند بود، دیتابیس قفل نماند!
            if ($productToUpdateChannel) {
                try {
                    // استفاده از متدی که قبلاً در سرویس نوشتید (ویرایش پیام به جای ارسال مجدد)
                    TelegramProductService::updateChannelPost($productToUpdateChannel);
                    $processedCount++;
                } catch (\Exception $e) {
                    Log::channel('cron')->error("Error updating channel for expired product {$id}: " . $e->getMessage());
                }
            }
        }

        if ($processedCount > 0) {
            Log::channel('cron')->info("✅ {$processedCount} products had their expired discounts removed and channel posts updated.");
        }

        return Command::SUCCESS;
    }
}
