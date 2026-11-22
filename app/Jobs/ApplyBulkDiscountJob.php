<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Category;
use App\Models\Product;
use App\Models\ManagedBot;
use App\Services\TelegramProductService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ApplyBulkDiscountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    protected ManagedBot $bot;
    protected $categoryId;
    protected $percent;
    protected $days;
    protected $promptMessageId;
    protected $chatId;

    public function __construct(ManagedBot $bot, $categoryId, $percent, $days, $promptMessageId, $chatId)
    {
        $this->bot = $bot;
        $this->categoryId = $categoryId;
        $this->percent = (float) $percent;
        $this->days = (int) $days;
        $this->promptMessageId = $promptMessageId;
        $this->chatId = $chatId;
    }

    public function handle()
    {
        Log::channel('cron')->info("QueueJob [Bot {$this->bot->id}]: 🚀 کارگر پس‌زمینه شروع به کار کرد...");

        try {

            if (empty($this->promptMessageId) && !empty($this->chatId)) {
                $initRes = Http::post("https://api.telegram.org/bot{$this->bot->bot_token}/sendMessage", [
                    'chat_id' => $this->chatId,
                    'text' => "⏳ <b>دستور پردازش از پنل وب دریافت شد...</b>\n<i>سیستم در حال آماده‌سازی نوار پیشرفت است.</i>",
                    'parse_mode' => 'HTML'
                ]);

                if ($initRes->successful()) {
                    $this->promptMessageId = $initRes->json('result.message_id');
                }
            }

            // ۱. استخراج زیردسته‌ها
            $allCategories = Category::where('bot_id', $this->bot->id)->get();
            $targetIds = [(int) $this->categoryId];
            $visited = [];

            $getChildren = function ($parentId) use (&$getChildren, $allCategories, &$targetIds, &$visited) {
                if (in_array($parentId, $visited)) return;
                $visited[] = $parentId;

                foreach ($allCategories->where('parent_id', $parentId) as $child) {
                    if (!in_array($child->id, $targetIds)) {
                        $targetIds[] = $child->id;
                        $getChildren($child->id);
                    }
                }
            };
            $getChildren((int) $this->categoryId);

            // ۲. استخراج محصولات به همراه متغیرها (variants)
            $products = Product::with('variants')->where('bot_id', $this->bot->id)->whereIn('category_id', $targetIds)->get();
            $totalProducts = $products->count();

            if ($totalProducts === 0) {
                Log::channel('cron')->info("QueueJob [Bot {$this->bot->id}]: ⚠️ هیچ محصولی یافت نشد. خروج...");
                return;
            }

            $count = 0;
            $lastUpdateTime = time();

            // ۳. پردازش یکی یکی محصولات
            foreach ($products as $product) {
                if ($this->percent == 0) {
                    $product->discount_price = null;
                    $product->discount_expires_at = null;
                } else {
                    $discountAmount = ($product->price * $this->percent) / 100;
                    $product->discount_price = $product->price - $discountAmount;

                    if ($this->days > 0) {
                        $product->discount_expires_at = now()->addDays((int) $this->days)->endOfDay();
                    } else {
                        $product->discount_expires_at = null;
                    }
                }
                $product->save();

                // 🚀 حل باگ: اعمال تخفیف روی متغیرهای محصول (سایز، رنگ و ...)
                foreach ($product->variants as $variant) {
                    if ($this->percent == 0) {
                        $variant->discount_price = null;
                    } else {
                        $varDiscountAmount = ($variant->price * $this->percent) / 100;
                        $variant->discount_price = $variant->price - $varDiscountAmount;
                    }
                    $variant->save();
                }

                // انتشار پست در کانال
                TelegramProductService::repostToChannel($product);

                $count++;

                // ۴. آپدیت نوار پیشرفت در تلگرام
                if ($this->promptMessageId && $this->chatId && (time() - $lastUpdateTime >= 2 || $count === $totalProducts)) {
                    $percentComplete = round(($count / $totalProducts) * 100);
                    $filledBlocks = max(0, min(10, (int) round($percentComplete / 10)));
                    $emptyBlocks = 10 - $filledBlocks;
                    $progressBar = str_repeat('🟩', $filledBlocks) . str_repeat('⬜️', $emptyBlocks);

                    $daysTextProgress = $this->days > 0 ? " | برای {$this->days} روز" : " | دائمی";

                    $text = "⚙️ <b>در حال اعمال تخفیف گروهی ({$this->percent}٪{$daysTextProgress})</b>\n\n" .
                        "{$progressBar} <b>{$percentComplete}٪</b>\n\n" .
                        "📦 <b>پیشرفت پردازش:</b> {$count} از {$totalProducts}\n" .
                        "🔄 <b>آخرین محصول:</b> <i>" . mb_substr($product->name, 0, 30) . "...</i>";

                    Http::post("https://api.telegram.org/bot{$this->bot->bot_token}/editMessageText", [
                        'chat_id' => $this->chatId,
                        'message_id' => $this->promptMessageId,
                        'text' => $text,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode(['inline_keyboard' => []])
                    ]);

                    $lastUpdateTime = time();
                }
            }

            // ۵. پایان موفقیت آمیز
            $daysTextSuccess = $this->days > 0 ? " برای <b>{$this->days} روز</b>" : " به صورت <b>دائمی</b>";

            $successText = $this->percent == 0
                ? "✅ <b>عملیات موفق:</b> تخفیف از روی تمام <b>{$count} محصول</b> و متغیرهایشان حذف شد و در کانال نیز به‌روزرسانی گردید."
                : "🎉 <b>جشنواره فعال شد!</b>\nتخفیف {$this->percent}٪{$daysTextSuccess} روی <b>{$count} محصول</b> با موفقیت اعمال و کانال آپدیت شد.";

            if ($this->promptMessageId && $this->chatId) {
                $backKeyboard = [
                    [['text' => '🔙 بازگشت به مدیریت این دسته', 'callback_data' => "admin_cat_show_{$this->categoryId}"]]
                ];

                Http::post("https://api.telegram.org/bot{$this->bot->bot_token}/editMessageText", [
                    'chat_id' => $this->chatId,
                    'message_id' => $this->promptMessageId,
                    'text' => $successText,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode(['inline_keyboard' => $backKeyboard])
                ]);
            }

        } catch (Throwable $e) {
            Log::error("QueueJob FATAL ERROR [Bot {$this->bot->id}]: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        }
    }
}
