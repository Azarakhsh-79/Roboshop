<?php

namespace App\AI\Agents;

use App\AI\DTOs\AgentResponse;
use App\Models\Product;
use App\Models\Category;

class ShopSalesAgent extends BaseAgent
{
    public function handle(string $input, array$context = []): AgentResponse
    {
        // ۱. استخراج کلمات کلیدی و ساخت کانتکست فروشگاه
        $keywords = $this->extractSearchKeywords($input);
        $storeContext =$this->buildStoreContext($input,$keywords);

        // ۲. ساخت پرامپت حرفه‌ای
        $systemPrompt = $this->buildSystemPrompt($storeContext);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($this->getHistory() as$historyMsg) {
            $messages[] =$historyMsg;
        }

        $messages[] = ['role' => 'user', 'content' =>$input];

        // ۳. تلاش برای دریافت پاسخ از هوش مصنوعی
        $rawResponse = $this->ai->chat($messages, ['temperature' => 0.3]);

        // ۴. 🛡️ موتور هوشمند Fallback محلی (اگر هوش مصنوعی قطع بود یا جواب نداد)
        if (!$rawResponse) {
            return $this->localKeywordFallback($input,$keywords);
        }

        $parsed = $this->extractJson($rawResponse);

       // اگر مدل هوش مصنوعی نتوانست JSON معتبر برگرداند، از پاسخ جایگزین استفاده کن
if (!$parsed || empty($parsed['reply'])) {
    return $this->localKeywordFallback($input, $keywords);
}

        $replyText = $this->formatTelegramHtml($parsed['reply']);
        $productIds = is_array($parsed['product_ids'] ?? null) ? $parsed['product_ids'] : [];$categoryIds = is_array($parsed['category_ids'] ?? null) ? $parsed['category_ids'] : [];
        $suggestedAction =$parsed['action'] ?? null;

        $this->appendToHistory($input,$replyText);

        $keyboard = $this->buildSmartKeyboard($productIds, $categoryIds,$suggestedAction);

        return new AgentResponse($replyText,$keyboard);
    }

    /**
     * سیستم پشتیبان محلی (بدون نیاز به API خارجی) برای زمانی که هوش مصنوعی قطع است
     */
    protected function localKeywordFallback(string $input, array$keywords): AgentResponse
    {
        // جستجوی مستقیم در محصولات بر اساس کلمات کلیدی استخراج‌شده
        $query = Product::where('bot_id',$this->bot->id)->where('is_active', true);

        if (!empty($keywords)) {$query->where(function ($q) use ($keywords) {
                foreach ($keywords as$kw) {
                    $q->orWhere('name', 'like', "\%{$kw}%")
                      ->orWhere('description', 'like', "%{$kw}%");
                }
            });
        }

        $products =$query->take(4)->get();

        if ($products->isNotEmpty()) {
            $prodNames =$products->pluck('name')->implode('، ');
            $prodIds =$products->pluck('id')->toArray();

            $reply = "عزیزم بر اساس درخواست شما («{$input}») محصولات زیر را پیدا کردم:\n\n" .
                     "▫️ " . $prodNames . "\n\nمی‌توانید از طریق دکمه‌های زیر جزئیات را مشاهده کنید 👇";

            return new AgentResponse($reply, $this->buildSmartKeyboard($prodIds, [], null));
        }

        // اگر هیچ کالایی پیدا نشد، پیشنهاد دسته‌بندی‌ها را بده
        $categories = Category::where('bot_id',$this->bot->id)->where('is_active', true)->take(3)->get();
        $catIds =$categories->pluck('id')->toArray();

        $reply = "مشتری عزیز، کالای مورد نظر شما در حال حاضر در لیست محصولات ما یافت نشد.\n\n" .
                 "پیشنهاد می‌کنم از طریق دکمه‌های زیر از دسته‌بندی‌های دیگر فروشگاه دیدن کنید 🥰";

        return new AgentResponse($reply, $this->buildSmartKeyboard([],$catIds, 'show_categories'));
    }

    protected function extractSearchKeywords(string $text): array
    {
        $normalized = str_replace(
            ['ي', 'ك', 'ة', 'ؤ', 'إ', 'أ', "\u{200c}", '‌'],
            ['ی', 'ک', 'ه', 'و', 'ا', 'ا', ' ', ' '],
            $text
        );

        $compoundFixes = [
            'شلوارجین' => 'شلوار جین',
            'تیشرت'    => 'تیشرت',
            'دورس'     => 'دورس',
        ];
        foreach ($compoundFixes as $stuck =>$separated) {
            $normalized = str_ireplace($stuck, $separated,$normalized);
        }

        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized);$normalized = preg_replace('/(.)\1{2,}/u', '$1',$normalized);

        $words = preg_split('/\s+/u', trim($normalized));

        $stopWords = [
            'سلام', 'درود', 'خسته', 'نباشید', 'خوبی', 'خوبین', 'عزیز', 'جان',
            'لیست', 'محصول', 'محصولات', 'کالا', 'کالاها', 'مدل', 'کار',
            'دارید', 'دارین', 'موجود', 'موجوده', 'هست', 'نیست', 'ندارید', 'ندارین',
            'میخوام', 'میخام', 'میخواستم', 'بفرست', 'بفرستید', 'نشون', 'بده', 'بدید',
            'قیمت', 'قیمتش', 'چنده', 'چقدر', 'چقدره', 'تومان', 'تومن',
            'چی', 'چیا', 'کدوم', 'چطور', 'چطوری', 'کجاست', 'چند',
            'برای', 'واسه', 'توی', 'داخل', 'هم', 'رو', 'را', 'از', 'به', 'در', 'با', 'تا', 'که', 'یا', 'و',
            'یه', 'یک', 'اون', 'این', 'همه', 'کل', 'لطفا', 'میشه', 'اگه', 'اگر', 'من', 'ما', 'شما',
        ];

        $keywords = [];
        foreach ($words as$word) {
            $word = trim($word);
            if (mb_strlen($word) >= 2 && !in_array($word,$stopWords, true)) {
                $keywords[] =$word;
            }
        }

        return array_values(array_unique($keywords));
    }

   protected function buildStoreContext(string $userQuery, array$keywords): string
    {
        $storeName = $this->bot->settings->firstWhere('key', 'store_name')?->value ?? $this->bot->bot_name;
        $shippingCost = (int) ($this->bot->settings->firstWhere('key', 'shipping_cost')?->value ?? 0);
        $shippingText = $shippingCost > 0 ? number_format($shippingCost) . ' تومان' : 'رایگان';

        // دریافت دسته‌بندی‌ها همراه با محصولاتشان برای تفکیک دقیق‌تر
        $categories = Category::where('bot_id',$this->bot->id)
            ->where('is_active', true)
            ->with(['parent', 'products' => fn($q) =>$q->where('is_active', true)])
            ->get();

        $categoriesText = "";
        foreach ($categories as $cat) {$parentPrefix = $cat->parent ? "{$cat->parent->name} > " : "";
            $prodNames =$cat->products->pluck('name')->implode('، ');
            $categoriesText .= "- [CatID: {$cat->id}] دسته: {$parentPrefix}{$cat->name} \vert{} محصولات این دسته: [{$prodNames}]\n";
        }

        $allProducts = Product::where('bot_id',$this->bot->id)
            ->where('is_active', true)
            ->with(['variants' => fn($q) =>$q->where('is_active', true), 'category.parent'])
            ->latest()
            ->take(60)
            ->get();

        $scoredProducts = $allProducts->map(function (Product$product) use ($keywords) {$score = 0;
            $prodName =$this->normalizeForMatch($product->name);$catName = $this->normalizeForMatch($product->category?->name ?? '');
            $parentCatName = $this->normalizeForMatch($product->category?->parent?->name ?? '');
            $variantsText = $this->normalizeForMatch($product->variants->pluck('variant_name')->implode(' '));

            foreach ($keywords as $kw) {$kwNorm = $this->normalizeForMatch($kw);
                if (empty($kwNorm)) continue;

                // اگر کلمه کلیدی دقیقاً نام دسته یا بخشی از نام دسته باشد، امتیاز بسیار بالا می‌گیرد
                if (str_contains($catName, $kwNorm) || str_contains($parentCatName, $kwNorm)) {$score += 25;
                }
                if (str_contains($prodName, $kwNorm)) {$score += 15;
                }
                if (str_contains($variantsText, $kwNorm)) {$score += 6;
                }
            }

            return ['product' => $product, 'score' =>$score];
        });

        $matched =$scoredProducts->where('score', '>', 0)->sortByDesc('score')->take(10);
        $others  =$scoredProducts->where('score', '===', 0)->take(10);

        $matchedText = "";
        foreach ($matched as $item) {$matchedText .= $this->formatProductLine($item['product']);
        }

        $othersText = "";
        foreach ($others as $item) {$othersText .= $this->formatProductLine($item['product']);
        }

        $keywordsDebug = !empty($keywords) ? implode('، ', $keywords) : 'عمومی';

        return "نام فروشگاه: {$storeName}\n"
             . "هزینه ارسال: {$shippingText}\n"
             . "کلمات کلیدی استخراج‌شده از پیام مشتری: {$keywordsDebug}\n\n"
             . "📂 ساختار دسته‌بندی‌ها و محصولات داخلشان:\n" . ($categoriesText ?: "ندارد\n") . "\n"
             . "🎯 محصولات مرتبط با جستجوی کاربر:\n" . ($matchedText ?: "مورد بازیابی مستقیم یافت نشد\n") . "\n"
             . "🛍 سایر محصولات ویترین:\n" . ($othersText ?: "ندارد");
    }

    protected function buildSystemPrompt(string $storeContext): string
    {
        $customerName =$this->user?->first_name ?? 'مشتری عزیز';

        return <<<PROMPT
تو یک مشاور فروش و استایلیست فوق‌العاده حرفه‌ای در فروشگاه تلگرامی هستی و داری با «{$customerName}» گفتگو می‌کنی.

اطلاعات انبار و دسته‌بندی‌های فروشگاه:
{$storeContext}

قوانین سخت‌گیرانه فروشندگی:
1. **تفکیک دقیق دسته‌ها (بسیار مهم):** اگر مشتری نام یک دسته‌بندی خاص را پرسید (مثل «مانتو»، «شومیز»، «شلوار» یا «کیف»)، **فقط** محصولاتی را معرفی کن که در همان دسته‌بندی یا با همان نام هستند. هرگز «شومیز» را به عنوان «مانتو» معرفی نکن! اگر در آن دسته‌بندی محصولی نداریم، صراحتاً بگو در حال حاضر در این دسته محصولی نداریم و گزینه‌های دسته‌های دیگر را پیشنهاد بده.
2. **معرفی کامل گزینه‌های موجود:** اگر کالای درخواستی موجود بود، تمام مدل‌های آن دسته را با قیمت و ویژگی معرفی کن و ID آن‌ها را در `product_ids` بگذار.
3. **قالب خروجی:** خروجی تو باید **فقط و فقط یک JSON معتبر** باشد:
{
  "reply": "متن پاسخ صمیمی به فارسی با ایموجی و تگ <b> برای بولد کردن",
  "product_ids": [1, 2],
  "category_ids": [],
  "action": null
}
PROMPT;
    }

    protected function formatProductLine(Product $p): string
    {
        $finalPrice = number_format($p->getFinalPrice());$catName = $p->category ? $p->category->name : 'عمومی';
        if ($p->category?->parent) {$catName = $p->category->parent->name . ' > ' .$catName;
        }

        $activeVariants = $p->variants->where('is_active', true);$varStr = $activeVariants->isNotEmpty() ? " \vert{} مدل/رنگ: [" . $activeVariants->pluck('variant_name')->implode(', ') . "]" : "";

        return "- [ID: {$p->id}] نام: «{$p->name}» \vert{} دسته: {$catName} | قیمت: {$finalPrice} تومان{$varStr}\n";
    }

    protected function normalizeForMatch(string $str): string
    {
        $str = str_replace(
            ['ي', 'ك', "\u{200c}", '‌'],
            ['ی', 'ک', ' ', ' '],
            mb_strtolower($str)
        );
        return preg_replace('/\s+/u', ' ', trim($str));
    }

  
    protected function formatTelegramHtml(string $text): string
    {
        return trim(preg_replace('/\*\*(.+?)\*\*/u', '<b>$1</b>',$text));
    }

    protected function buildSmartKeyboard(array $productIds, array $categoryIds, ?string $suggestedAction): array
    {
        $keyboard = [];

        if (!empty($productIds)) {$cleanProdIds = array_slice(array_unique(array_map('intval', $productIds)), 0, 6);$products = Product::where('bot_id', $this->bot->id)->where('is_active', true)->whereIn('id',$cleanProdIds)->get();

            foreach ($products as $product) {$price = number_format($product->getFinalPrice());$keyboard[] = [[
                    'text' => "🛍 {$product->name} \vert{} 💰 {$price} ت",
                    'callback_data' => "show_prod_{$product->id}"
                ]];
            }
        }

        if (!empty($categoryIds)) {$cleanCatIds = array_slice(array_unique(array_map('intval', $categoryIds)), 0, 3);$categories = Category::where('bot_id', $this->bot->id)->where('is_active', true)->whereIn('id',$cleanCatIds)->get();

            foreach ($categories as $category) {$keyboard[] = [[
                    'text' => "📂 مشاهده بخش «{$category->name}»",
                    'callback_data' => "products_{$category->id}"
                ]];
            }
        }

        $keyboard[] = [
            ['text' => '📂 همه‌ی دسته‌ها', 'callback_data' => 'show_categories'],
            ['text' => '🔍 جستجو', 'switch_inline_query_current_chat' => ''],
            ['text' => '🏠 منوی اصلی', 'callback_data' => 'main_menu']
        ];

        return $keyboard;
    }
}
