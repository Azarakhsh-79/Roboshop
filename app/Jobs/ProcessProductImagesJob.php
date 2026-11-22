<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\ProductImage;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
class ProcessProductImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $productId;
    protected $photos;
    protected $botToken;

    public function __construct($productId, array $photos, $botToken)
    {
        $this->productId = $productId;
        $this->photos = $photos;
        $this->botToken = $botToken;
    }

    public function handle()
    {
        Log::channel('cron')->info("Image Job START: Processing images for Product ID {$this->productId}");
        $product = Product::find($this->productId);
       if (!$product) {
            Log::channel('cron')->warning("Image Job CANCELLED: Product {$this->productId} not found.");
            return;
        }

        foreach ($this->photos as $index => $telegramFileId) {
            // ۱. دریافت مسیر فایل موقت از سرور تلگرام
            $response = Http::get("https://api.telegram.org/bot{$this->botToken}/getFile?file_id={$telegramFileId}");

            if (!$response->successful() || !isset($response->json()['result']['file_path'])) {
                continue; // اگر مشکلی در دریافت بود، برو سراغ عکس بعدی
            }

            $telegramFilePath = $response->json()['result']['file_path'];

            // ۲. دانلود مستقیم بایت‌های تصویر
            $imageContent = Http::get("https://api.telegram.org/file/bot{$this->botToken}/{$telegramFilePath}")->body();

            // ۳. پردازش تصویر دقیقاً با منطق پنل وب (توابع بومی GD)
            $image = imagecreatefromstring($imageContent);

            if (!$image) {
                continue; // اگر محتوا عکس نبود
            }

            $width = imagesx($image);
            $height = imagesy($image);

            // محدود کردن عرض به 1200 پیکسل (بدون افت نسبت ابعاد)
            if ($width > 1200) {
                $newWidth = 1200;
                $newHeight = (int) ($height * ($newWidth / $width));
                $resized = imagecreatetruecolor($newWidth, $newHeight);

                // تنظیمات پیشرفته برای حفظ پس‌زمینه شفاف (اگر عکس اصلی اینطور بود)
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
                imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);

                // تغییر اندازه با بالاترین کیفیت
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagedestroy($image);
                $image = $resized;
            }

            // تولید نام یکتا
            $filename = Str::uuid() . '.webp';
            $path = 'product-images/' . $filename;

            // مسیر دقیق ذخیره‌سازی در دیسک public لاراول
            $absolutePath = storage_path('app/public/' . $path);

            // ساختن پوشه در سرور اگر هنوز وجود نداشته باشد
            if (!file_exists(dirname($absolutePath))) {
                mkdir(dirname($absolutePath), 0755, true);
            }

            // تبدیل مستقیم به فرمت WebP با کیفیت 80 و ذخیره
            imagewebp($image, $absolutePath, 80);
            imagedestroy($image);

            // دسترسی خواندن برای لینوکس
            chmod($absolutePath, 0644);

            // ۴. ذخیره در دیتابیس (هماهنگ با فرم Filament)
            ProductImage::create([
                'product_id' => $this->productId,
                'bot_id'     => $product->bot_id, // برای اعمال Global Scope ها
                'type'       => 'photo',

                // 💡 مسیر فیزیکی عکس برای نمایش در سایت و پنل مدیریت
                'file_id'    => $path,

                // 💡 آیدی تلگرامی برای رندر سریع (آنی) در خود ربات
                // (حتماً این فیلد را در مایگریشن خود داشته باشید)
                'telegram_file_id' => $telegramFileId,

                'sort_order' => $index, // اولین عکس (0) به عنوان کاور شناخته می‌شود
            ]);
        }
        Log::channel('cron')->info("Image Job FINISHED: Images processed for Product ID {$this->productId}");
    }
}
