<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramProductService
{
    /**
     * تولید متن برای ربات (حالت استاندارد)
     */
    public static function generateCaption(Product $product, $isChannel = false): string
    {
        $text = "🌟 <b>" . trim(htmlspecialchars($product->name, ENT_QUOTES, 'UTF-8')) . "</b>\n";

        if (!empty($product->sku)) {
            $text .= "🪪 <code>کد کالا: {$product->sku}</code>\n";
        }
        $text .= "\n";

        $isValidDiscount = false;
        if (!empty($product->discount_price)) {
            if (empty($product->discount_expires_at) || \Carbon\Carbon::parse($product->discount_expires_at)->isFuture()) {
                $isValidDiscount = true;
            }
        }

        if ($isValidDiscount) {
            $discountPercent = ($product->price > 0)
                ? round((($product->price - $product->discount_price) / $product->price) * 100)
                : 0;

            $text .= "💰 <s>" . number_format($product->price) . " تومان</s>\n";
            $text .= "🔥 <b>" . number_format($product->discount_price) . " تومان ({$discountPercent}٪ تخفیف)</b>\n";

            if (!empty($product->discount_expires_at)) {
                $expiresAt = \Carbon\Carbon::parse($product->discount_expires_at);
                $daysLeft = (int) now()->diffInDays($expiresAt);
                $hoursLeft = now()->diffInHours($expiresAt) % 24;

                if ($daysLeft > 0) {
                    $text .= "⏳ <i>(فقط {$daysLeft} روز تا پایان این تخفیف فرصت دارید!)</i>\n";
                } elseif ($hoursLeft > 0) {
                    $text .= "🚨 <i>(تیک‌تاک... فقط {$hoursLeft} ساعت تا پایان جشنواره زمان مونده!)</i>\n";
                } else {
                    $text .= "🔥 <i>(ساعات پایانی! همین الان خرید رو نهایی کنید)</i>\n";
                }
            }
            $text .= "\n";
        } else {
            $text .= "💎 <b>ارزش این کالا: " . number_format($product->price) . " تومان</b>\n\n";
        }

        $socialProof = "";
        if ($product->average_rating > 0) {
            $socialProof .= "⭐️ <b>رضایت مشتریان:</b> {$product->average_rating} از 5\n";
        }
        if ($product->sales_count >= 10) {
            $socialProof .= "🔥 <b>کالای داغ:</b> بیش از {$product->sales_count} نفر اینو خریدن!\n";
        }

        if (!empty($socialProof)) {
            $text .= $socialProof . "\n";
        }

        $variants = $product->variants()->where('is_active', true)->get();
        if ($variants->count() > 0) {
            $text .= "🎨 <b>تنوع‌های بی‌نظیر این محصول:</b>\n";
            foreach ($variants->take(5) as $variant) {
                $icon = ($variant->stock !== null && $variant->stock <= 0) ? "❌" : "▫️";
                $text .= " {$icon} <i>{$variant->variant_name}</i>\n";
            }
            $text .= "\n";
        }

        if ($product->stock === null) {
            $text .= "✅ <b>موجود و آماده ارسال فوری</b> 🚀\n\n";
        } else {
            if ($product->stock <= 0) {
                $text .= "❌ <i>اوه! موجودی این کالا فعلاً تموم شده 💔</i>\n\n";
            } elseif ($product->stock < 15) {
                $text .= "🔥 <b>فقط {$product->stock} عدد</b> تو انبار مونده! 🏃‍♂️\n\n";
            } else {
                $text .= "✅ <b>موجود تو انبار (آماده ارسال)</b> 📦\n\n";
            }
        }

        if ($product->description) {
            $desc = strip_tags(html_entity_decode($product->description));
            $text .= "<blockquote expandable>" . mb_substr($desc, 0, 800) . "...</blockquote>\n";
        }

        return $text;
    }

    /**
     * 🚀 متد جدید: تولید کدهای Rich HTML مخصوص پُست کانال
     */
    public static function generateRichChannelHTML(Product $product): string
    {
        $html = "🌟 <b>" . trim(htmlspecialchars($product->name, ENT_QUOTES, 'UTF-8')) . "</b><br>";

        if (!empty($product->sku)) {
            $html .= "🪪 <code>کد کالا: {$product->sku}</code><br>";
        }
        $html .= "<br>";

        // قیمت‌گذاری و تخفیفات
        $isValidDiscount = false;
        if (!empty($product->discount_price) && (empty($product->discount_expires_at) || \Carbon\Carbon::parse($product->discount_expires_at)->isFuture())) {
            $isValidDiscount = true;
        }

        if ($isValidDiscount) {
            $discountPercent = ($product->price > 0) ? round((($product->price - $product->discount_price) / $product->price) * 100) : 0;
            $html .= "💰 قیمت اصلی: <s>" . number_format($product->price) . " تومان</s><br>";
            $html .= "🔥 <b>تخفیف ویژه: " . number_format($product->discount_price) . " تومان ({$discountPercent}٪ تخفیف)</b><br>";

            if (!empty($product->discount_expires_at)) {
                $expiresAt = \Carbon\Carbon::parse($product->discount_expires_at);
                $jalaliDate = \Morilog\Jalali\Jalalian::fromCarbon($expiresAt)->format('Y/m/d');
                $html .= "⏳ <i>(اعتبار این قیمت فقط تا {$jalaliDate})</i><br>";
            }
        } else {
            $html .= "💎 <b>ارزش سرمایه‌گذاری: " . number_format($product->price) . " تومان</b><br>";
        }
        $html .= "<br>";

        // نمایش جدول تنوع‌ها در کانال
        $variants = $product->variants()->where('is_active', true)->get();
        if ($variants->count() > 0) {
            $html .= "🎨 <b>تنوع‌های موجود:</b><br>";
            $html .= "<table bordered striped compact>";
            $html .= "<tr><th align=\"right\">مدل / رنگ</th><th align=\"center\">وضعیت</th></tr>";
            foreach ($variants->take(10) as $variant) {
                $status = ($variant->stock !== null && $variant->stock <= 0) ? "❌ ناموجود" : "✅ موجود";
                $html .= "<tr><td align=\"right\">{$variant->variant_name}</td><td align=\"center\">{$status}</td></tr>";
            }
            $html .= "</table><br>";
        }

        // توضیحات داخل باکس نقل قول
        if ($product->description) {
            $desc = strip_tags(html_entity_decode($product->description));
            $html .= "<blockquote expandable>{$desc}</blockquote><br>";
        }

        return $html;
    }


    /**
     * تولید دکمه‌های شیشه‌ای
     */
    public static function generateKeyboard(Product $product, $isChannel = false, $backButton = null, $userId = null, $isAdmin = false): array
    {
        $keyboard = [];
        $bot = $product->bot;
        $deepLink = "https://t.me/{$bot->bot_username}?start=product_{$product->id}";

        $shareRawText = "🔥 وای ببین چی پیدا کردم!\n💎 {$product->name}\n\n";
        if (!empty($product->discount_price) && (empty($product->discount_expires_at) || \Carbon\Carbon::parse($product->discount_expires_at)->isFuture())) {
            $shareRawText .= "🎁 الان تو تخفیف ویژه‌ست و قیمتش شده: " . number_format($product->discount_price) . " تومان!\n\n";
        }
        $shareRawText .= "👇 بزن روی لینک زیر تا ناموجود نشده بریم ببینیمش:\n";
        $shareUrl = "https://t.me/share/url?url={$deepLink}&text=" . urlencode($shareRawText);

        if ($isChannel) {
            // 🚀 دکمه خرید و اشتراک‌گذاری در یک ردیف کنار هم برای کانال
            $keyboard[] = [
                ['text' => '🛒 خرید سریع این محصول', 'url' => $deepLink],
                ['text' => '📤 ارسال به دوستان', 'url' => $shareUrl]
            ];
        } else {
            $variantsCount = $product->variants()->where('is_active', true)->count();
            if ($variantsCount > 0) {
                $keyboard[] = [['text' => '🎯 انتخاب مدل و خرید', 'callback_data' => "select_variant_{$product->id}"]];
            } elseif ($product->stock === null || $product->stock > 0) {
                $keyboard[] = [['text' => '🛍 افزودن به سبد خرید', 'callback_data' => "add_to_cart_{$product->id}"]];
            } else {
                $keyboard[] = [
                    [
                        'text' => '💔 متاسفانه تمام شد',
                        'callback_data' => 'ignore_cb',
                        'disabled' => true
                    ]
                ];
            }

            $favoriteBtn = ['text' => '🤍 افزودن به علاقه‌مندی', 'callback_data' => "toggle_fav_{$product->id}"];
            if ($userId && \App\Models\Favorite::where('bot_id', $product->bot_id)->where('user_id', $userId)->where('product_id', $product->id)->exists()) {
                $favoriteBtn = ['text' => '❤️ حذف از علاقه‌مندی', 'callback_data' => "toggle_fav_{$product->id}"];
            }

            $keyboard[] = [
                ['text' => '🛒 سبد خرید من', 'callback_data' => 'view_cart'],
                $favoriteBtn
            ];

            $actionRow = [];
            if ($product->images()->count() > 1) {
                $actionRow[] = ['text' => '🖼 گالری تصاویر', 'callback_data' => "gallery_{$product->id}_0"];
            }
            $actionRow[] = ['text' => '🎁 پیشنهاد به دوست', 'url' => $shareUrl];
            $keyboard[] = $actionRow;

            if ($isAdmin) {
                $keyboard[] = [
                    ['text' => '⚙️ تنظیمات محصول', 'callback_data' => "admin_manage_prod_{$product->id}"],
                    ['text' => '📊 آمار و جزئیات', 'callback_data' => "prod_stats_{$product->id}"]
                ];
            }

            if ($backButton) {
                $keyboard[] = [$backButton];
            } else {
                $keyboard[] = [['text' => '🏠 بازگشت به ویترین', 'callback_data' => 'main_menu']];
            }
        }

        return ['inline_keyboard' => $keyboard];
    }

    /**
     * 🚀 متد ارسال هوشمند (Rich Message برای کانال، Standard برای ربات)
     */
    public static function send(Product $product, $chatId, $isChannel = false, $backButton = null, $userId = null, $isAdmin = false)
    {
        if (!$isChannel) {
            $product->increment('views_count');
        }

        $bot = $product->bot;
        $keyboard = self::generateKeyboard($product, $isChannel, $backButton, $userId, $isAdmin);

        // ==========================================
        // 🌟 ارسال به کانال (استفاده از Rich Message و Collage)
        // ==========================================
        if ($isChannel) {
            $html = self::generateRichChannelHTML($product);
            $images = $product->images()->orderBy('sort_order')->take(6)->get(); // حداکثر ۶ عکس برای کلاژ کانال

            $mediaArray = [];
            $request = Http::timeout(30);
            $hasLocalFiles = false;

            if ($images->count() > 0) {
                $collageHtml = "<tg-collage>\n";
                foreach ($images as $idx => $img) {
                    $mediaId = "img_post_{$idx}";
                    $collageHtml .= "<img src=\"tg://photo?id={$mediaId}\"/>\n";

                    if (!empty($img->telegram_file_id)) {
                        $mediaArray[] = ['id' => $mediaId, 'media' => ['type' => 'photo', 'media' => $img->telegram_file_id]];
                    } else {
                        $absolutePath = storage_path('app/public/' . $img->file_id);
                        if (file_exists($absolutePath)) {
                            $mediaArray[] = ['id' => $mediaId, 'media' => ['type' => 'photo', 'media' => "attach://{$mediaId}"]];
                            $request->attach($mediaId, file_get_contents($absolutePath), "photo{$idx}.webp");
                            $hasLocalFiles = true;
                        }
                    }
                }
                $collageHtml .= "</tg-collage><br><br>";
                $html = $collageHtml . $html; // چسباندن کلاژ به بالای توضیحات
            }

            $payload = [
                'chat_id' => $chatId,
                'rich_message' => ['html' => $html],
                'reply_markup' => $keyboard // کیبورد مستقیم به ساختار جدید متصل می‌شود
            ];

            if (!empty($mediaArray)) {
                $payload['rich_message']['media'] = $mediaArray;
            }

            if ($hasLocalFiles) {
                $payload['rich_message'] = json_encode($payload['rich_message']);
                $payload['reply_markup'] = json_encode($payload['reply_markup']);
                $response = $request->post("https://api.telegram.org/bot{$bot->bot_token}/sendRichMessage", $payload);
            } else {
                $response = Http::timeout(15)->post("https://api.telegram.org/bot{$bot->bot_token}/sendRichMessage", $payload);
            }

            if ($response && $response->successful()) {
                $result = $response->json('result');
                if (isset($result['message_id']) && $result['message_id'] != $product->channel_message_id) {
                    $product->updateQuietly(['channel_message_id' => $result['message_id']]);
                }
            } else {
                Log::error('RichChannel Send Error: ' . json_encode($response ? $response->json() : 'No response'));
            }
            return $response;
        }

        // ==========================================
        // 🤖 ارسال به ربات (حالت استاندارد قبلی)
        // ==========================================
        $text = self::generateCaption($product, false);
        $firstImage = $product->images()->orderBy('sort_order')->first();
        $response = null;

        if ($firstImage) {
            if (!empty($firstImage->telegram_file_id)) {
                $response = Http::timeout(20)->post("https://api.telegram.org/bot{$bot->bot_token}/sendPhoto", [
                    'chat_id' => $chatId,
                    'photo' => $firstImage->telegram_file_id,
                    'caption' => $text,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode($keyboard)
                ]);
            } else {
                $absolutePath = storage_path('app/public/' . $firstImage->file_id);
                if (file_exists($absolutePath)) {
                    $response = Http::timeout(20)->attach('photo', file_get_contents($absolutePath), 'product.webp')
                        ->post("https://api.telegram.org/bot{$bot->bot_token}/sendPhoto", [
                            'chat_id' => $chatId,
                            'caption' => $text,
                            'parse_mode' => 'HTML',
                            'reply_markup' => json_encode($keyboard)
                        ]);
                }
            }
        }

        if (!$response) {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$bot->bot_token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($keyboard)
            ]);
        }

        if ($response && $response->successful() && isset($response->json('result')['photo']) && $firstImage) {
            $firstImage->updateQuietly(['telegram_file_id' => end($response->json('result')['photo'])['file_id']]);
        }

        return $response;
    }

    public static function updateChannelPost(Product $product)
    {
        if (!$product->channel_message_id) {
            return;
        }

        $channelId = $product->bot->settings()->where('key', 'channel_id')->value('value');
        if (empty($channelId))
            return;
        if (!is_numeric($channelId) && !str_starts_with($channelId, '@')) {
            $channelId = '@' . $channelId;
        }

        return self::editInChannel($product, $channelId);
    }

    public static function repostToChannel(Product $product)
    {
        $channelId = $product->bot->settings()->where('key', 'channel_id')->value('value');
        if (empty($channelId))
            return;
        if (!is_numeric($channelId) && !str_starts_with($channelId, '@')) {
            $channelId = '@' . $channelId;
        }

        if ($product->channel_message_id) {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$product->bot->bot_token}/deleteMessage", [
                'chat_id' => $channelId,
                'message_id' => $product->channel_message_id
            ]);

            if ($response->successful() || str_contains(strtolower($response->json('description') ?? ''), 'not found')) {
                $product->updateQuietly(['channel_message_id' => null]);
            }
        }

        sleep(0.5);
        return self::send($product, $channelId, true);
    }

    /**
     * 🚀 متد ویرایش آنلاین در کانال (بدون پاک شدن پیام و صفر شدن بازدیدها!)
     */
    public static function editInChannel(Product $product, $channelId)
    {
        $bot = $product->bot;
        $messageId = $product->channel_message_id;

        // اگر پیامی از قبل در کانال ندارد، مستقیماً به متد ارسال جدید پاس می‌دهیم
        if (!$messageId) {
            return self::send($product, $channelId, true);
        }

        // ==========================================
        // 🎨 تولید محتوای جدید (کلاژ + متن + دکمه‌ها)
        // ==========================================
        $html = self::generateRichChannelHTML($product);
        $keyboard = self::generateKeyboard($product, true);
        $images = $product->images()->orderBy('sort_order')->take(6)->get();

        $mediaArray = [];
        $request = Http::timeout(30);
        $hasLocalFiles = false;

        // ساخت کلاژ آپدیت‌شده
        if ($images->count() > 0) {
            $collageHtml = "<tg-collage>\n";
            foreach ($images as $idx => $img) {
                $mediaId = "img_post_{$idx}";
                $collageHtml .= "<img src=\"tg://photo?id={$mediaId}\"/>\n";

                if (!empty($img->telegram_file_id)) {
                    $mediaArray[] = ['id' => $mediaId, 'media' => ['type' => 'photo', 'media' => $img->telegram_file_id]];
                } else {
                    $absolutePath = storage_path('app/public/' . $img->file_id);
                    if (file_exists($absolutePath)) {
                        $mediaArray[] = ['id' => $mediaId, 'media' => ['type' => 'photo', 'media' => "attach://{$mediaId}"]];
                        $request->attach($mediaId, file_get_contents($absolutePath), "photo{$idx}.webp");
                        $hasLocalFiles = true;
                    }
                }
            }
            $collageHtml .= "</tg-collage><br><br>";
            $html = $collageHtml . $html;
        }

        // ==========================================
        // 🚀 ارسال ریکوئست ادیت با جادوی جدید
        // ==========================================
        $payload = [
            'chat_id' => $channelId,
            'message_id' => $messageId,
            'text' => '🔄 در حال بروزرسانی کاتالوگ...', // متن فریب‌دهنده برای رد شدن از سد ولیدیشن تلگرام
            'rich_message' => ['html' => $html],
            'reply_markup' => $keyboard // کیبورد جدید
        ];

        if (!empty($mediaArray)) {
            $payload['rich_message']['media'] = $mediaArray;
        }

        if ($hasLocalFiles) {
            $payload['rich_message'] = json_encode($payload['rich_message']);
            $payload['reply_markup'] = json_encode($payload['reply_markup']);
            $response = $request->post("https://api.telegram.org/bot{$bot->bot_token}/editMessageText", $payload);
        } else {
            $response = Http::timeout(15)->post("https://api.telegram.org/bot{$bot->bot_token}/editMessageText", $payload);
        }

        // ==========================================
        // 🛡 پلن B: مدیریت خطاهای احتمالی
        // ==========================================
        if ($response && !$response->successful()) {
            $errorDesc = strtolower($response->json('description') ?? '');

            // اگر تلگرام ارور داد (به جز خطای تکراری بودن پیام)، یعنی پیام اصلی از کانال پاک شده یا نوعش فرق داره
            if (!str_contains($errorDesc, 'message is not modified')) {
                // پیام خراب قبلی را پاک می‌کنیم
                Http::timeout(10)->post("https://api.telegram.org/bot{$bot->bot_token}/deleteMessage", [
                    'chat_id' => $channelId,
                    'message_id' => $messageId
                ]);
                $product->updateQuietly(['channel_message_id' => null]);

                // پیام را به عنوان یک پست کاملاً جدید می‌فرستیم
                return self::send($product, $channelId, true);
            }
        }

        return $response;
    }
}
