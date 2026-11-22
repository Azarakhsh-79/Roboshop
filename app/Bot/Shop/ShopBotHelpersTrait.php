<?php

namespace App\Bot\Shop;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\App;
use App\Models\OwnerVerification;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;


trait ShopBotHelpersTrait
{
    protected array $session = [];

    protected bool $sessionDirty = false;

    protected function init()
    {
        if (isset($this->update['message'])) {
            $this->chatId = $this->update['message']['chat']['id'];
            $this->messageId = $this->update['message']['message_id'];
            $this->text = $this->update['message']['text'] ?? '';
            $this->data = '';
        } elseif (isset($this->update['callback_query'])) {
            // 🛑 تفکیک دکمه‌های معمولی از دکمه‌های اینلاین
            if (isset($this->update['callback_query']['message'])) {
                $this->chatId = $this->update['callback_query']['message']['chat']['id'];
                $this->messageId = $this->update['callback_query']['message']['message_id'];
            } else {
                $this->chatId = $this->update['callback_query']['from']['id'];
                $this->messageId = null; // پیام‌های اینلاین message_id ندارند
            }

            $this->data = $this->update['callback_query']['data'];
            $this->text = '';
        } elseif (isset($this->update['inline_query'])) {
            $this->inlineQueryId = $this->update['inline_query']['id'];
            $this->inlineQueryText = $this->update['inline_query']['query'];
            $this->chatId = $this->update['inline_query']['from']['id'];
            $this->text = '';
            $this->data = '';
        }

        $userId = $this->user?->id ?? 'guest_' . $this->chatId;
        $this->session = \Illuminate\Support\Facades\Cache::get("shop_session:{$this->bot->id}:{$userId}", []);
        $lang = $this->user?->language ?? 'fa';
        \Illuminate\Support\Facades\App::setLocale($lang);
    }

    protected function getSession($key = null, $default = null)
    {
        if ($key === null) {
            return $this->session;
        }
        return $this->session[$key] ?? $default;
    }

    protected function updateSession(array $newData)
    {
        $this->session = array_merge($this->session, $newData);
        Cache::put("shop_session:{$this->bot->id}:{$this->user->id}", $this->session, 3600 * 2);
    }

    protected function clearSession()
    {
        $this->session = [];
        $this->sessionDirty = false;
        $userId = $this->user?->id ?? 'guest_' . $this->chatId;
        Cache::forget("shop_session:{$this->bot->id}:{$userId}");
    }

    /**
     * 🛑 سیستم بررسی عضویت اجباری در کانال (با خروجی Boolean)
     */
    protected function checkForceJoin(): bool
    {
        // آیا قابلیت عضویت اجباری روشن است؟
        $forceJoin = $this->bot->settings->firstWhere('key', 'force_join')?->value === '1';
        if (!$forceJoin)
            return false;

        $channelId = $this->bot->settings->firstWhere('key', 'channel_id')?->value;
        if (empty($channelId))
            return false;

        // تغییر پیام به حالت Loading وقتی کاربر روی دکمه کلیک می‌کند
        if ($this->data === 'check_force_join') {
            $this->sendRequest('answerCallbackQuery', [
                'callback_query_id' => $this->update['callback_query']['id'],
                'text' => '⏳ در حال بررسی وضعیت عضویت شما...'
            ]);
        }

        $userId = $this->user->chat_id;
        $chatId = str_starts_with($channelId, '@') ? $channelId : '@' . $channelId;

        // پرسش از سرور تلگرام برای وضعیت کاربر
        $response = Http::timeout(5)->get("https://api.telegram.org/bot{$this->bot->bot_token}/getChatMember", [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);

        if ($response->successful()) {
            $status = $response->json('result.status');
            // اگر شخص هنوز عضو است:
            if (in_array($status, ['member', 'administrator', 'creator', 'restricted'])) {
                if ($this->data === 'check_force_join') {
                    $this->deleteMessage($this->messageId);
                    $this->showMainMenu();
                    return true; // چون منو را نشان دادیم، می‌گوییم توقف کند
                }
                return false; // اجازه عبور به کدهای بعدی ربات
            }
        }

        // اگر کاربر روی دکمه زده ولی عضو نشده:
        if ($this->data === 'check_force_join') {
            $this->sendRequest('answerCallbackQuery', [
                'callback_query_id' => $this->update['callback_query']['id'],
                'text' => '❌ شما هنوز در کانال عضو نشده‌اید!',
                'show_alert' => true // اخطار قرمز روی صفحه
            ]);
            return true;
        }

        // 🔒 قفل کردن کاربر (نمایش پیام عضویت اجباری)
        $channelUrl = str_replace('@', '', $channelId);
        $joinLink = "https://t.me/{$channelUrl}";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '📢 ورود و عضویت در کانال', 'url' => $joinLink]],
                [['text' => '✅ عضو شدم', 'callback_data' => 'check_force_join']]
            ]
        ];

        $text = "🔒 <b>عضویت اجباری</b>\n\nکاربر گرامی، جهت استفاده از فروشگاه و مشاهده محصولات، ابتدا باید در کانال رسمی ما عضو شوید.\n\n👇 <i>لطفاً روی دکمه زیر کلیک کرده و پس از عضویت، دکمه «عضو شدم» را لمس کنید.</i>";

        if (isset($this->update['callback_query'])) {
            $this->editMessageText($text, ['reply_markup' => json_encode($keyboard)]);
        } else {
            $this->sendMessage($text, ['reply_markup' => json_encode($keyboard)]);
        }

        return true; // ⛔️ توقف کدهای بعدی ربات
    }
    // ================================================================
    // 📡 بخش ۲: متدهای ارسال درخواست (API Methods)
    // ================================================================

    // در ShopBotHelpersTrait.php
    protected function sendRequest($method, $params = [])
    {
        $url = "https://api.telegram.org/bot{$this->bot->bot_token}/{$method}";
        $start = microtime(true);
        try {
            $response = Http::timeout(5)->post($url, $params);
            return $response->json();
        } catch (\Exception $e) {
            Log::error("Telegram API Error [{$this->bot->bot_username}]: " . $e->getMessage());
            return null;
        } finally {
            $this->telegramTimeMs += (microtime(true) - $start) * 1000; // 👈 جدید
        }
    }

    protected function sendMessage($text, $extra = [])
    {
        $params = array_merge([
            'chat_id' => $this->chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ], $extra);
        return $this->sendRequest('sendMessage', $params);
    }

    protected function editMessageText($text, $extra = [])
    {
        $params = array_merge([
            'chat_id' => $this->chatId,
            'message_id' => $this->messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ], $extra);
        return $this->sendRequest('editMessageText', $params);
    }

    protected function deleteMessage($ids, $chatId = null)
    {
        // 🛑 اگر آیدی پیام خالی یا نال بود، هیچ ریکوئستی به تلگرام نفرست
        if (empty($ids)) {
            return;
        }

        $chatId = $chatId ?? $this->chatId;

        if (is_array($ids)) {
            foreach (array_chunk($ids, 100) as $chunk) {
                $this->sendRequest('deleteMessages', ['chat_id' => $chatId, 'message_ids' => $chunk]);
            }
        } else {
            $this->sendRequest('deleteMessage', [
                'chat_id' => $chatId,
                'message_id' => $ids
            ]);
        }
    }

    protected function answerCallback($callbackId, $text = null)
    {
        $this->sendRequest('answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => $text]);
    }

    /**
     * مثل sendRequest ولی برای درخواست‌هایی که نیاز به آپلود فایل (multipart) دارن.
     * از فایل به‌صورت stream استفاده می‌کنه، نه file_get_contents، تا کل فایل یکجا وارد حافظه نشه.
     */
    protected function sendRequestWithAttachment(string $method, array $params, string $fileField, string $filePath, string $filename)
    {
        $url = "https://api.telegram.org/bot{$this->bot->bot_token}/{$method}";
        $start = microtime(true);
        $stream = null;

        try {
            $stream = fopen($filePath, 'r');
            if ($stream === false) {
                return null;
            }

            $response = Http::timeout(15)
                ->attach($fileField, $stream, $filename)
                ->post($url, $params);

            return $response->json();

        } catch (\Exception $e) {
            Log::error("ShopBot Telegram Upload Error [{$this->bot->bot_username}]: " . $e->getMessage());
            return null;

        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            $this->telegramTimeMs += (microtime(true) - $start) * 1000;
            $this->telegramCallCount++;
        }
    }

    // ================================================================
    // 🖥️ بخش ۳: رابط کاربری (UI & Menus)
    // ================================================================

    protected function showMainMenu()
    {
        // ۱. دریافت و پردازش متن خوش‌آمدگویی
        $welcomeText = $this->bot->settings->firstWhere('key', 'welcome_message')?->value;

        if ($welcomeText) {
            $welcomeText = str_replace(
                ['{name}', '{bot_name}'],
                [$this->user->first_name ?? 'کاربر', $this->bot->bot_name],
                $welcomeText
            );
        } else {
            $welcomeText = __('shop.welcome', [
                'name' => $this->user->first_name ?? 'کاربر',
                'bot_name' => $this->bot->bot_name
            ]);
        }

        $inlineKeyboard = [];

         // =================================================================
        // 🚀 دکمه مینی‌اپلیکیشن (فقط برای ربات دمو)
        // =================================================================

        // 🌟 ردیف طلایی: دکمه اتصال اکانت (فقط اگر شماره ثبت نشده باشد)
        if (empty($this->user->phone)) {
            $inlineKeyboard[] = [
                ['text' => '📱 ثبت شماره موبایل', 'callback_data' => 'req_contact']
            ];
        }
        if (strtolower($this->bot->bot_username) === 'demo_roboshop_bot') {
            $webAppUrl = url("/app/{$this->bot->bot_username}");
            $inlineKeyboard[] = [
                [
                    'text' => '📱 وب اپلیکیشن ',
                    'web_app' => ['url' => $webAppUrl]
                ]
            ];
        }

        // 🥇 ردیف اول: دکمه محصولات تخفیف‌دار (بالاترین قسمت منو)
        $showDiscountBtn = $this->bot->settings->firstWhere('key', 'show_discounted_products')?->value;
        if ($showDiscountBtn === '1' || $showDiscountBtn === 'true') {
            $inlineKeyboard[] = [
                ['text' => '🔥 محصولات تخفیف‌دار', 'callback_data' => 'discounted_products']
            ];
        }

        // 🥈 ردیف‌های میانی: دسته‌بندی‌های فروشگاه
        $maxCategoriesToShow = 10;
        $categoriesQuery = Category::where('bot_id', $this->bot->id)
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->withExists(['children as has_children' => fn($q) => $q->where('is_active', true)])
            ->orderBy('sort_order', 'asc');

        $totalCategories = $categoriesQuery->count();
        $categories = $categoriesQuery->limit($maxCategoriesToShow)->get();

        $row = [];
        foreach ($categories as $category) {
            $callbackData = $category->has_children ? "subcat_{$category->id}" : "products_{$category->id}";
            $row[] = ['text' => $category->name, 'callback_data' => $callbackData];

            if (count($row) == 2) {
                $inlineKeyboard[] = $row;
                $row = [];
            }
        }

        // اضافه کردن دسته‌بندی فرد (در صورت وجود)
        if (!empty($row)) {
            $inlineKeyboard[] = $row;
        }

        // اگر تعداد دسته‌بندی‌ها زیاد بود، دکمه مشاهده همه اضافه می‌شود
        if ($totalCategories > $maxCategoriesToShow) {
            $inlineKeyboard[] = [
                ['text' => '🗂 مشاهده همه دسته‌بندی‌ها', 'callback_data' => 'all_categories']
            ];
        }

        // 🥉 ردیف زیر دسته‌بندی‌ها: دکمه جدیدترین محصولات
        $inlineKeyboard[] = [
            ['text' => '🌟 جدیدترین محصولات', 'callback_data' => 'latest_products']
        ];



        // 🚀 دکمه‌های ناوبری و کاربری (پایین منو)
        $inlineKeyboard[] = [
            ['text' => '🔍 جستجوی کالا', 'switch_inline_query_current_chat' => '']
        ];

        $inlineKeyboard[] = [
            ['text' => __('shop.btn_cart', [], 'fa') ?? '🛒 سبد خرید', 'callback_data' => 'view_cart'],
            ['text' => __('shop.btn_favorites', [], 'fa') ?? '❤️ علاقه‌مندی‌ها', 'callback_data' => 'my_favorites'],
            ['text' => __('shop.btn_track_order', [], 'fa') ?? '📦 پیگیری سفارشات', 'callback_data' => 'my_orders_list'],
        ];

        $inlineKeyboard[] = [
            ['text' => '🤖', 'callback_data' => 'about'],
            ['text' => __('shop.btn_support', [], 'fa') ?? '🎧 پشتیبانی', 'callback_data' => 'support_info'],
            ['text' => __('shop.btn_profile', [], 'fa') ?? '👤 حساب کاربری', 'callback_data' => 'user_profile'],
        ];

        // ⚙️ دسترسی پنل ادمین (فقط برای مدیران)
        if ($this->user->is_admin == 1) {
            $inlineKeyboard[] = [
                ['text' => '⚙️ ورود به پنل مدیریت ربات', 'callback_data' => 'admin_panel']
            ];
        }

        $replyMarkup = ['inline_keyboard' => $inlineKeyboard];

        // مدیریت ارسال یا ویرایش پیام (جلوگیری از پرش تصویر)
        if (isset($this->update['callback_query'])) {
            $message = $this->update['callback_query']['message'] ?? [];
            $hasMedia = isset($message['photo']) || isset($message['video']) || isset($message['document']) || isset($message['animation']);

            if ($hasMedia) {
                $this->deleteMessage($this->messageId);
                $this->sendMessage($welcomeText, ['reply_markup' => json_encode($replyMarkup)]);
            } else {
                $this->editMessageText($welcomeText, ['reply_markup' => json_encode($replyMarkup)]);
            }
        } else {
            $this->sendMessage($welcomeText, ['reply_markup' => json_encode($replyMarkup)]);
        }
    }

    /**
     * استخراج محصول از دیتابیس و نمایش آن
     */
    protected function showProduct($productId)
    {
        $product = Product::where('bot_id', $this->bot->id)
            ->where('id', $productId)
            ->where('is_active', true)
            ->first();

        if (!$product) {
            $this->sendMessage("❌ محصول مورد نظر یافت نشد یا در حال حاضر ناموجود است.");
            return;
        }

        // فقط با یک خط، تمام پروسه ساخت متن، دکمه و عکس را به متد مرکزی می‌سپاریم!
        $this->sendProductMessage($product);
    }


    /**
     * نمایش هوشمند دسته‌بندی‌ها با قابلیت صفحه‌بندی (Pagination)
     */

    protected function showCategories($parentId = null, $isAdmin = false, $page = 1)
    {

        $parentCategory = $parentId
            ? Category::find($parentId)
            : null;

        $query = Category::where('bot_id', $this->bot->id)
            ->when(
                !$isAdmin,
                fn($q) => $q->where('is_active', true)
            )
            ->when(
                $parentId,
                fn($q) => $q->where('parent_id', $parentId),
                fn($q) => $q->whereNull('parent_id')
            )
            ->withExists([
                'children as has_children' => function ($q) use ($isAdmin) {

                    if (!$isAdmin) {
                        $q->where('is_active', true);
                    }

                }
            ]);


        $perPage = 10;

        $totalCategories = $query->count();

        $totalPages = max(
            1,
            (int) ceil($totalCategories / $perPage)
        );

        $page = max(
            1,
            min($page, $totalPages)
        );

        $categories = $query
            ->orderBy('sort_order', 'asc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();


        if ($isAdmin) {

            if ($parentCategory) {

                $text =
                    "⚙️ <b>مدیریت زیردسته‌ها</b>\n"
                    . "└ <b>{$parentCategory->name}</b>\n\n"
                    . "📌 زیردسته‌های این بخش را از لیست زیر مدیریت کنید.\n"
                    . "با انتخاب هر مورد می‌توانید اطلاعات و وضعیت آن را تغییر دهید.";

            } else {

                $text =
                    "🗂 <b>مدیریت دسته‌بندی فروشگاه</b>\n\n"
                    . "✨ دسته‌های زیر، <b>دسته‌های اصلی فروشگاه</b> هستند "
                    . "و مستقیماً در منوی اصلی مشتریان نمایش داده می‌شوند.\n\n"
                    . "📂 با ورود به هر دسته می‌توانید زیردسته‌های آن را ایجاد و مدیریت کنید.\n\n"
                    . "👇 <i>دسته موردنظر را انتخاب کنید:</i>";
            }

        } else {

            if ($parentCategory) {

                $text =
                    " <b>{$parentCategory->name}</b>\n\n";

                if ($parentCategory->description) {

                    $text .=
                        "<blockquote expandable>"
                        . "✨ {$parentCategory->description}"
                        . "</blockquote>\n\n";
                }

                if ($categories->isEmpty()) {

                    $text .=
                        "🛍 <i>محصولی در این بخش قرار نگرفته است.</i>\n\n"
                        . "به‌زودی محصولات جدیدی به این بخش اضافه خواهد شد. 🌱";

                } else {

                    $text .=
                        "🛍 <b>دسته‌بندی محصولات</b>\n\n"
                        . "👇 یکی از گزینه‌های زیر را انتخاب کنید:";
                }

            } else {

                $text =
                    "🛍 <b>دسته‌بندی محصولات</b>\n\n"
                    . "👇 <i>دسته موردنظر را انتخاب کنید:</i>";
            }
        }


        $keyboard = [];
        $row = [];

        foreach ($categories as $category) {

            // --------------------------------------------------------
            // حالت ادمین
            // --------------------------------------------------------

            if ($isAdmin) {

                $status = $category->is_active
                    ? '🟢'
                    : '🔴';

                $icon = $category->has_children
                    ? ''
                    : '';

                $buttonText =
                    "{$icon} {$status} {$category->name}";

                $callbackData =
                    "admin_cat_show_{$category->id}";

            }

            // --------------------------------------------------------
            // حالت مشتری
            // --------------------------------------------------------
            else {

                $icon = $category->has_children
                    ? ''
                    : '';

                $buttonText =
                    "{$icon} {$category->name}";

                $callbackData = $category->has_children
                    ? "subcat_{$category->id}"
                    : "products_{$category->id}";
            }


            $row[] = [
                'text' => $buttonText,
                'callback_data' => $callbackData,
            ];


            // دو دکمه در هر ردیف
            if (count($row) === 2) {

                $keyboard[] = $row;

                $row = [];
            }
        }


        // اگر یک دکمه باقی مانده بود
        if (!empty($row)) {

            $keyboard[] = $row;
        }


        $keyboard = $this->addNavigationButtons(
            $keyboard,
            $totalPages,
            $page,
            $parentId,
            $isAdmin,
            $parentCategory
        );


        $this->editMessageText(
            $text,
            [
                'reply_markup' => json_encode([
                    'inline_keyboard' => $keyboard,
                ]),
            ]
        );
    }





    protected function addNavigationButtons($keyboard, $totalPages, $page, $parentId, $isAdmin, $parentCategory)
    {
        // دکمه‌های صفحه‌بندی
        if ($totalPages > 1) {
            $pageControls = [];
            $adminFlag = $isAdmin ? 1 : 0;
            $parentFlag = $parentId ?: 'null';

            if ($page > 1)
                $pageControls[] = ['text' => '«', 'callback_data' => "cat_page_{$parentFlag}_{$adminFlag}_" . ($page - 1)];
            $pageControls[] = ['text' => "📄 {$page} از {$totalPages}", 'callback_data' => "ignore_cb"];
            if ($page < $totalPages)
                $pageControls[] = ['text' => '»', 'callback_data' => "cat_page_{$parentFlag}_{$adminFlag}_" . ($page + 1)];

            $keyboard[] = $pageControls;
        }

        // دکمه‌های پایین (بازگشت)
        if ($isAdmin) {
            $keyboard[] = $parentId
                ? [['text' => '➕ افزودن زیردسته', 'callback_data' => "admin_cat_addsub_{$parentId}"], ['text' => '🔙 بازگشت', 'callback_data' => ($parentCategory->parent_id ? "admin_cat_show_{$parentCategory->parent_id}" : "admin_manage_categories")]]
                : [['text' => '➕ افزودن دسته اصلی', 'callback_data' => 'admin_add_main_category'], ['text' => '⚙️ پنل مدیریت', 'callback_data' => 'admin_panel']];
        } else {
            $keyboard[] = $parentId
                ? [['text' => '🏠 منوی اصلی', 'callback_data' => 'main_menu'], ['text' => '« بازگشت', 'callback_data' => ($parentCategory->parent_id ? "subcat_{$parentCategory->parent_id}" : "main_menu")]]
                : [['text' => '🏠 منوی اصلی', 'callback_data' => 'main_menu']];
        }

        return $keyboard;
    }

    protected function showProfile()
    {
        $text = __('shop.profile_info', [
            'name' => $this->user->first_name,
            'chat_id' => $this->chatId
        ]);

        $this->sendMessage($text);
    }

    protected function showSupport()
    {
        $supportInfo = $this->bot->settings()->where('key', 'support_contact')->value('value') ?? __('shop.support_not_set');
        $this->sendMessage(__('shop.support_info', ['info' => $supportInfo]));
    }

    /**
     * متد جامع و مرکزی برای نمایش محصول (قابل استفاده در تمام بخش‌های ربات)
     * @param \App\Models\Product $product
     * @param array|null $backButton دکمه بازگشت سفارشی (اختیاری)
     */
    protected function sendProductMessage($product, $backButton = null)
    {
        $isAdmin = ($this->user->is_admin == 1);
        \App\Services\TelegramProductService::send($product, $this->chatId, false, $backButton, $this->user->id, $isAdmin);
    }




    // ================================================================
    // ⚙️ بخش ۴: مدیریت فرایندها (Logic)
    // ================================================================

    protected function handleStart()
    {
        $this->clearSession();

        // مسیر ۱: اگر کاربر در حال تایید مدیریت ربات است
        if (str_starts_with($this->text, '/start verify_')) {
            $parts = explode('_', str_replace('/start verify_', '', $this->text));
            $token = $parts[0] ?? null;
            $siteUserId = $parts[1] ?? null;

            if ($token && $siteUserId) {
                $this->verifyAdmin($token, $siteUserId);
            } else {
                $this->sendMessage(__('shop.verify_link_incomplete'));
            }
            return;
        }

        // 🚀 مسیر ۲ (جدید): اگر کاربر از طریق کانال روی محصولی کلیک کرده است
        if (str_starts_with($this->text, '/start product_')) {
            $productId = str_replace('/start product_', '', $this->text);
            $this->showProduct($productId);
            return;
        }

        // در غیر این صورت منوی اصلی را نشان بده
        $this->showMainMenu();
    }
    protected function verifyAdmin(string $token, string $siteUserId)
    {
        $verification = OwnerVerification::where('bot_id', $this->bot->id)
            ->where('token', $token)
            ->where('verification_status', 'pending_usable')
            ->first();

        if (!$verification) {
            $this->sendMessage("❌ لینک تایید نامعتبر یا منقضی شده است.");
            return;
        }

        // اکانت اصلی مالک در پنل سایت
        $siteOwner = User::find($siteUserId);

        if ($siteOwner) {
            // ۱. آپدیت چت‌آیدی در اکانت اصلی سایت (برای ارسال نوتیفیکیشن‌های پنل سایت به تلگرام ادمین)
            $siteOwner->chat_id = $this->chatId;
            $siteOwner->save();

            // ۲. 🌟 ساخت یا پیداکردن پروفایل کاربریِ این شخص درون ربات فروشگاهی و ادمین کردن او 🌟
            $this->user = User::firstOrCreate(
                [
                    'bot_id' => $this->bot->id,
                    'chat_id' => $this->chatId,
                ]
            );

            // مطمئن می‌شویم که حتماً ادمین است
            $this->user->is_admin = 1;
            $this->user->save();

            // ۳. فعال کردن ربات
            $this->bot->status = 'active';
            $this->bot->save();

            // ۴. باطل کردن لینک تایید
            $verification->verification_status = 'verified';
            $verification->save();

            $this->sendMessage("✅ مالکیت ربات با موفقیت تایید شد!\nفروشگاه شما اکنون فعال است.");
            $this->showMainMenu();
        } else {
            $this->sendMessage("❌ خطای سیستمی: اکانت سایت شما یافت نشد.");
        }
    }



    // ================================================================
    // ⚙️ بخش ۵: مسیرهای ادمین (Admin Logic)
    // ================================================================


    public function showAdminPanel()
    {
        $botId = $this->bot->id;

        // ==========================================
        // 📊 ۱. استخراج آمارهای لحظه‌ای از دیتابیس
        // ==========================================

        // دریافت تعداد سفارشات جدید (فرض می‌کنیم وضعیت آن‌ها در دیتابیس pending است)
        // 💡 نکته: کلمه 'pending' را با توجه به ساختار دیتابیس خود (مثلاً 'paid' یا 'processing') تغییر دهید
        $newOrdersCount = \App\Models\Invoice::where('bot_id', $botId)
            ->where('status', 'paid')
            ->count();

        // دریافت تعداد کل محصولات ثبت شده در فروشگاه
        //$productsCount = \App\Models\Product::where('bot_id', $botId)->count();

        // بررسی وضعیت فعالیت فروشگاه از تنظیمات
        $isActive = $this->bot->settings->firstWhere('key', 'is_active')?->value;
        $statusText = ($isActive === '1' || strtolower($isActive) === 'true') ? '🟢 فعال و آنلاین' : '🔴 غیرفعال (تعطیل)';

        // ==========================================
        // 🗓 ۲. ساخت تاریخ و ساعت شمسی
        // ==========================================
        $currentDate = \Morilog\Jalali\Jalalian::now()->format('Y/m/d');
        $currentTime = \Morilog\Jalali\Jalalian::now()->format('H:i');

        // ==========================================
        // 🔘 ۳. هوشمندسازی متن دکمه‌ها
        // ==========================================
        // اگر سفارش جدیدی بود، یک آلارم به دکمه اضافه می‌شود
        $ordersBtnText = $newOrdersCount > 0
            ? "📦 سفارشات جدید ({$newOrdersCount} 🔴)"
            : "📦 مدیریت سفارشات";

        // نمایش تعداد محصولات روی دکمه دسته‌بندی‌ها
        $productsBtnText = "🗂 محصولات و دسته‌ها";

        // ==========================================
        // 📝 ۴. تولید متن داشبورد
        // ==========================================
        $text = "🛠 <b>داشبورد مدیریت هوشمند فروشگاه</b>\n";
        $text .= "➖➖➖➖➖➖➖➖➖➖\n";
        $text .= "🗓 {$currentDate} {$currentTime}\u{200f}\n";
        $text .= "🚦 <b>وضعیت ربات:</b> {$statusText}\n\n";

        // پیام هشدار در صورت وجود سفارش جدید
        if ($newOrdersCount > 0) {
            $text .= "⚠️ <b>توجه:</b> شما <b>{$newOrdersCount}</b> سفارش بررسی نشده دارید! لطفاً در اسرع وقت اقدام کنید.\n\n";
        } else {
            $text .= "✅ در حال حاضر تمام سفارشات بررسی شده‌اند.\n\n";
        }

        $text .= "👇 <b>لطفاً بخش مورد نظر خود را انتخاب کنید:</b>";

        // ==========================================
        // 🎛 ۵. چیدمان کیبورد شیشه‌ای
        // ==========================================

        $magicLoginUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'bot.magic.login',
            now()->addMinutes(5),
            ['user' => $this->user->id] // آیدی کاربری که الان در ربات است
        );



        $keyboard = [
            [
                ['text' => '🌐 ورود مستقیم به پنل مدیریت', 'url' => $magicLoginUrl]
            ],
            [
                ['text' => $ordersBtnText, 'callback_data' => 'admin_manage_orders'],
            ],
            [
                ['text' => $productsBtnText, 'callback_data' => 'admin_manage_categories']
            ],
            [
                ['text' => '👥 مدیریت ادمین‌ها', 'callback_data' => 'admin_manage_admins'],
                ['text' => '⚙️ تنظیمات فروشگاه', 'callback_data' => 'admin_store_settings']
            ],
            [
                ['text' => '📊 گزارشات و آمار فروش', 'callback_data' => 'admin_sales_reports'],
                // اشتراک فروشگاه (پلن) - این دکمه فقط برای مدیران اصلی ربات نمایش داده می‌شود
                ['text' => '💳 اشتراک و پلن فروشگاه', 'callback_data' => 'admin_subscription']
            ],
            [
                // دکمه ارتباط مستقیم مالکین ربات با شما
                ['text' => '👨‍💻 ارتباط با پشتیبانی پلتفرم (توسعه‌دهنده)', 'url' => 'https://t.me/Amir_soleimani_79']
            ],
            [
                ['text' => '🔙 بازگشت به منوی اصلی ربات', 'callback_data' => 'main_menu']
            ]
        ];

        // ==========================================
        // 🚀 ۶. مدیریت رندر پیام (جلوگیری از خطای مدیا)
        // ==========================================
        // اگر ادمین از روی یک پیام عکس‌دار (مثل ویترین محصول) دکمه بازگشت به پنل را زد، پیام پاک شود
        $message = $this->update['callback_query']['message'] ?? [];
        $hasMedia = isset($message['photo']) || isset($message['video']) || isset($message['document']) || isset($message['animation']);

        if ($hasMedia) {
            $this->deleteMessage($this->messageId);
            $this->sendMessage($text, [
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
                'parse_mode' => 'HTML'
            ]);
        } else {
            $this->editMessageText($text, [
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
                'parse_mode' => 'HTML'
            ]);
        }
    }

    // protected function setSession($key, $value)
    // {
    //     $this->session[$key] = $value;
    //     $userId = $this->user?->id ?? 'guest_' . $this->chatId;
    //     Cache::put("shop_session:{$this->bot->id}:{$userId}", $this->session);
    // }



    protected function setSession($key, $value)
    {
        $this->session[$key] = $value;
        $this->sessionDirty = true;   // فقط علامت می‌زنیم که «تغییر کرده»، ذخیره نمی‌کنیم
    }

    public function __destruct()
    {
        $this->persistSession();      // این خودکار در پایان اجرای هندلر صدا زده میشه
    }

    protected function persistSession()
    {
        if (!$this->sessionDirty)
            return; // اگه اصلاً تغییری نبوده، حتی همون یک بار هم ننویس
        $userId = $this->user?->id ?? 'guest_' . $this->chatId;
        Cache::put("shop_session:{$this->bot->id}:{$userId}", $this->session, 3600 * 2);
        $this->sessionDirty = false;
    }



    /**
     * مدیریت عملیات روی یک دسته‌بندی خاص (نمایش، تغییر وضعیت، حذف)
     */
    public function handleAdminCategoryAction($action, $id)
    {
        $category = Category::where('bot_id', $this->bot->id)->find($id);

        if (!$category) {
            // استفاده از sendRequest در صورتی که متد answerCallback را نساخته‌اید
            $this->sendRequest('answerCallbackQuery', ['callback_query_id' => $this->update['callback_query']['id'], 'text' => "❌ دسته‌بندی یافت نشد!", 'show_alert' => true]);
            return;
        }

        switch ($action) {
            case 'show': // نمایش جزئیات دسته‌بندی و لیست زیردسته‌ها یا محصولات در همان صفحه
                $subCategories = Category::where('parent_id', $category->id)->orderBy('sort_order', 'asc')->get();
                $subCount = $subCategories->count();
                // 🚀 گرفتن تعداد محصولات این دسته
                $productCount = Product::where('category_id', $category->id)->count();

                $status = $category->is_active ? 'فعال 🟢' : 'غیرفعال 🔴';

                // 🚀 نمایش توضیحات با استفاده از قابلیت شیک Blockquote (نقل قول) تلگرام
                $descText = $category->description
                    ? "📝 <b>توضیحات:</b>\n<blockquote expandable>{$category->description}</blockquote>\n"
                    : "📝 <b>توضیحات:</b> <i>ثبت نشده</i>\n\n";

                $text = "🏷 : <b>{$category->name}</b> \n";
                $text .= $descText;
                $text .= "📊 <b>وضعیت:</b> {$status}\n";

                $keyboard = [];
                $row = [];

                // ==========================================
                // 1️⃣ حالت اول: دسته دارای زیردسته است
                // ==========================================
                if ($subCount > 0) {
                    $text .= "📁 <b>تعداد زیردسته‌ها:</b> {$subCount}\n\n";
                    $text .= "<b>لیست زیردسته‌ها: 👇</b>\n";

                    // رندر کردن زیردسته‌ها در ردیف‌های بالایی (دو ستونه)
                    foreach ($subCategories as $subCat) {
                        $subStatus = $subCat->is_active ? '🟢' : '🔴';
                        $row[] = [
                            'text' => "{$subStatus} {$subCat->name}",
                            'callback_data' => "admin_cat_show_{$subCat->id}"
                        ];

                        if (count($row) == 2) {
                            $keyboard[] = $row;
                            $row = [];
                        }
                    }
                    if (!empty($row)) {
                        $keyboard[] = $row;
                    }

                    // دکمه‌های مخصوص این حالت
                    $keyboard[] = [
                        ['text' => '➕ افزودن زیردسته', 'callback_data' => "admin_cat_addsub_{$category->id}"],
                        ['text' => '🎁 تخفیف گروهی', 'callback_data' => "admin_cat_bulkdiscount_{$category->id}"]
                    ];
                }
                // ==========================================
                // 2️⃣ حالت دوم: دسته زیردسته ندارد اما محصول دارد
                // ==========================================
                elseif ($productCount > 0) {
                    $text .= "📦 <b>تعداد محصولات:</b> {$productCount}\n\n";

                    // دکمه‌های مخصوص محصولات
                    $keyboard[] = [['text' => '📋 مشاهده و مدیریت محصولات', 'callback_data' => "admin_prod_list_{$category->id}_1"]];
                    $keyboard[] = [['text' => '➕ ایجاد محصول جدید', 'callback_data' => "admin_prod_add_to_cat_{$category->id}"]];

                    $keyboard[] = [
                        ['text' => '🔄 انتقال محصولات', 'callback_data' => "admin_cat_moveprod_{$category->id}"],
                        ['text' => '🎁 تخفیف گروهی', 'callback_data' => "admin_cat_bulkdiscount_{$category->id}"]
                    ];
                }
                // ==========================================
                // 3️⃣ حالت سوم: دسته کاملاً خالی است
                // ==========================================
                else {
                    $text .= "📉 <i>این دسته‌بندی کاملاً خالی است.</i>\n\n";

                    // چون خالی است، ادمین هم می‌تواند زیردسته بسازد هم محصول
                    $keyboard[] = [['text' => '➕ ایجاد اولین محصول', 'callback_data' => "admin_prod_add_to_cat_{$category->id}"]];
                    $keyboard[] = [['text' => '➕ افزودن زیردسته', 'callback_data' => "admin_cat_addsub_{$category->id}"]];
                }

                // ==========================================
                // ⚙️ دکمه‌های مدیریتی مشترک (همیشه نمایش داده می‌شوند)
                // ==========================================
                $keyboard[] = [
                    ['text' => '✏️ ویرایش نام', 'callback_data' => "admin_cat_editname_{$category->id}"],
                    ['text' => '📝 ویرایش توضیحات', 'callback_data' => "admin_cat_editdesc_{$category->id}"]
                ];

                $keyboard[] = [
                    ['text' => $category->is_active ? '🔴 غیرفعال کردن ' : '🟢 فعال کردن ', 'callback_data' => "admin_cat_toggle_{$category->id}"],
                    ['text' => '🗑 حذف دسته', 'callback_data' => "admin_cat_del_{$category->id}"]
                ];

                // 🔙 دکمه بازگشت هوشمند (تمام‌عرض در پایین)
                $backData = $category->parent_id ? "admin_cat_show_{$category->parent_id}" : "admin_manage_categories";
                $keyboard[] = [
                    ['text' => '🔙 بازگشت', 'callback_data' => $backData]
                ];

                $this->editMessageText($text, [
                    'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),

                ]);
                break;

            case 'addsub': // ایجاد زیردسته
                // 🚨 بررسی: آیا این دسته خودش محصول دارد؟
                $hasProducts = Product::where('category_id', $category->id)->exists();
                if ($hasProducts) {
                    $this->sendRequest('answerCallbackQuery', [
                        'callback_query_id' => $this->update['callback_query']['id'],
                        'text' => "❌ این دسته دارای محصول است! دسته‌ای که محصول دارد نمی‌تواند زیردسته داشته باشد.",
                        'show_alert' => true
                    ]);
                    return;
                }

                $keyboard = [
                    [['text' => '🔙 لغو و بازگشت', 'callback_data' => "admin_cat_show_{$category->id}"]]
                ];

                $this->editMessageText(
                    "✨ <b>افزودن زیردسته جدید</b>\n\n"
                    . "📂 زیردسته برای: <b>{$category->name}</b>\n\n"
                    . "🏷 لطفاً <b>نام زیردسته</b> موردنظر را ارسال کنید.\n\n"
                    . "💡 <b>برای ظاهر جذاب‌تر فروشگاه:</b>\n"
                    . "می‌توانید از یک ایموجی مرتبط در ابتدای نام استفاده کنید.\n\n"
                    . "مثلاً:\n"
                    . "👔 مردانه\n"
                    . "👗 زنانه\n"
                    . "👟 کفش و کتانی\n"
                    . "🧢 اکسسوری\n\n"
                    . "✨ <i>زیردسته پس از ایجاد، داخل دسته «{$category->name}» نمایش داده می‌شود.</i>\n\n"
                    . "❌ برای انصراف، دکمه زیر را انتخاب کنید.",
                    [
                        'reply_markup' => json_encode([
                            'inline_keyboard' => $keyboard
                        ])
                    ]
                );


                $this->setSession('state', "admin_add_sub_category_{$category->id}");

                $messageId = $this->update['callback_query']['message']['message_id'] ?? null;
                if ($messageId) {
                    $this->setSession('message_id', $messageId);
                }
                break;

            case 'toggle': // خاموش یا روشن کردن دسته
                $category->is_active = !$category->is_active;
                $category->save();
                $this->handleAdminCategoryAction('show', $category->id); // رفرش کردن صفحه
                break;

            case 'del':
                // ۱. بررسی وجود زیردسته‌ها (آیا این دسته فرزندی دارد؟)
                $hasSubcategories = Category::where('parent_id', $category->id)->exists();
                if ($hasSubcategories) {
                    $this->sendRequest('answerCallbackQuery', [
                        'callback_query_id' => $this->update['callback_query']['id'],
                        'text' => "❌ این دسته دارای زیردسته است! لطفاً ابتدا زیردسته‌های آن را حذف یا جابجا کنید.",
                        'show_alert' => true
                    ]);
                    return; // خروج از عملیات
                }

                // ۲. بررسی وجود محصولات (آیا محصولی به این دسته متصل است؟)
                $hasProducts = Product::where('category_id', $category->id)->exists();
                if ($hasProducts) {
                    $this->sendRequest('answerCallbackQuery', [
                        'callback_query_id' => $this->update['callback_query']['id'],
                        'text' => "❌ محصولاتی در این دسته وجود دارند! لطفاً ابتدا محصولات را حذف یا به دسته دیگری منتقل کنید.",
                        'show_alert' => true
                    ]);
                    return; // خروج از عملیات
                }

                // ۳. در صورتی که دسته خالی و امن بود، آن را حذف می‌کنیم
                try {
                    $parentId = $category->parent_id;
                    $category->delete();

                    if ($parentId) {
                        // بعد از حذف یک زیردسته، صفحه نمایشِ دسته پدر را لود می‌کنیم
                        $this->handleAdminCategoryAction('show', $parentId);
                    } else {
                        // اگر دسته اصلی بود، لیست دسته‌های اصلی را نشان بده
                        $this->showCategories(null, true);
                    }

                    // یک پیام موفقیت کوتاه (Toast) به ادمین نشان می‌دهیم
                    $this->sendRequest('answerCallbackQuery', [
                        'callback_query_id' => $this->update['callback_query']['id'],
                        'text' => "✅ دسته با موفقیت حذف شد.",
                        'show_alert' => false
                    ]);

                } catch (\Exception $e) {
                    // این بلاک فقط برای خطاهای غیرمنتظره سیستمی باقی می‌ماند
                    $this->sendRequest('answerCallbackQuery', [
                        'callback_query_id' => $this->update['callback_query']['id'],
                        'text' => "❌ خطای سیستمی رخ داد. لطفاً به پشتیبانی اطلاع دهید.",
                        'show_alert' => true
                    ]);
                }
                break;

            case 'editdesc':
                $keyboard = [
                    [['text' => '🔙 لغو و بازگشت', 'callback_data' => "admin_cat_show_{$category->id}"]]
                ];

                // 🚀 اضافه کردن راهنمای کلمه "حذف" برای زمانی که ادمین می‌خواهد توضیحات را کلاً پاک کند
                $this->editMessageText("📝 <b>لطفاً توضیحات جدید را برای دسته‌بندی «{$category->name}» ارسال کنید:</b>\n\n<i>(برای پاک کردن توضیحات قبلی، کلمه <code>حذف</code> را بفرستید یا برای انصراف روی دکمه زیر کلیک کنید)</i>", [
                    'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
                ]);

                // نام state را روی admin_edit_category_desc_ تنظیم کردیم
                $this->setSession('state', "admin_edit_category_desc_{$category->id}");

                // 🚀 استخراج و ذخیره آیدی پیام
                $messageId = $this->update['callback_query']['message']['message_id'] ?? null;
                if ($messageId) {
                    $this->setSession('message_id', $messageId);
                }
                break;

            case 'editname': // 🚀 فرم دریافت نام جدید
                $keyboard = [
                    [['text' => '🔙 لغو و بازگشت', 'callback_data' => "admin_cat_show_{$category->id}"]]
                ];

                $this->editMessageText("✏️ <b>لطفاً نام جدید را برای دسته‌بندی «{$category->name}» ارسال کنید:</b>\n\n<i>(برای انصراف روی دکمه زیر کلیک کنید)</i>", [
                    'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
                ]);

                $this->setSession('state', "admin_edit_cat_name_{$category->id}");

                // 🚀 استخراج و ذخیره آیدی پیام برای ادیتِ بعدی
                $messageId = $this->update['callback_query']['message']['message_id'] ?? null;
                if ($messageId) {
                    $this->setSession('message_id', $messageId);
                }
                break;

            case 'bulkdiscount': // 🚀 فرم اعمال تخفیف گروهی روی کل دسته
                $keyboard = [
                    [['text' => '🔙 لغو و بازگشت', 'callback_data' => "admin_cat_show_{$category->id}"]]
                ];

                $text = "🎁 <b>تخفیف گروهی برای بخش «{$category->name}» (مرحله ۱ از ۲)</b>\n\n";
                $text .= "لطفاً <b>درصد تخفیف</b> مورد نظر را ارسال کنید (مثلاً: <code>20</code>)\n\n";
                $text .= "<i>⚠️ <b>چند نکته بسیار مهم:</b>\n";
                $text .= "▫️ <b>گستردگی:</b> تخفیف روی تمام محصولاتِ این دسته و زیردسته‌های آن اعمال خواهد شد.\n";
                $text .= "▫️ <b>آپدیت کانال:</b> ربات پیام‌های قدیمی را پاک کرده و با لیبل تخفیف بازنشر می‌کند.\n";
                $text .= "▫️ <b>زمان‌بندی:</b> این عملیات در  پس‌زمینه انجام می‌شود؛ لطفاً صبور باشید.\n\n";
                $text .= "(برای لغو تمام تخفیف‌های این بخش، عدد <code>0</code> را بفرستید)</i>";

                $this->editMessageText($text, [
                    'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
                ]);

                // 👈 نام وضعیت را به دریافت درصد تغییر دادیم
                $this->setSession('state', "admin_bulk_discount_percent_{$category->id}");

                $messageId = $this->update['callback_query']['message']['message_id'] ?? null;
                if ($messageId) {
                    $this->setSession('message_id', $messageId);
                }
                break;

            case 'moveprod':
                $productsCount = Product::where('category_id', $category->id)->count();

                if ($productsCount == 0) {
                    $this->sendRequest('answerCallbackQuery', [
                        'callback_query_id' => $this->update['callback_query']['id'],
                        'text' => "❌ این دسته در حال حاضر هیچ محصولی برای انتقال ندارد!",
                        'show_alert' => true
                    ]);
                    return;
                }

                $this->setSession('move_source_cat_id', $category->id);

                $text = "🔄 <b>انتقال محصولات از «{$category->name}»</b>\n\n";
                $text .= "تعداد <b>{$productsCount}</b> محصول در این دسته وجود دارد.\n";
                $text .= "👇 <i>لطفاً دسته‌بندی مقصد را انتخاب کنید:</i>\n\n";
                $text .= "<i>⚠️ دقت کنید دسته‌هایی که دارای زیردسته هستند (⛔️) نمی‌توانند محصول بپذیرند.</i>";

                $mainCategories = Category::where('bot_id', $this->bot->id)
                    ->whereNull('parent_id')
                    ->orderBy('sort_order', 'asc')
                    ->with([
                        'children' => function ($query) {
                            $query->orderBy('sort_order', 'asc');
                        }
                    ])
                    ->get();

                $keyboard = [];
                foreach ($mainCategories as $mainCard) {
                    $hasChildren = $mainCard->children->isNotEmpty();

                    if ($mainCard->id != $category->id) {
                        if ($hasChildren) {
                            // 💡 اگر زیردسته دارد، اکشن invalidtarget به آن می‌دهیم تا ارور بدهد
                            $keyboard[] = [
                                ['text' => "📁 " . $mainCard->name . " (غیرمجاز ⛔️)", 'callback_data' => "admin_cat_invalidtarget"]
                            ];
                        } else {
                            $keyboard[] = [
                                ['text' => "📁 " . $mainCard->name, 'callback_data' => "admin_cat_domove_{$mainCard->id}"]
                            ];
                        }
                    }

                    foreach ($mainCard->children as $subCard) {
                        if ($subCard->id != $category->id) {
                            // زیردسته‌ها قاعدتاً خودشان زیردسته ندارند و مجاز هستند
                            $keyboard[] = [
                                ['text' => " ├─ 🔹 " . $subCard->name, 'callback_data' => "admin_cat_domove_{$subCard->id}"]
                            ];
                        }
                    }
                }

                $keyboard[] = [
                    ['text' => '🔙 لغو و بازگشت', 'callback_data' => "admin_cat_show_{$category->id}"]
                ];

                $this->editMessageText($text, [
                    'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),

                ]);
                break;

            case 'domove':
                $targetCat = Category::find($id);

                // 🚨 بررسی نهایی: آیا دسته مقصد زیردسته دارد؟
                $targetHasSubcats = Category::where('parent_id', $targetCat->id)->exists();
                if ($targetHasSubcats) {
                    $this->sendRequest('answerCallbackQuery', [
                        'callback_query_id' => $this->update['callback_query']['id'],
                        'text' => "❌ خطای امنیتی: دسته مقصد دارای زیردسته است و نمی‌تواند محصول بپذیرد!",
                        'show_alert' => true
                    ]);
                    return;
                }

                $sourceId = $this->getSession('move_source_cat_id');

                if (!$sourceId) {
                    $this->sendRequest('answerCallbackQuery', [
                        'callback_query_id' => $this->update['callback_query']['id'],
                        'text' => "❌ زمان شما منقضی شده است. لطفاً مجدداً تلاش کنید.",
                        'show_alert' => true
                    ]);
                    return;
                }

                $updatedCount = Product::where('category_id', $sourceId)
                    ->update(['category_id' => $targetCat->id]);

                $this->setSession('move_source_cat_id', null);

                $this->sendRequest('answerCallbackQuery', [
                    'callback_query_id' => $this->update['callback_query']['id'],
                    'text' => "✅ تعداد {$updatedCount} محصول با موفقیت به دسته «{$targetCat->name}» منتقل شد.",
                    'show_alert' => true
                ]);

                $this->handleAdminCategoryAction('show', $sourceId);
                break;

        }
    }
    /**
     * نمایش لیست محصولاتِ یک دسته به صورت کاتالوگ دکمه‌ای
     */
    protected function showProductsList($categoryId, $page = 1)
    {
        $category = Category::find($categoryId);
        if (!$category) {
            $this->sendMessage("❌ این دسته‌بندی یافت نشد.");
            return;
        }

        $query = Product::where('category_id', $categoryId)
            ->where('bot_id', $this->bot->id)
            ->where('is_active', true);

        // سیستم صفحه‌بندی
        $perPage = 8;
        $totalProducts = $query->count();
        $totalPages = (int) ceil($totalProducts / $perPage);

        if ($page < 1)
            $page = 1;
        if ($page > $totalPages && $totalPages > 0)
            $page = $totalPages;

        $products = $query->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        // 📝 تولید متن پیام
        $text = "<b>{$category->name}</b>\n\n";

        if ($products->isEmpty()) {
            $text .= "📦 <i>در حال حاضر محصولی در این دسته‌بندی موجود نیست.</i>\n";
            $text .= "✨ لطفاً بعداً دوباره به این بخش سر بزنید.";
        } else {
            $text .= "🌟 <i>محصول مورد نظر خود را از لیست زیر انتخاب کنید.</i>\n";
            $text .= "🖼 با کلیک روی هر محصول، تصاویر، مشخصات و قیمت آن را مشاهده خواهید کرد.";
        }

        // 🔘 تولید دکمه‌های کیبورد
        $keyboard = [];

        foreach ($products as $product) {

            // محصول ناموجود
            if ($product->stock !== null && $product->stock <= 0) {

                $buttonText = "🚫 {$product->name} | ناموجود";

            } else {

                // قیمت قابل نمایش
                $displayPrice = $product->price;
                $discountLabel = "";

                if (
                    !empty($product->discount_price) &&
                    (
                        empty($product->discount_expires_at) ||
                        \Carbon\Carbon::parse($product->discount_expires_at)->isFuture()
                    )
                ) {
                    $displayPrice = $product->discount_price;
                    $discountLabel = " 🔥";
                } else {
                    $discountLabel = " 💰";
                }

                $priceFormatted = number_format($displayPrice) . " تومان";

                // موجودی کم
                $stockLabel = "";
                if ($product->stock !== null && $product->stock <= 10) {
                    $stockLabel = " ⚠️";
                }

                $buttonText = "{$product->name} |{$discountLabel} {$priceFormatted}{$stockLabel}";
            }

            $keyboard[] = [
                [
                    'text' => $buttonText,
                    'callback_data' => "show_prod_{$product->id}"
                ]
            ];
        }

        if ($totalPages > 1) {
            $pageControls = [];

            if ($page > 1) {
                $pageControls[] = ['text' => '«', 'callback_data' => "prod_page_{$category->id}_" . ($page - 1)];
            }

            $pageControls[] = ['text' => "📄 {$page} از {$totalPages}", 'callback_data' => "ignore_cb"];

            if ($page < $totalPages) {
                $pageControls[] = ['text' => '»', 'callback_data' => "prod_page_{$category->id}_" . ($page + 1)];
            }

            $keyboard[] = $pageControls;
        }

        $parentData = $category->parent_id ? "subcat_{$category->parent_id}" : "main_menu";
        $keyboard[] = [['text' => ' بازگشت به دسته‌بندی‌ها🔙', 'callback_data' => $parentData]];

        $message = $this->update['callback_query']['message'] ?? [];
        $hasMedia = isset($message['photo']) || isset($message['video']) || isset($message['document']) || isset($message['animation']);

        if ($hasMedia) {

            $this->deleteMessage($this->messageId);

            Http::post("https://api.telegram.org/bot{$this->bot->bot_token}/sendMessage", [
                'chat_id' => $this->chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
            ]);
        } else {
            $this->editMessageText($text, [
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),

            ]);
        }
    }

    /**
     * نمایش منوی اصلی مدیریت محصولات
     */
    public function showManageProductsMenu()
    {
        $text = "📦 <b>مدیریت محصولات</b>\n\n";
        $text .= "به بخش مدیریت محصولات خوش آمدید. از طریق گزینه‌های زیر می‌توانید محصولات فروشگاه را مدیریت کنید:\n\n";
        $text .= "🔻 <i>یک گزینه را انتخاب کنید:</i>";

        $keyboard = [
            [
                ['text' => '➕ افزودن محصول جدید', 'callback_data' => 'admin_prod_add']
            ],
            [
                ['text' => '📋 لیست و ویرایش محصولات', 'callback_data' => 'admin_prod_list']
            ],
            [
                // قابلیت جستجو با اینلاین کوئری که قبلاً نوشتید را مستقیماً اینجا هم می‌گذاریم
                ['text' => '🔍 جستجوی سریع محصول', 'switch_inline_query_current_chat' => '']
            ],
            [
                ['text' => '🔙 بازگشت به پنل مدیریت', 'callback_data' => 'admin_panel']
            ]
        ];

        $this->editMessageText($text, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),

        ]);
    }

    /**
     * نمایش لیست دسته‌بندی‌ها برای انتخاب محل ذخیره محصول جدید
     */
    protected function askProductCategoryToFinish($promptMessageId, $tempProduct)
    {
        $text = "🎯 <b>مرحله آخر: انتخاب دسته‌بندی</b>\n\n";
        $text .= "اطلاعات محصول کامل شد. لطفاً مشخص کنید این محصول در کدام دسته‌بندی قرار بگیرد:\n";
        $text .= "<i>⚠️ دقت کنید دسته‌هایی که دارای زیردسته هستند (⛔️) مجاز نیستند.</i>";

        // دریافت دسته‌های اصلی به همراه زیردسته‌ها
        $mainCategories = Category::where('bot_id', $this->bot->id)
            ->whereNull('parent_id')
            ->orderBy('sort_order', 'asc')
            ->with([
                'children' => function ($query) {
                    $query->orderBy('sort_order', 'asc');
                }
            ])
            ->get();

        $keyboard = [];
        foreach ($mainCategories as $mainCard) {
            $hasChildren = $mainCard->children->isNotEmpty();

            if ($hasChildren) {
                $keyboard[] = [
                    ['text' => "📁 " . $mainCard->name . " (غیرمجاز ⛔️)", 'callback_data' => "admin_cat_invalidtarget"]
                ];
            } else {
                $keyboard[] = [
                    ['text' => "📁 " . $mainCard->name, 'callback_data' => "admin_prod_save_cat_{$mainCard->id}"]
                ];
            }

            foreach ($mainCard->children as $subCard) {
                $keyboard[] = [
                    ['text' => " ├─ 🔹 " . $subCard->name, 'callback_data' => "admin_prod_save_cat_{$subCard->id}"]
                ];
            }
        }

        $keyboard[] = [
            ['text' => '🔙 لغو کل عملیات', 'callback_data' => 'admin_manage_products']
        ];

        $this->editMessageText($text, [
            'message_id' => $promptMessageId,
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),

        ]);
    }

    /**
     * ذخیره نهایی محصول در دیتابیس
     */
    protected function saveProductToDatabase($categoryId)
    {
        $tempProduct = $this->getSession('temp_product_data');

        if (!$tempProduct) {
            $this->sendRequest('answerCallbackQuery', [
                'callback_query_id' => $this->update['callback_query']['id'],
                'text' => "❌ خطا در دریافت اطلاعات. دوباره تلاش کنید.",
                'show_alert' => true
            ]);
            return;
        }

        // ثبت در دیتابیس با فیلد stock اضافه شده
        $product = \App\Models\Product::create([
            'bot_id' => $this->bot->id,
            'category_id' => $categoryId,
            'name' => $tempProduct['name'],
            'price' => $tempProduct['price'],
            'stock' => $tempProduct['stock'], // 👈 موجودی ثبت شد
            'description' => $tempProduct['description'],
            'is_active' => true,
        ]);

        // ارسال به صف برای پردازش تصاویر
        \App\Jobs\ProcessProductImagesJob::dispatch(
            $product->id,
            $tempProduct['photos'],
            $this->bot->bot_token
        );

        // پاکسازی سشن
        $this->setSession('state', null);
        $this->setSession('message_id', null);
        $this->setSession('temp_product_data', null);

        $this->sendRequest('answerCallbackQuery', [
            'callback_query_id' => $this->update['callback_query']['id'],
            'text' => "🎉 محصول با موفقیت ثبت شد!",
            'show_alert' => true
        ]);

        $this->showManageProductsMenu();
    }

    protected function showAdminProductList($categoryId, $page = 1)
    {
        $category = Category::where('bot_id', $this->bot->id)->find($categoryId);
        $query = Product::where('category_id', $categoryId)->where('bot_id', $this->bot->id);

        $perPage = 8;
        $total = $query->count();
        $totalPages = (int) ceil($total / $perPage);
        $page = max(1, min($page, $totalPages ?: 1));

        $products = $query->orderBy('created_at', 'desc')->skip(($page - 1) * $perPage)->take($perPage)->get();

        $text = "📦 <b>مدیریت محصولات - بخش «{$category->name}»</b>\n\n";
        $text .= "تعداد کل محصولات: <b>{$total}</b>\n\n";
        $text .= "👇 <i>برای مدیریت، روی محصول کلیک کنید:</i>";

        $keyboard = [];
        foreach ($products as $product) {
            // نمایش یک علامت برای محصولاتی که موجودی‌شان تمام شده
            $stockAlert = $product->stock <= 0 ? '⚠️ تمام شده' : $product->stock;
            $keyboard[] = [['text' => "🔹 {$product->name} (موجودی: {$stockAlert})", 'callback_data' => "admin_prod_manage_{$product->id}"]];
        }

        // صفحه‌بندی
        $nav = [];
        if ($page > 1)
            $nav[] = ['text' => '«', 'callback_data' => "admin_prod_list_{$categoryId}_" . ($page - 1)];

        $nav[] = ['text' => "{$page}/{$totalPages}", 'callback_data' => 'ignore_cb'];

        if ($page < $totalPages)
            $nav[] = ['text' => '»', 'callback_data' => "admin_prod_list_{$categoryId}_" . ($page + 1)];

        if (!empty($nav)) {
            $keyboard[] = $nav;
        }

        // 💡 تغییر کلیدی اینجاست: بازگشت به همان دسته‌بندی
        $keyboard[] = [['text' => '🔙 بازگشت به مدیریت دسته', 'callback_data' => "admin_cat_show_{$category->id}"]];

        $this->editMessageText($text, ['reply_markup' => json_encode(['inline_keyboard' => $keyboard]),]);
    }

    protected function handleAdminProductManage($productId)
    {
        // 💡 محصول را به همراه دسته‌بندی‌اش (برای نمایش نام دسته) لود می‌کنیم
        $product = Product::where('bot_id', $this->bot->id)->with('category')->find($productId);

        if (!$product) {
            $this->sendRequest('answerCallbackQuery', [
                'callback_query_id' => $this->update['callback_query']['id'],
                'text' => "❌ محصول یافت نشد",
                'show_alert' => true
            ]);
            return;
        }

        // ==========================================
        // 📝 آماده‌سازی مقادیر برای نمایش تمیز
        // ==========================================
        $price = number_format($product->price) . " تومان";
        $discountPrice = $product->discount_price ? number_format($product->discount_price) . " تومان" : "ندارد";
        $sku = $product->sku ?: "ثبت نشده";
        $stock = $product->stock <= 0 ? "⚠️ ناموجود (0)" : "{$product->stock} عدد";
        $status = $product->is_active ? '🟢 فعال' : '🔴 غیرفعال';
        $categoryName = $product->category ? $product->category->name : 'نامشخص';

        // یک تکه کوچک از توضیحات را نشان می‌دهیم (حداکثر 60 کاراکتر)
        $descSnippet = $product->description ? mb_substr(strip_tags($product->description), 0, 60) . '...' : 'ندارد';

        // ==========================================
        // 🎨 تولید متن پیام داشبورد محصول
        // ==========================================
        $text = "🎯 <b>داشبورد مدیریت محصول</b>\n\n";
        $text .= "🏷 <b>نام:</b> {$product->name}\n";
        $text .= "🔖 <b>کد کالا (SKU):</b> {$sku}\n";
        $text .= "📁 <b>دسته‌بندی:</b> {$categoryName}\n";
        $text .= "💰 <b>قیمت اصلی:</b> {$price}\n";
        $text .= "🎁 <b>قیمت با تخفیف:</b> {$discountPrice}\n";
        $text .= "📦 <b>موجودی:</b> {$stock}\n";
        $text .= "📝 <b>توضیحات:</b> {$descSnippet}\n";
        $text .= "📊 <b>وضعیت نمایش:</b> {$status}\n\n";
        $text .= "👇 <i>چه چیزی را می‌خواهید تغییر دهید؟</i>";

        // ==========================================
        // 🔘 تولید دکمه‌های کنترلی (با چیدمان شبکه‌ای و زیبا)
        // ==========================================

        // هندل کردن دکمه بازگشت به لیست (اگر محصول بدون دسته بود، به پنل اصلی برود)
        $backToListData = $product->category_id ? "admin_prod_list_{$product->category_id}_1" : 'admin_panel';

        $keyboard = [
            // ردیف ۱: مشخصات پایه
            [
                ['text' => '✏️ ویرایش نام', 'callback_data' => "admin_prod_editname_{$product->id}"],
                ['text' => '🔖 ویرایش کد (SKU)', 'callback_data' => "admin_prod_editsku_{$product->id}"]
            ],
            // ردیف ۲: مالی و قیمت‌گذاری
            [
                ['text' => '💰 قیمت اصلی', 'callback_data' => "admin_prod_editprice_{$product->id}"],
                ['text' => '🎁 قیمت تخفیف‌دار', 'callback_data' => "admin_prod_editdiscount_{$product->id}"]
            ],
            // ردیف ۳: انبار و جابجایی
            [
                ['text' => '📦 تغییر موجودی', 'callback_data' => "admin_prod_editstock_{$product->id}"],
                ['text' => '📁 تغییر دسته‌بندی', 'callback_data' => "admin_prod_editcat_{$product->id}"]
            ],
            // ردیف ۴: محتوا
            [
                ['text' => '📝 ویرایش توضیحات', 'callback_data' => "admin_prod_editdesc_{$product->id}"],
                ['text' => '🖼 مدیریت تصاویر', 'callback_data' => "admin_prod_editphoto_{$product->id}"]
            ],
            // ردیف ۵: عملیات حیاتی
            [
                ['text' => $product->is_active ? '🔴 غیرفعال کردن محصول' : '🟢 فعال کردن محصول', 'callback_data' => "admin_prod_toggle_{$product->id}"],
            ],
            [
                ['text' => '🗑 حذف کامل محصول', 'callback_data' => "admin_prod_del_{$product->id}"]
            ],
            // ردیف ۶: بازگشت‌های هوشمند
            [
                ['text' => '👁 مشاهده محصول', 'callback_data' => "show_prod_{$product->id}"]
            ],
            [
                ['text' => '🔙 بازگشت به لیست محصولات این دسته', 'callback_data' => $backToListData]
            ]
        ];

        // ==========================================
        // 🚀 مدیریت هوشمند رندر پیام (جلوگیری از خطای مدیا)
        // ==========================================
        $message = $this->update['callback_query']['message'] ?? [];
        $hasMedia = isset($message['photo']) || isset($message['video']) || isset($message['document']) || isset($message['animation']);

        if ($hasMedia) {
            // اگر ادمین از روی عکس محصول (ویترین) دکمه تنظیمات را زده است
            $this->deleteMessage($this->messageId);
            $this->sendMessage($text, [
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
                'parse_mode' => 'HTML' // 💡 حتماً پارس مد را اضافه کنید تا تگ‌های <b> کار کنند
            ]);
        } else {
            // اگر ادمین از روی یک لیست متنی وارد این صفحه شده است
            $this->editMessageText($text, [
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
                'parse_mode' => 'HTML'
            ]);
        }
    }

    /**
     * ℹ️ منوی اصلی درباره ما
     */
    protected function showAboutInfo()
    {
        $text = "🏢 <b>پلتفرم هوشمند ساخت و مدیریت فروشگاه</b>\n\n";
        $text .= "🚀 یک راهکار یکپارچه برای راه‌اندازی و مدیریت فروشگاه شما در تلگرام.\n\n";
        $text .= "با استفاده از این پلتفرم می‌توانید فروشگاه اختصاصی خود را راه‌اندازی کنید، محصولات و سفارش‌ها را مدیریت کنید، موجودی انبار را کنترل کنید و ارتباط بهتری با مشتریان خود داشته باشید.\n\n";
        $text .= "✨ هدف ما این است که راه‌اندازی فروشگاه آنلاین را برای شما ساده، سریع و حرفه‌ای کنیم؛ بدون پیچیدگی‌های فنی.\n\n";
        $text .= "🤖 <b>ربات اصلی پلتفرم:</b>\n";
        $text .= "برای راه‌اندازی فروشگاه جدید، مدیریت ربات‌ها و استفاده از امکانات پلتفرم از طریق ربات اصلی اقدام کنید:\n\n";
        $text .= "👉 @RoboShop_fatherbot\n\n";
        $text .= "💡 <i>برای آشنایی بیشتر با امکانات پلتفرم، یکی از بخش‌های زیر را انتخاب کنید:</i>";

        $keyboard = [
            [
                ['text' => '🚀 امکانات و قابلیت‌های پلتفرم', 'callback_data' => 'about_features']
            ],
            [
                ['text' => '👨‍💻 درباره تیم توسعه‌دهنده', 'callback_data' => 'about_developer'],
                ['text' => '📞 ارتباط با پشتیبانی', 'callback_data' => 'about_contact']
            ],
            [
                ['text' => '🌐 وب‌سایت رسمی ما', 'url' => 'https://roboshopapp.ir']
            ],
            [
                ['text' => '🔙 بازگشت به منوی اصلی', 'callback_data' => 'main_menu']
            ]
        ];

        // بررسی اینکه کاربر از طریق دکمه شیشه‌ای آمده یا با ارسال دستور متنی (مثل /about)
        if ($this->messageId) {
            $this->editMessageText($text, [
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
                'parse_mode' => 'HTML'
            ]);
        } else {
            $this->sendMessage($text, [
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
                'parse_mode' => 'HTML'
            ]);
        }
    }

    /**
     * 🚀 نمایش امکانات پلتفرم
     */
    protected function showAboutFeatures()
    {
        $text = "🚀 <b>امکانات بی‌نظیر پلتفرم روبوشاپ:</b>\n\n";
        $text .= "✅ <b>فروشگاه اختصاصی:</b> ربات با نام و برند شخصی شما\n";
        $text .= "✅ <b>مدیریت آسان:</b> پنل مدیریت تحت وب بسیار حرفه‌ای و زیبا\n";
        $text .= "✅ <b>پرداخت آنلاین:</b> اتصال مستقیم به درگاه‌های بانکی معتبر\n";
        $text .= "✅ <b>موجودی هوشمند:</b> کسر خودکار موجودی انبار بعد از هر خرید\n";
        $text .= "✅ <b>تخفیف‌های جذاب:</b> امکان تعریف کد تخفیف و جشنواره فروش\n";
        $text .= "✅ <b>دسته‌بندی نامحدود:</b> ساخت دسته‌بندی‌های تو در تو برای محصولات\n";
        $text .= "✅ <b>اطلاع‌رسانی در لحظه:</b> ارسال نوتیفیکیشن لحظه‌ای به مشتری و ادمین\n\n";
        $text .= "💡 <i>با روبوشاپ، شما فقط به فکر فروش باشید؛ کارهای فنی با ما!</i>";

        $keyboard = [
            [['text' => '🔙 بازگشت به منوی درباره ما', 'callback_data' => 'about_menu']]
        ];

        $this->editMessageText($text, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
            'parse_mode' => 'HTML'
        ]);
    }

    /**
     * 👨‍💻 معرفی تیم توسعه‌دهنده
     */
    protected function showAboutDeveloper()
    {
        $text = "👨‍💻 <b>درباره تیم توسعه‌دهنده</b>\n\n";
        $text .= "ما یک تیم متخصص از مهندسین نرم‌افزار هستیم که با هدف ساده‌سازی تجارت الکترونیک، پلتفرم قدرتمند <b>روبوشاپ</b> را خلق کرده‌ایم.\n\n";
        $text .= "🎯 <b>ماموریت ما:</b> توانمندسازی کسب‌وکارهای کوچک و متوسط برای ورود قدرتمند به دنیای فروش آنلاین، بدون نیاز به دانش برنامه‌نویسی یا پرداخت هزینه‌های نجومی.\n\n";
        $text .= "✨ تمامی زیرساخت‌های این پلتفرم با استفاده از به‌روزترین تکنولوژی‌های دنیا توسعه داده شده تا بالاترین امنیت، پایداری و سرعت را برای شما تضمین کند.";

        $keyboard = [
            [['text' => '🔙 بازگشت به منوی درباره ما', 'callback_data' => 'about_menu']]
        ];

        $this->editMessageText($text, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
            'parse_mode' => 'HTML'
        ]);
    }

    /**
     * 📞 اطلاعات تماس و پشتیبانی مرکزی
     */
    protected function showAboutContact()
    {
        $text = "📞 <b>ارتباط با پشتیبانی مرکزی پلتفرم</b>\n\n";
        $text .= "تیم پشتیبانی ما همیشه آماده پاسخگویی به سوالات، دریافت پیشنهادات و حل مشکلات شماست.\n\n";
        $text .= "💬 <b>ارتباط در تلگرام:</b> @Amir_soleimani_79\n";
        $text .= "🌐 <b>وب‌سایت رسمی:</b> roboshopapp.ir\n";
        $text .= "<i>(ساعات پاسخگویی: روزهای کاری از ۹ صبح تا ۱۸ عصر)</i>";

        $keyboard = [
            [['text' => '💬 ارسال پیام به پشتیبانی', 'url' => 'https://t.me/Amir_soleimani_79']], // می‌توانید آیدی ادمین پشتیبانی را اینجا بگذارید
            [['text' => '🔙 بازگشت به منوی درباره ما', 'callback_data' => 'about_menu']]
        ];

        $this->editMessageText($text, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
            'parse_mode' => 'HTML'
        ]);
    }

    /**
     * مدیریت کلیک روی دکمه‌های داشبورد محصول
     */
    public function handleAdminProductAction($action, $payloadId)
    {
        // 💡 تفکیک هوشمند آیدی‌ها (برای زمان‌هایی که اکشن savecat است و دو آیدی داریم)
        $productId = $payloadId;
        $secondaryId = null;

        // اگر اکشن مربوط به دسته یا عکس بود، آیدی دوم را هم بگیر
        if (in_array($action, ['savecat', 'delphoto', 'setmainphoto'])) {
            $parts = explode('_', $payloadId);
            $productId = $parts[0];
            $secondaryId = $parts[1] ?? null;
        }

        $product = \App\Models\Product::where('bot_id', $this->bot->id)->find($productId);

        if (!$product) {
            $this->sendRequest('answerCallbackQuery', ['callback_query_id' => $this->update['callback_query']['id'], 'text' => "❌ محصول یافت نشد", 'show_alert' => true]);
            return;
        }

        $promptMessageId = $this->update['callback_query']['message']['message_id'] ?? null;
        $markup = json_encode(['inline_keyboard' => [[['text' => '🔙 لغو و بازگشت', 'callback_data' => "admin_prod_manage_{$product->id}"]]]]);

        switch ($action) {
            case 'manage':
                $this->handleAdminProductManage($productId);
                break;
            case 'toggle':
                $product->is_active = !$product->is_active;
                $product->save();
                \App\Services\TelegramProductService::updateChannelPost($product);
                $this->handleAdminProductManage($productId);
                break;
            case 'del':
                $catId = $product->category_id;
                $product->delete();
                $this->sendRequest('answerCallbackQuery', [
                    'callback_query_id' => $this->update['callback_query']['id'],
                    'text' => "✅ محصول با موفقیت حذف شد.",
                    'show_alert' => false
                ]);
                $this->showAdminProductList($catId);
                break;
            case 'editname':
                $this->setSession('state', "admin_wait_prod_name_{$product->id}");
                if ($promptMessageId)
                    $this->setSession('message_id', $promptMessageId);
                $this->editMessageText("✏️ <b>ویرایش نام محصول</b>\n\nنام فعلی: {$product->name}\n\nلطفاً <b>نام جدید</b> را ارسال کنید:", ['reply_markup' => $markup,]);
                break;
            case 'editsku':
                $this->setSession('state', "admin_wait_prod_sku_{$product->id}");
                if ($promptMessageId)
                    $this->setSession('message_id', $promptMessageId);
                $this->editMessageText("🔖 <b>ویرایش کد کالا (SKU)</b>\n\nکد فعلی: " . ($product->sku ?? 'ندارد') . "\n\nلطفاً <b>کد جدید</b> را ارسال کنید.\n<i>(برای پاک کردن کد، کلمه <code>حذف</code> را بفرستید)</i>", ['reply_markup' => $markup,]);
                break;
            case 'editprice':
                $this->setSession('state', "admin_wait_prod_price_{$product->id}");
                if ($promptMessageId)
                    $this->setSession('message_id', $promptMessageId);
                $this->editMessageText("💰 <b>ویرایش قیمت اصلی</b>\n\nقیمت فعلی: " . number_format($product->price) . " تومان\n\nلطفاً <b>قیمت جدید</b> را به عدد وارد کنید:", ['reply_markup' => $markup,]);
                break;
            case 'editdiscount':
                $this->setSession('state', "admin_wait_prod_discountpercent_{$product->id}");
                if ($promptMessageId)
                    $this->setSession('message_id', $promptMessageId);

                $currentDiscount = $product->discount_price ? number_format($product->discount_price) . ' تومان' : 'ندارد';
                $text = "🎁 <b>ویرایش تخفیف محصول</b>\n\nقیمت اصلی: " . number_format($product->price) . " تومان\nتخفیف فعلی: {$currentDiscount}\n\nلطفاً <b>درصد تخفیف</b> را به عدد وارد کنید (مثلاً 20):\n<i>(برای پاک کردن تخفیف قبلی، عدد <code>0</code> را بفرستید)</i>";
                $this->editMessageText($text, ['reply_markup' => $markup,]);
                break;
            case 'editstock':
                $this->setSession('state', "admin_wait_prod_stock_{$product->id}");
                if ($promptMessageId)
                    $this->setSession('message_id', $promptMessageId);
                $this->editMessageText("📦 <b>ویرایش موجودی انبار</b>\n\nموجودی فعلی: {$product->stock} عدد\n\nلطفاً <b>موجودی جدید</b> را به عدد وارد کنید:", ['reply_markup' => $markup,]);
                break;
            case 'editdesc':
                $this->setSession('state', "admin_wait_prod_desc_{$product->id}");
                if ($promptMessageId)
                    $this->setSession('message_id', $promptMessageId);
                $this->editMessageText("📝 <b>ویرایش توضیحات</b>\n\nلطفاً <b>توضیحات کامل</b> محصول را ارسال کنید.\n<i>(برای پاک کردن توضیحات، کلمه <code>حذف</code> را بفرستید)</i>", ['reply_markup' => $markup,]);
                break;

            // ===============================================
            // 🚀 دکمه‌های مربوط به تغییر دسته‌بندی
            // ===============================================
            case 'editcat':
                $text = "📁 <b>تغییر دسته‌بندی محصول</b>\n\n";
                $text .= "محصول: <b>{$product->name}</b>\n";
                $text .= "دسته‌بندی فعلی: " . ($product->category ? "«{$product->category->name}»" : 'ندارد') . "\n\n";
                $text .= "👇 <i>لطفاً دسته‌بندی جدید را از لیست زیر انتخاب کنید:</i>\n";
                $text .= "<i>⚠️ دقت کنید دسته‌هایی که دارای زیردسته هستند (⛔️) مجاز نیستند.</i>";

                // 🚀 گرفتن کل دسته‌بندی‌های این ربات به صورت یکجا (برای جلوگیری از فشار به دیتابیس)
                $allCategories = \App\Models\Category::where('bot_id', $this->bot->id)
                    ->orderBy('sort_order', 'asc')
                    ->get();

                $keyboard = [];

                // 💡 استفاده از تابع بازگشتی (Recursive) برای پیمایش درخت N-سطحی
                $buildTree = function ($parentId, $level) use (&$buildTree, $allCategories, $product, &$keyboard) {

                    // پیدا کردن فرزندان این سطح
                    $children = $allCategories->where('parent_id', $parentId);

                    foreach ($children as $cat) {
                        // بررسی اینکه آیا این دسته خودش زیردسته‌ای دارد؟
                        $hasChildren = $allCategories->where('parent_id', $cat->id)->isNotEmpty();

                        // 🎨 ایجاد پیشوند توهم‌رفتگی (Indentation) برای تلگرام
                        // برای سطح 0 خالی است، برای سطح 1 یک فاصله، سطح 2 دو فاصله و ...
                        $prefix = $level == 0 ? "" : str_repeat("   ", $level) . "├─ ";

                        if ($hasChildren) {
                            // دسته‌ای که زیردسته دارد (غیرمجاز برای انتخاب محصول)
                            $keyboard[] = [['text' => $prefix . "📁 " . $cat->name . " (غیرمجاز ⛔️)", 'callback_data' => "admin_cat_invalidtarget"]];

                            // 🔄 فراخوانی مجدد همین تابع برای گرفتن زیردسته‌های این شاخه (رفتن به عمق بیشتر)
                            $buildTree($cat->id, $level + 1);
                        } else {
                            // دسته‌های نهایی یا برگ (مجاز برای انتخاب)
                            $icon = ($product->category_id == $cat->id) ? '✅ ' : ($level == 0 ? '📁 ' : '🔹 ');
                            $keyboard[] = [['text' => $prefix . $icon . $cat->name, 'callback_data' => "admin_prod_savecat_{$product->id}_{$cat->id}"]];
                        }
                    }
                };

                // 🏁 استارت تابع بازگشتی از دسته‌های اصلی (والد = null) در سطح صفر
                $buildTree(null, 0);

                $keyboard[] = [['text' => '🔙 لغو و بازگشت', 'callback_data' => "admin_prod_manage_{$product->id}"]];

                $this->editMessageText($text, [
                    'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),

                ]);
                break;

            case 'savecat':
                // 💡 اصلاح شد: اضافه شدن \App\Models\ قبل از نام کلاس
                $targetCat = \App\Models\Category::find($secondaryId);

                if (!$targetCat) {
                    return;
                }

                // 🚨 بررسی امنیتی: مبادا دسته مقصد زیردسته داشته باشد
                $targetHasSubcats = \App\Models\Category::where('parent_id', $targetCat->id)->exists();
                if ($targetHasSubcats) {
                    $this->sendRequest('answerCallbackQuery', [
                        'callback_query_id' => $this->update['callback_query']['id'],
                        'text' => "❌ خطای امنیتی: دسته مقصد دارای زیردسته است!",
                        'show_alert' => true
                    ]);
                    return;
                }

                // ثبت دسته جدید در دیتابیس
                $product->category_id = $targetCat->id;
                $product->save();

                // 📢 آپدیت نام دسته در پیام کانال (در صورت نیاز به نمایش دسته)
                \App\Services\TelegramProductService::updateChannelPost($product);

                $this->sendRequest('answerCallbackQuery', [
                    'callback_query_id' => $this->update['callback_query']['id'],
                    'text' => "✅ محصول با موفقیت به دسته «{$targetCat->name}» منتقل شد.",
                    'show_alert' => false
                ]);

                // بازگشت به داشبورد محصول
                $this->handleAdminProductManage($productId);
                break;

            // ===============================================
            // 🖼 دکمه‌های مربوط به مدیریت تصاویر
            // ===============================================
            case 'editphoto':
                if ($promptMessageId) {
                    $this->setSession('dashboard_msg_id', $promptMessageId);
                }

                // ⏳ تغییر موقت پیام داشبورد
                $this->editMessageText("⏳ <b>در حال دریافت و آماده‌سازی تصاویر...</b>", [
                    'reply_markup' => json_encode(['inline_keyboard' => []]),

                ]);

                // رندر کردن گالری (متد کمکی که در پایین اضافه می‌کنیم)
                $this->renderProductImages($product);
                break;

            case 'delphoto':
                $img = \App\Models\ProductImage::find($secondaryId);
                if ($img) {
                    $img->delete();
                    if ($promptMessageId) {
                        $this->deleteMessage($promptMessageId); // درجا پیامِ همون عکس پاک میشه
                    }
                    $this->sendRequest('answerCallbackQuery', ['callback_query_id' => $this->update['callback_query']['id'], 'text' => "✅ عکس حذف شد"]);
                }
                break;

            case 'setmainphoto':
                $img = \App\Models\ProductImage::find($secondaryId);
                if ($img) {
                    // 1️⃣ ابتدا بقیه عکس‌های این محصول را می‌گیریم (به جز عکسی که قرار است کاور شود)
                    $otherImgs = \App\Models\ProductImage::where('product_id', $product->id)
                        ->where('id', '!=', $img->id)
                        ->orderBy('sort_order', 'asc')
                        ->get();

                    // 2️⃣ عکس انتخاب شده را به عنوان اولین عکس (ایندکس 0) ذخیره می‌کنیم
                    $img->sort_order = 0;
                    $img->save();

                    // 3️⃣ حالا بقیه عکس‌ها را از ایندکس 1 به بعد مرتب می‌کنیم
                    foreach ($otherImgs as $idx => $ai) {
                        $ai->sort_order = $idx + 1; // 1, 2, 3, ...
                        $ai->save();
                    }

                    $this->sendRequest('answerCallbackQuery', [
                        'callback_query_id' => $this->update['callback_query']['id'],
                        'text' => "🌟 عکس اصلی تغییر کرد"
                    ]);

                    // 🔄 رفرش کردن گالری برای نمایش تغییرات
                    $tempMsgs = $this->getSession('temp_photo_msgs') ?? [];
                    foreach ($tempMsgs as $msgId) {
                        $this->deleteMessage($msgId);
                    }
                    $this->renderProductImages($product);
                }
                break;

            case 'donephoto':
                // 🧹 پاک کردن تمام پیام‌های تصاویر و پیام راهنما
                $tempMsgs = $this->getSession('temp_photo_msgs') ?? [];
                foreach ($tempMsgs as $msgId) {
                    $this->deleteMessage($msgId);
                }

                $this->setSession('temp_photo_msgs', null);
                $this->setSession('state', null);

                // 🔙 بازیابی پیام داشبورد
                $dashMsgId = $this->getSession('dashboard_msg_id');
                if ($dashMsgId) {
                    $this->messageId = $dashMsgId;
                    $this->update['callback_query'] = ['message' => ['message_id' => $dashMsgId]];
                }

                \App\Services\TelegramProductService::updateChannelPost($product);
                $this->handleAdminProductManage($productId);
                break;
        }
    }


    /**
     * رندر کردن تمام تصاویر محصول و ارسال گروهی آن‌ها
     */
    protected function renderProductImages($product)
    {
        $images = \App\Models\ProductImage::where('product_id', $product->id)->orderBy('sort_order', 'asc')->get();
        $tempMsgs = [];

        foreach ($images as $index => $img) {
            $caption = "🖼 <b>تصویر " . ($index + 1) . "</b>\n\n";
            if ($index == 0) {
                $caption .= "🌟 <b>(عکس اصلی / کاور)</b>";
            }

            $kbd = [];
            // دکمه تعیین عکس اصلی (فقط برای عکس‌هایی که اول نیستند نشان داده می‌شود)
            if ($index > 0) {
                $kbd[] = [['text' => '🌟 انتخاب به عنوان عکس اصلی', 'callback_data' => "admin_prod_setmainphoto_{$product->id}_{$img->id}"]];
            }
            $kbd[] = [['text' => '🗑 حذف این تصویر', 'callback_data' => "admin_prod_delphoto_{$product->id}_{$img->id}"]];

            $response = null;

            // ارسال عکس
            if (!empty($img->telegram_file_id)) {
                $response = http::post("https://api.telegram.org/bot{$this->bot->bot_token}/sendPhoto", [
                    'chat_id' => $this->chatId,
                    'photo' => $img->telegram_file_id,
                    'caption' => $caption,
                    'reply_markup' => json_encode(['inline_keyboard' => $kbd]),

                ]);
            } else {
                $absolutePath = storage_path('app/public/' . $img->file_id);
                if (file_exists($absolutePath)) {
                    $response = http::attach('photo', file_get_contents($absolutePath), 'product.webp')
                        ->post("https://api.telegram.org/bot{$this->bot->bot_token}/sendPhoto", [
                            'chat_id' => $this->chatId,
                            'caption' => $caption,
                            'reply_markup' => json_encode(['inline_keyboard' => $kbd]),

                        ]);
                }
            }

            if ($response && $response->successful()) {
                $tempMsgs[] = $response->json('result')['message_id'];
            }
        }

        // 💬 ارسال پیام راهنمای افزودن عکس و دکمه اتمام
        $finalRes = http::post("https://api.telegram.org/bot{$this->bot->bot_token}/sendMessage", [
            'chat_id' => $this->chatId,
            'text' => "➕ <b>افزودن عکس جدید:</b>\nبرای اضافه کردن عکس، همین الان تصویر خود را (یکی یکی یا به صورت آلبوم) ارسال کنید.\n\n👇 <i>پس از پایان مدیریت تصاویر، برای بازگشت به داشبورد روی دکمه زیر کلیک کنید:</i>",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => '🔙 اتمام و بازگشت', 'callback_data' => "admin_prod_donephoto_{$product->id}"]]
                ]
            ]),

        ]);

        if ($finalRes && $finalRes->successful()) {
            $tempMsgs[] = $finalRes->json('result')['message_id'];
        }

        // ذخیره آیدی پیام‌ها در سشن (تا موقع خروج پاک بشن)
        $this->setSession('temp_photo_msgs', $tempMsgs);

        // تنظیم State برای دریافت عکس‌های جدید
        $this->setSession('state', "admin_wait_prod_addphoto_{$product->id}");
    }


    protected function showQuantitySelector($productId, $qty = 1, $variantId = null)
    {
        $product = \App\Models\Product::find($productId);
        if (!$product) {
            $this->answerCallback($this->update['callback_query']['id'], "❌ محصول یافت نشد.");
            return;
        }

        $maxStock = $product->stock;
        $variantName = "";

        if ($variantId) {
            $variant = $product->variants()->find($variantId);
            if ($variant) {
                $maxStock = $variant->stock;
                $variantName = " (مدل: {$variant->variant_name})";
            }
        }

        if ($qty < 1)
            $qty = 1;
        if ($maxStock !== null && $qty > $maxStock) {
            $qty = $maxStock;
            $this->sendRequest('answerCallbackQuery', [
                'callback_query_id' => $this->update['callback_query']['id'],
                'text' => "⚠️ حداکثر موجودی این محصول {$maxStock} عدد است!",
                'show_alert' => false
            ]);
        }

        // 🧮 محاسبه قیمت هوشمند (با بررسی صفر بودن و انقضای تاریخ)
        $basePrice = $product->price;
        $isProductDiscountValid = $product->discount_price > 0 && (empty($product->discount_expires_at) || \Carbon\Carbon::parse($product->discount_expires_at)->isFuture());
        $baseDiscount = $isProductDiscountValid ? $product->discount_price : 0;

        if ($variantId) {
            $variant = $product->variants()->find($variantId);
            if ($variant) {
                $basePrice = ($variant->price > 0) ? $variant->price : $basePrice;
                $varExpires = $variant->discount_expires_at ?? $product->discount_expires_at;
                $isVariantDiscountValid = $variant->discount_price > 0 && (empty($varExpires) || \Carbon\Carbon::parse($varExpires)->isFuture());
                $baseDiscount = $isVariantDiscountValid ? $variant->discount_price : $baseDiscount;
            }
        }

        $unitPrice = ($baseDiscount > 0) ? $baseDiscount : $basePrice;
        $totalPrice = number_format($unitPrice * $qty);

        // 🎨 ساخت دکمه‌های شمارنده
        $keyboard = [];
        $varSuffix = $variantId ? "_{$variantId}" : "";

        $keyboard[] = [
            ['text' => '➕', 'callback_data' => "cart_qty_{$productId}_" . ($qty + 1) . $varSuffix],
            ['text' => "🛒 {$qty} عدد", 'callback_data' => 'ignore_cb'],
            ['text' => '➖', 'callback_data' => "cart_qty_{$productId}_" . ($qty - 1) . $varSuffix]
        ];

        $keyboard[] = [
            ['text' => "✅ ثبت در سبد (مبلغ: {$totalPrice} T)", 'callback_data' => "cart_commit_{$productId}_{$qty}{$varSuffix}"]
        ];

        $keyboard[] = [
            ['text' => '🔙 انصراف', 'callback_data' => "show_prod_{$productId}"]
        ];

        $this->sendRequest('editMessageReplyMarkup', [
            'chat_id' => $this->chatId,
            'message_id' => $this->messageId,
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * ذخیره و آپدیت تعداد هر مدل در کش (حافظه موقت) کاربر
     */
    protected function updateTempVariantCart($productId, $variantId, $newQty)
    {
        $cacheKey = "temp_cart_var_{$this->chatId}_{$productId}";
        $selections = \Illuminate\Support\Facades\Cache::get($cacheKey, []);

        $variant = \App\Models\ProductVariant::find($variantId);
        if (!$variant)
            return;

        if ($newQty < 0)
            $newQty = 0;

        // 🛑 محافظت از موجودی
        if ($variant->stock !== null && $newQty > $variant->stock) {
            $newQty = $variant->stock;
            $this->sendRequest('answerCallbackQuery', [
                'callback_query_id' => $this->update['callback_query']['id'],
                'text' => "⚠️ حداکثر موجودی مدل «{$variant->variant_name}» {$variant->stock} عدد است!",
                'show_alert' => false
            ]);
        }

        if ($newQty == 0) {
            unset($selections[$variantId]);
        } else {
            $selections[$variantId] = $newQty;
        }

        // ذخیره تغییرات در کش به مدت ۲ ساعت
        \Illuminate\Support\Facades\Cache::put($cacheKey, $selections, now()->addHours(2));

        // رفرش کردن پیام
        $this->showUnifiedVariantSelector($productId);
    }

    protected function showUnifiedVariantSelector($productId)
    {
        $product = \App\Models\Product::with('variants')->find($productId);
        if (!$product)
            return;

        $cacheKey = "temp_cart_var_{$this->chatId}_{$productId}";
        $selections = \Illuminate\Support\Facades\Cache::get($cacheKey, []);

        $totalPayable = 0;
        $totalOriginal = 0;
        $totalQty = 0;

        $text = "🎯 <b>مدل‌های محصول: {$product->name}</b>\n\n";

        $basePrice = $product->price;
        $isProductDiscountValid = $product->discount_price > 0 && (empty($product->discount_expires_at) || \Carbon\Carbon::parse($product->discount_expires_at)->isFuture());
        $baseDiscount = $isProductDiscountValid ? $product->discount_price : 0;

        if (empty($selections)) {
            $text .= "<i>👇 هنوز مدلی انتخاب نکرده‌اید. با دکمه‌های زیر تعداد مورد نیاز از هر مدل را مشخص کنید:</i>\n\n";
        } else {
            $text .= "🛒 <b>سبد خرید موقت شما:</b>\n";

            foreach ($selections as $varId => $qty) {
                $variant = $product->variants->where('id', $varId)->first();
                if (!$variant)
                    continue;

                // 🧮 محاسبه هوشمند قیمت مدل (با بررسی انقضا)
                $varPrice = ($variant->price > 0) ? $variant->price : $basePrice;
                $varExpires = $variant->discount_expires_at ?? $product->discount_expires_at;
                $isVariantDiscountValid = $variant->discount_price > 0 && (empty($varExpires) || \Carbon\Carbon::parse($varExpires)->isFuture());
                $discount = $isVariantDiscountValid ? $variant->discount_price : $baseDiscount;

                $finalPrice = ($discount > 0) ? $discount : $varPrice;

                $totalPayable += ($finalPrice * $qty);
                $totalOriginal += ($varPrice * $qty);
                $totalQty += $qty;

                $text .= "▫️ {$variant->variant_name}: <b>{$qty} عدد</b> (مبلغ: " . number_format($finalPrice * $qty) . " T)\n";
            }
            $text .= "\n";

            $text .= "📦 تعداد کل اقلام: <b>{$totalQty} عدد</b>\n";

            if ($totalOriginal > $totalPayable) {
                $profit = $totalOriginal - $totalPayable;
                $text .= "💰 قیمت بدون تخفیف: <s>" . number_format($totalOriginal) . "</s> T\n";
                $text .= "🎁 سود شما از خرید: <b>" . number_format($profit) . " T</b>\n";
            }
            $text .= "💳 <b>مبلغ قابل پرداخت: " . number_format($totalPayable) . " تومان</b>\n\n";
        }

        // 🔘 تولید دکمه‌های شیشه‌ای
        $keyboard = [];
        foreach ($product->variants->where('is_active', true) as $variant) {
            $qty = $selections[$variant->id] ?? 0;

            // 🧮 اعمال همان محاسبه هوشمند روی دکمه‌ها
            $varPrice = ($variant->price > 0) ? $variant->price : $basePrice;
            $varExpires = $variant->discount_expires_at ?? $product->discount_expires_at;
            $isVariantDiscountValid = $variant->discount_price > 0 && (empty($varExpires) || \Carbon\Carbon::parse($varExpires)->isFuture());
            $discount = $isVariantDiscountValid ? $variant->discount_price : $baseDiscount;

            $finalPrice = ($discount > 0) ? $discount : $varPrice;

            $stockIcon = ($variant->stock !== null && $variant->stock <= 0) ? "❌ " : "🔖 ";
            $priceText = number_format($finalPrice) . " تومان";

            if ($variant->stock !== null && $variant->stock <= 0) {
                // 🚀 جادوی Bot API 10.3: غیرفعال کردن دکمه مدل ناموجود
                $keyboard[] = [
                    [
                        'text' => "{$stockIcon} {$variant->variant_name} - تمام شد",
                        'callback_data' => 'ignore_cb',
                        'disabled' => true // 👈 این خط اضافه شد تا دکمه کاملاً خاموش شود
                    ]
                ];
            } else {
                $keyboard[] = [['text' => "{$stockIcon} {$variant->variant_name} ◂ {$priceText}", 'callback_data' => 'ignore_cb']];
                $keyboard[] = [
                    ['text' => '➕', 'callback_data' => "cart_qty_{$productId}_" . ($qty + 1) . "_{$variant->id}"],
                    ['text' => $qty > 0 ? "🛒 {$qty} عدد" : "انتخاب", 'callback_data' => 'ignore_cb'],
                    ['text' => $qty > 0 ? '➖' : '▪️', 'callback_data' => $qty > 0 ? "cart_qty_{$productId}_" . ($qty - 1) . "_{$variant->id}" : 'ignore_cb']
                ];
            }
        }

        if ($totalQty > 0) {
            $keyboard[] = [['text' => "✅ تایید و افزودن به سبد اصلی ({$totalQty} کالا)", 'callback_data' => "cart_commit_vars_{$productId}"]];
        }

        $keyboard[] = [['text' => '🔙 بازگشت به محصول', 'callback_data' => "show_prod_{$productId}"]];

        $this->sendRequest('editMessageCaption', [
            'chat_id' => $this->chatId,
            'message_id' => $this->messageId,
            'caption' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),

        ]);
    }
    /**
     * ثبت نهایی محصول ساده در دیتابیس سبد خرید
     */
    protected function commitToCart($productId, $qty, $variantId = null)
    {
        $qty = (int) $qty;

        if ($qty > 0) {
            // 💡 استفاده از firstOrNew برای پیدا کردن رکورد قبلی یا ساخت رکورد جدید
            $cartItem = \App\Models\Cart::firstOrNew([
                'bot_id' => $this->bot->id,
                'user_id' => $this->user->id,
                'product_id' => $productId,
                'variant_id' => $variantId
            ]);

            // اگر قبلا تو سبد بوده، تعداد جدید به قبلی اضافه میشه
            $cartItem->quantity = $cartItem->exists ? ($cartItem->quantity + $qty) : $qty;
            $cartItem->save();
        }

        $this->sendRequest('answerCallbackQuery', [
            'callback_query_id' => $this->update['callback_query']['id'],
            'text' => "✅ به سبد خرید اضافه شد.",
            'show_alert' => false
        ]);

        $this->showSuccessCartMessage($productId);
    }

    /**
     * ثبت نهایی محصولات دارای مدل (انتقال از کش به دیتابیس)
     */
    protected function commitVariantsToCart($productId)
    {
        $cacheKey = "temp_cart_var_{$this->chatId}_{$productId}";
        $selections = \Illuminate\Support\Facades\Cache::get($cacheKey, []);

        if (empty($selections)) {
            $this->sendRequest('answerCallbackQuery', [
                'callback_query_id' => $this->update['callback_query']['id'],
                'text' => "❌ شما هیچ مدلی انتخاب نکرده‌اید!",
                'show_alert' => true
            ]);
            return;
        }

        foreach ($selections as $varId => $qty) {
            if ($qty > 0) {
                $cartItem = \App\Models\Cart::firstOrNew([
                    'bot_id' => $this->bot->id,
                    'user_id' => $this->user->id,
                    'product_id' => $productId,
                    'variant_id' => $varId
                ]);

                $cartItem->quantity = $cartItem->exists ? ($cartItem->quantity + $qty) : $qty;
                $cartItem->save();
            }
        }

        // 🧹 پاک کردن حافظه موقت (کش) پس از ثبت موفق در دیتابیس
        \Illuminate\Support\Facades\Cache::forget($cacheKey);

        $this->sendRequest('answerCallbackQuery', [
            'callback_query_id' => $this->update['callback_query']['id'],
            'text' => "✅ محصولات با موفقیت به سبد خرید اضافه شدند.",
            'show_alert' => false
        ]);

        $this->showSuccessCartMessage($productId);
    }

    /**
     * 🎨 نمایش پیام موفقیت‌آمیز بودن ثبت و هدایت کاربر به مراحل بعدی
     */
    protected function showSuccessCartMessage($productId)
    {
        $text = "🎉 <b>عملیات موفقیت‌آمیز بود!</b>\n\n";
        $text .= "محصولات مورد نظر شما با موفقیت در 🛒 سبد خرید ذخیره شدند.\n\n";
        $text .= "<i>👇 می‌توانید برای پرداخت و نهایی کردن سفارش به سبد خرید بروید، یا به گشت‌و‌گذار در فروشگاه ادامه دهید.</i>";

        $keyboard = [
            [['text' => '💳 مشاهده سبد خرید و پرداخت', 'callback_data' => 'view_cart']],
            [['text' => '🔙 بازگشت به صفحه همین محصول', 'callback_data' => "show_prod_{$productId}"]],
            [['text' => '🏠 بازگشت به ویترین فروشگاه', 'callback_data' => 'main_menu']]
        ];

        // تشخیص اینکه پیام قبلی عکس‌دار بوده (کپشن) یا متنی ساده
        if (isset($this->update['callback_query']['message']['photo'])) {
            $this->sendRequest('editMessageCaption', [
                'chat_id' => $this->chatId,
                'message_id' => $this->messageId,
                'caption' => $text,
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),

            ]);
        } else {
            $this->editMessageText($text, [
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),

            ]);
        }
    }

    /**
     * 🛒 تولید و رندر فاکتور شیک سبد خرید
     */
    // protected function showCart()
    // {
    //     $carts = \App\Models\Cart::where('bot_id', $this->bot->id)
    //         ->where('user_id', $this->user->id)
    //         ->with(['product', 'variant'])
    //         ->get();

    //     // 🛑 اگر سبد خالی بود
    //     if ($carts->isEmpty()) {
    //         $text = "🛒 <b>سبد خرید شما کاملاً خالی است!</b>\n\n";
    //         $text .= "<i>👇 برای شروع یک خرید هیجان‌انگیز، به ویترین فروشگاه سر بزنید:</i>";
    //         $keyboard = [[['text' => '🛍 مشاهده ویترین محصولات', 'callback_data' => 'main_menu']]];

    //         if (isset($this->update['callback_query']['message']['photo'])) {
    //             $this->deleteMessage($this->messageId);
    //             $this->sendMessage($text, ['reply_markup' => json_encode(['inline_keyboard' => $keyboard]),]);
    //         } else {
    //             $this->editMessageText($text, ['reply_markup' => json_encode(['inline_keyboard' => $keyboard]),]);
    //         }
    //         return;
    //     }

    //     $text = "🛒 <b>فاکتور خرید شما</b>\n";
    //     $text .= "➖➖➖➖➖➖➖➖➖➖\u{200f}\n";

    //     $totalOriginal = 0;
    //     $totalPayable = 0;
    //     $totalQty = 0;
    //     $keyboard = [];
    //     $counter = 1;

    //     foreach ($carts as $item) {
    //         if (!$item->product) {
    //             $item->delete();
    //             continue;
    //         }

    //         $productName = $item->product->name;
    //         $variantName = $item->variant ? " (مدل: {$item->variant->variant_name})" : "";

    //         // 🧮 محاسبه هوشمند با بررسی انقضای تاریخ
    //         $basePrice = $item->product->price;
    //         $isProductDiscountValid = $item->product->discount_price > 0 && (empty($item->product->discount_expires_at) || \Carbon\Carbon::parse($item->product->discount_expires_at)->isFuture());
    //         $discount = $isProductDiscountValid ? $item->product->discount_price : 0;

    //         if ($item->variant) {
    //             $basePrice = ($item->variant->price > 0) ? $item->variant->price : $basePrice;
    //             $varExpires = $item->variant->discount_expires_at ?? $item->product->discount_expires_at;
    //             $isVariantDiscountValid = $item->variant->discount_price > 0 && (empty($varExpires) || \Carbon\Carbon::parse($varExpires)->isFuture());
    //             $discount = $isVariantDiscountValid ? $item->variant->discount_price : $discount;
    //         }

    //         $finalPrice = ($discount > 0) ? $discount : $basePrice;

    //         $rowOriginal = $basePrice * $item->quantity;
    //         $rowPayable = $finalPrice * $item->quantity;

    //         $totalOriginal += $rowOriginal;
    //         $totalPayable += $rowPayable;
    //         $totalQty += $item->quantity;

    //         $text .= "{$counter}️⃣ <b>{$productName}{$variantName}</b>\n";
    //         $text .= "▫️ فی: " . number_format($finalPrice) . " T\n";
    //         $text .= "▫️ تعداد: {$item->quantity} عدد\n";
    //         $text .= "▫️ جمع: <b>" . number_format($rowPayable) . " T</b>\n";
    //         $text .= "┈┈┈┈┈┈┈┈┈┈┈┈┈┈\u{200f}\n";

    //         $keyboard[] = [
    //             ['text' => "👁 مشاهده کالا", 'callback_data' => "show_prod_{$item->product_id}"]
    //         ];
    //         $keyboard[] = [
    //             ['text' => '➕', 'callback_data' => "ucart_qty_{$item->id}_" . ($item->quantity + 1)],
    //             ['text' => "{$item->quantity} عدد", 'callback_data' => "ignore_cb"],
    //             ['text' => '➖', 'callback_data' => "ucart_qty_{$item->id}_" . ($item->quantity - 1)],
    //             ['text' => '🗑 حذف', 'callback_data' => "ucart_del_{$item->id}"]
    //         ];

    //         $counter++;
    //     }

    //     $text .= "\n📦 <b>تعداد اقلام:</b> {$totalQty} عدد\n";

    //     if ($totalOriginal > $totalPayable) {
    //         $profit = $totalOriginal - $totalPayable;
    //         $text .= "💰 جمع بدون تخفیف: <s>" . number_format($totalOriginal) . "</s> T\n";
    //         $text .= "🎁 <b>سود شما از این خرید:</b> " . number_format($profit) . " T\n";
    //     }
    //     $text .= "💳 <b>مبلغ قابل پرداخت: " . number_format($totalPayable) . " تومان</b>\n\n";

    //     $selectedAddressId = $this->getSession('checkout_address_id');
    //     $selectedAddress = $selectedAddressId ? \App\Models\UserAddress::find($selectedAddressId) : null;

    //     if ($selectedAddress) {
    //         $shortAddr = mb_substr($selectedAddress->address, 0, 35) . '...';
    //         $text .= "📍 <b>ارسال به:</b> {$shortAddr}\n";
    //         $text .= "📞 <b>تلفن:</b> {$selectedAddress->phone}\n";

    //         $keyboard[] = [['text' => '💳 تایید نهایی و پرداخت', 'callback_data' => 'ucart_checkout']];
    //         $keyboard[] = [['text' => '📍 تغییر آدرس ارسال', 'callback_data' => 'cart_choose_address']];
    //     } else {
    //         $text .= "⚠️ <i>برای نمایش دکمه پرداخت، ابتدا باید آدرس خود را ثبت یا انتخاب کنید.</i>\n";
    //         $keyboard[] = [['text' => '📍 انتخاب یا ثبت آدرس', 'callback_data' => 'cart_choose_address']];
    //     }

    //     $keyboard[] = [
    //         ['text' => '🗑 خالی کردن سبد', 'callback_data' => 'ucart_clear'],
    //         ['text' => '🛍 ادامه خرید', 'callback_data' => 'main_menu']
    //     ];

    //     if (isset($this->update['callback_query']['message']['photo'])) {
    //         $this->deleteMessage($this->messageId);
    //         $this->sendMessage($text, ['reply_markup' => json_encode(['inline_keyboard' => $keyboard]),]);
    //     } else {
    //         $this->editMessageText($text, ['reply_markup' => json_encode(['inline_keyboard' => $keyboard]),]);
    //     }
    // }



    /**
     * 🛒 نمایش سبد خرید
     * نسخه بازطراحی‌شده با چیدمان مرتب دکمه‌ها و تجربه کاربری بهتر
     */
    protected function showCart()
    {
        $carts = \App\Models\Cart::where('bot_id', $this->bot->id)
            ->where('user_id', $this->user->id)
            ->with(['product', 'variant'])
            ->get();

        $emptyCart = function () {
            $html = "🛒 <b>سبد خرید شما خالی است!</b><br><br>";
            $html .= "<i>برای شروع خرید، محصولات فروشگاه را مشاهده کنید.</i><br><br>";

            $html .= "<tg-button-row align=\"center\">";
            $html .= "<tg-button type=\"callback_data\" style=\"primary\" data=\"main_menu\">🛍 مشاهده محصولات</tg-button>";
            $html .= "</tg-button-row>";

            $this->sendOrEditRichMessage($html);
        };

        if ($carts->isEmpty()) {
            $emptyCart();
            return;
        }

        $escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = "🛒 <b>سبد خرید شما</b><br>";
        $html .= "<i>محصولات انتخاب‌شده را بررسی کنید.</i><br><br>";

        $totalOriginal = 0;
        $totalItemsPayable = 0; // مبلغ کل کالاها پس از تخفیف محصولات
        $totalQty = 0;

        foreach ($carts as $item) {
            if (!$item->product) {
                $item->delete();
                continue;
            }

            $productName = $escape($item->product->name);
            $variantName = $item->variant ? " ({$escape($item->variant->variant_name)})" : "";
            $fullName = $productName . $variantName;
            $maxStock = $item->variant ? $item->variant->stock : $item->product->stock;

            $basePrice = $item->product->price;

            $isProductDiscountValid = $item->product->discount_price > 0 &&
                (empty($item->product->discount_expires_at) || \Carbon\Carbon::parse($item->product->discount_expires_at)->isFuture());
            $discount = $isProductDiscountValid ? $item->product->discount_price : 0;

            if ($item->variant) {
                $basePrice = $item->variant->price > 0 ? $item->variant->price : $basePrice;
                $varExpires = $item->variant->discount_expires_at ?? $item->product->discount_expires_at;
                $isVariantDiscountValid = $item->variant->discount_price > 0 &&
                    (empty($varExpires) || \Carbon\Carbon::parse($varExpires)->isFuture());
                $discount = $isVariantDiscountValid ? $item->variant->discount_price : $discount;
            }

            $finalPrice = $discount > 0 ? $discount : $basePrice;
            $rowOriginal = $basePrice * $item->quantity;
            $rowPayable = $finalPrice * $item->quantity;

            $totalOriginal += $rowOriginal;
            $totalItemsPayable += $rowPayable;
            $totalQty += $item->quantity;

            $html .= "🔹 <b>{$fullName}</b><br>";
            if ($discount > 0 && $discount < $basePrice) {
                $html .= "▫️ قیمت اصلی: <s>" . number_format($basePrice) . "</s> تومان<br>";
            }
            $html .= "💵 <b>قیمت: " . number_format($finalPrice) . " تومان</b><br>";

            $html .= "<tg-button-row align=\"center\">";
            if ($item->quantity <= 1) {
                $html .= "<tg-button type=\"callback_data\" style=\"danger\" data=\"ucart_del_{$item->id}\">🗑</tg-button>";
            } else {
                $html .= "<tg-button type=\"callback_data\" data=\"ucart_qty_{$item->id}_" . ($item->quantity - 1) . "\">➖</tg-button>";
            }
            $html .= "<tg-button type=\"disabled\">{$item->quantity} عدد</tg-button>";
            if ($maxStock !== null && $item->quantity >= $maxStock) {
                $html .= "<tg-button type=\"disabled\">حداکثر</tg-button>";
            } else {
                $html .= "<tg-button type=\"callback_data\" data=\"ucart_qty_{$item->id}_" . ($item->quantity + 1) . "\">➕</tg-button>";
            }
            $html .= "</tg-button-row>";

            $html .= "<tg-button-row align=\"center\">";
            $html .= "<tg-button type=\"callback_data\" style=\"link\" data=\"show_prod_{$item->product_id}\">👁 مشاهده محصول</tg-button>";
            $html .= "<tg-button type=\"callback_data\" style=\"danger\" data=\"ucart_del_{$item->id}\">🗑 حذف محصول</tg-button>";
            $html .= "</tg-button-row><br>";
        }

        if ($totalQty <= 0) {
            $emptyCart();
            return;
        }

        // ==========================================
        // محاسبات نهایی (کد تخفیف + هزینه ارسال + کیف پول)
        // ==========================================
        $appliedCode = $this->getSession('applied_discount_code');
        $couponDiscountAmount = 0;
        $payableAfterCoupon = $totalItemsPayable; // مبنای اعمال کد تخفیف، فقط قیمت کالاهاست

        if ($appliedCode) {
            $discountRecord = \App\Models\DiscountCode::where('bot_id', $this->bot->id)->where('code', $appliedCode)->first();
            if ($discountRecord) {
                if ($discountRecord->type === 'percentage') {
                    $couponDiscountAmount = ($payableAfterCoupon * $discountRecord->value) / 100;
                } else {
                    $couponDiscountAmount = $discountRecord->value;
                }
                $couponDiscountAmount = min($couponDiscountAmount, $payableAfterCoupon);
                $payableAfterCoupon -= $couponDiscountAmount;
            } else {
                $this->setSession('applied_discount_code', null);
                $appliedCode = null;
            }
        }

        // افزودن هزینه ارسال
        $shippingCost = (int) \App\Models\Setting::where('bot_id', $this->bot->id)->where('key', 'shipping_cost')->value('value');
        $finalPayable = $payableAfterCoupon + $shippingCost;

        // کسر از کیف پول
        $walletBalance = max(0, (float) ($this->user->wallet_balance ?? 0));
        $useWallet = (bool) $this->getSession('use_wallet', false);
        $walletAmountUsed = 0;

        if ($useWallet && $walletBalance > 0) {
            $walletAmountUsed = min($walletBalance, $finalPayable);
            $finalPayable -= $walletAmountUsed;
        }

        // ==========================================
        // رندر فاکتور
        // ==========================================
        $html .= "<details open><summary>💳 <b>خلاصه فاکتور ({$totalQty} کالا)</b></summary><br>";
        $html .= "▫️ جمع کالاها: " . number_format($totalOriginal) . " تومان<br>";

        if ($totalOriginal > $totalItemsPayable) {
            $productProfit = $totalOriginal - $totalItemsPayable;
            $html .= "🎁 تخفیف محصولات: <b>" . number_format($productProfit) . " تومان</b><br>";
        }

        if ($appliedCode && $couponDiscountAmount > 0) {
            $html .= "🎟 تخفیف کد خرید: <b>-" . number_format($couponDiscountAmount) . " تومان</b><br>";
        }

        $html .= "🚚 هزینه ارسال: <b>" . ($shippingCost > 0 ? number_format($shippingCost) . " تومان" : "رایگان") . "</b><br>";

        if ($useWallet && $walletAmountUsed > 0) {
            $html .= "💰 کسر از کیف پول: <b>-" . number_format($walletAmountUsed) . " تومان</b><br>";
        }

        $html .= "<br>💵 <b>مبلغ قابل پرداخت: " . number_format($finalPayable) . " تومان</b></details><br>";

        $html .= "🎟 <b>تخفیف و کیف پول</b><br>";
        if ($walletBalance > 0 && !$useWallet) {
            $html .= "💎 <i>موجودی کیف پول شما: " . number_format($walletBalance) . " تومان</i><br>";
        }

        $html .= "<tg-button-row align=\"center\">";
        if ($appliedCode) {
            $html .= "<tg-button type=\"callback_data\" style=\"danger\" data=\"cart_remove_discount\">❌ لغو کد تخفیف</tg-button>";
        } else {
            $html .= "<tg-button type=\"callback_data\" style=\"primary\" data=\"cart_enter_discount\">🎟 ثبت کد تخفیف</tg-button>";
        }

        if ($walletBalance > 0) {
            if ($useWallet) {
                $html .= "<tg-button type=\"callback_data\" style=\"danger\" data=\"cart_toggle_wallet\">❌ لغو کیف پول</tg-button>";
            } else {
                $html .= "<tg-button type=\"callback_data\" style=\"success\" data=\"cart_toggle_wallet\">💰 استفاده از کیف پول</tg-button>";
            }
        }
        $html .= "</tg-button-row><br>";

        // آدرس ارسال و پرداخت
        $selectedAddressId = $this->getSession('checkout_address_id');
        $selectedAddress = $selectedAddressId ? \App\Models\UserAddress::find($selectedAddressId) : null;

        if ($selectedAddress) {
            $html .= "<details open><summary>📍 <b>آدرس ارسال سفارش</b></summary><br>";
            $html .= "🏠 آدرس: {$escape($selectedAddress->address)}<br>";
            $html .= "📞 تلفن: {$escape($selectedAddress->phone)}</details><br>";

            $html .= "<tg-button-row align=\"center\">";
            $html .= "<tg-button type=\"callback_data\" style=\"primary\" data=\"cart_choose_address\">📍 تغییر آدرس ارسال</tg-button>";
            $html .= "</tg-button-row><br>";

            $html .= "<tg-button-row align=\"center\">";
            $html .= "<tg-button type=\"callback_data\" style=\"success\" data=\"ucart_checkout\">💳 تایید سفارش و پرداخت</tg-button>";
            $html .= "</tg-button-row>";
        } else {
            $html .= "⚠️ <b>آدرس ارسال انتخاب نشده است.</b><br><i>برای ادامه خرید، ابتدا آدرس خود را ثبت کنید.</i><br><br>";
            $html .= "<tg-button-row align=\"center\">";
            $html .= "<tg-button type=\"callback_data\" style=\"primary\" data=\"cart_choose_address\">📍 انتخاب یا ثبت آدرس</tg-button>";
            $html .= "</tg-button-row>";
        }

        $html .= "<br><tg-button-row align=\"center\">";
        $html .= "<tg-button type=\"callback_data\" data=\"main_menu\">🛍 ادامه خرید</tg-button>";
        $html .= "<tg-button type=\"callback_data\" style=\"danger\" data=\"ucart_clear\">🗑 خالی کردن سبد</tg-button>";
        $html .= "</tg-button-row>";

        $this->sendOrEditRichMessage($html);
    }

    protected function generateInvoice($method = 'online')
    {
        $addressId = $this->getSession('checkout_address_id');
        if (!$addressId)
            return;

        $carts = \App\Models\Cart::where('bot_id', $this->bot->id)->where('user_id', $this->user->id)->with(['product', 'variant'])->get();
        if ($carts->isEmpty())
            return;

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $totalOriginal = 0;
            $totalItemsPayable = 0;
            $outOfStockItems = [];

            // بررسی موجودی
            foreach ($carts as $item) {
                if (!$item->product)
                    continue;

                $lockedProduct = \App\Models\Product::where('id', $item->product_id)->lockForUpdate()->first();
                if ($item->variant_id) {
                    $lockedVariant = \App\Models\ProductVariant::where('id', $item->variant_id)->lockForUpdate()->first();
                    if ($lockedVariant && $lockedVariant->stock !== null && $lockedVariant->stock < $item->quantity) {
                        $outOfStockItems[] = $lockedVariant->variant_name ?? $lockedProduct->name;
                    }
                } else {
                    if ($lockedProduct && $lockedProduct->stock !== null && $lockedProduct->stock < $item->quantity) {
                        $outOfStockItems[] = $lockedProduct->name;
                    }
                }
            }

            if (!empty($outOfStockItems)) {
                \Illuminate\Support\Facades\DB::rollBack();
                $names = implode(' ، ', $outOfStockItems);
                $this->sendRequest('answerCallbackQuery', ['callback_query_id' => $this->update['callback_query']['id'], 'text' => "موجودی این کالاها کافی نیست:\n\n{$names}", 'show_alert' => true]);
                $this->showCart();
                return;
            }

            // محاسبه مبالغ
            foreach ($carts as $item) {
                $basePrice = $item->product->price;
                $isProductDiscountValid = $item->product->discount_price > 0 && (empty($item->product->discount_expires_at) || \Carbon\Carbon::parse($item->product->discount_expires_at)->isFuture());
                $discount = $isProductDiscountValid ? $item->product->discount_price : 0;

                if ($item->variant) {
                    $basePrice = ($item->variant->price > 0) ? $item->variant->price : $basePrice;
                    $varExpires = $item->variant->discount_expires_at ?? $item->product->discount_expires_at;
                    $isVariantDiscountValid = $item->variant->discount_price > 0 && (empty($varExpires) || \Carbon\Carbon::parse($varExpires)->isFuture());
                    $discount = $isVariantDiscountValid ? $item->variant->discount_price : $discount;
                }

                $finalPrice = ($discount > 0) ? $discount : $basePrice;
                $totalOriginal += ($basePrice * $item->quantity);
                $totalItemsPayable += ($finalPrice * $item->quantity);
            }

            $appliedCode = $this->getSession('applied_discount_code');
            $couponDiscountAmount = 0;
            $payableAfterCoupon = $totalItemsPayable;

            if ($appliedCode) {
                $discountRecord = \App\Models\DiscountCode::where('bot_id', $this->bot->id)->where('code', $appliedCode)->first();
                if ($discountRecord) {
                    if ($discountRecord->type === 'percentage') {
                        $couponDiscountAmount = ($payableAfterCoupon * $discountRecord->value) / 100;
                    } else {
                        $couponDiscountAmount = $discountRecord->value;
                    }
                    $couponDiscountAmount = min($couponDiscountAmount, $payableAfterCoupon);
                    $payableAfterCoupon -= $couponDiscountAmount;
                } else {
                    $appliedCode = null;
                }
            }

            $productDiscount = $totalOriginal - $totalItemsPayable;
            $totalDiscount = $productDiscount + $couponDiscountAmount;

            $shippingCost = (int) \App\Models\Setting::where('bot_id', $this->bot->id)->where('key', 'shipping_cost')->value('value');
            $finalPayable = $payableAfterCoupon + $shippingCost;

            $walletBalance = max(0, (float) ($this->user->wallet_balance ?? 0));
            $useWallet = (bool) $this->getSession('use_wallet', false);
            $walletAmountUsed = 0;

            if ($useWallet && $walletBalance > 0) {
                $walletAmountUsed = min($walletBalance, $finalPayable);
                $finalPayable -= $walletAmountUsed;
            }

            // ذخیره فاکتور با تفکیک شفاف در user_info
            $invoice = \App\Models\Invoice::create([
                'bot_id' => $this->bot->id,
                'user_id' => $this->user->id,
                'tracking_code' => 'INV-' . strtoupper(uniqid()),
                'total_amount' => $totalOriginal,
                'discount_amount' => $totalDiscount,
                'wallet_amount_used' => $walletAmountUsed,
                'status' => $finalPayable == 0 ? 'paid' : 'pending',
                'applied_discount_code' => $appliedCode,
                'user_info' => [
                    'address_id' => $addressId,
                    'shipping_cost' => $shippingCost,
                    'payment_method' => $finalPayable == 0 ? 'wallet' : $method,
                    'product_discount' => $productDiscount, // 👈 تفکیک تخفیف کالا
                    'coupon_discount' => $couponDiscountAmount // 👈 تفکیک کد تخفیف
                ],
            ]);

            if ($walletAmountUsed > 0) {
                $this->user->modifyWallet(
                    -$walletAmountUsed,
                    'purchase',
                    "خرید از فروشگاه - سفارش {$invoice->tracking_code}",
                    $invoice->id
                );
            }

            foreach ($carts as $item) {
                $basePrice = $item->product->price;
                $isProductDiscountValid = $item->product->discount_price > 0 && (empty($item->product->discount_expires_at) || \Carbon\Carbon::parse($item->product->discount_expires_at)->isFuture());
                $discount = $isProductDiscountValid ? $item->product->discount_price : 0;

                if ($item->variant) {
                    $basePrice = ($item->variant->price > 0) ? $item->variant->price : $basePrice;
                    $varExpires = $item->variant->discount_expires_at ?? $item->product->discount_expires_at;
                    $isVariantDiscountValid = $item->variant->discount_price > 0 && (empty($varExpires) || \Carbon\Carbon::parse($varExpires)->isFuture());
                    $discount = $isVariantDiscountValid ? $item->variant->discount_price : $discount;
                }
                $finalPrice = ($discount > 0) ? $discount : $basePrice;
                $productName = $item->product->name;
                if ($item->variant)
                    $productName .= " (تنوع: {$item->variant->variant_name})";

                \App\Models\InvoiceItem::create([
                    'bot_id' => $this->bot->id,
                    'invoice_id' => $invoice->id,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'name_at_purchase' => $productName,
                    'quantity' => $item->quantity,
                    'price' => $finalPrice,
                ]);

                if ($item->variant_id) {
                    \App\Models\ProductVariant::where('id', $item->variant_id)->decrement('stock', $item->quantity);
                } else {
                    \App\Models\Product::where('id', $item->product_id)->decrement('stock', $item->quantity);
                }
            }

            \App\Models\Cart::where('bot_id', $this->bot->id)->where('user_id', $this->user->id)->delete();
            \Illuminate\Support\Facades\DB::commit();

            $this->setSession('checkout_address_id', null);
            $this->setSession('use_wallet', null);

            if ($appliedCode) {
                \App\Models\DiscountCode::where('bot_id', $this->bot->id)->where('code', $appliedCode)->increment('usage_count');
                $this->setSession('applied_discount_code', null);
            }

            if ($finalPayable == 0) {
                $text = "🎉 <b>سفارش با موفقیت ثبت و پرداخت شد!</b>\n\n";
                $text .= "📦 کد پیگیری: <code>{$invoice->tracking_code}</code>\n";
                $text .= "💳 کل مبلغ از کیف پول شما کسر گردید.\n\n";
                $text .= "<i>میتوانید از بخش پیگیری سفارشات وضعیت آن را بررسی کنید.</i>";

                $this->editMessageText($text, ['reply_markup' => json_encode(['inline_keyboard' => [[['text' => '📦 پیگیری سفارشات', 'callback_data' => 'my_orders_list']]]])]);
                return;
            }

            if ($method === 'online') {
                $paymentUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute('payment.show', now()->addMinutes(30), ['invoice' => $invoice->id]);
                $text = "🎉 <b>سفارش شما ثبت شد!</b>\n\nکد پیگیری: <code>{$invoice->tracking_code}</code>\nمبلغ قابل پرداخت: <b>" . number_format($finalPayable) . " تومان</b>\n\n👇 <b>برای پرداخت آنلاین روی دکمه زیر کلیک کنید:</b>";
                $this->editMessageText($text, ['reply_markup' => json_encode(['inline_keyboard' => [[['text' => '💳 پرداخت آنلاین', 'url' => $paymentUrl]]]])]);
            } else if ($method === 'card') {
                $cardNumber = $this->bot->settings->firstWhere('key', 'bank_card_number')?->value;
                $cardName = $this->bot->settings->firstWhere('key', 'bank_card_name')?->value;

                $text = "💳 <b>پرداخت کارت به کارت</b>\n\n";
                $text .= "سفارش <code>{$invoice->tracking_code}</code> ثبت شد.\n";
                $text .= "مبلغ قابل پرداخت: <b>" . number_format($finalPayable) . " تومان</b>\n \n";
                $text .= "💳 <b>شماره کارت:</b> <code>{$cardNumber}</code>\n";
                $text .= "👤 <b>به نام:</b> {$cardName}\n \n\n";
                $text .= "⚠️ <b>توجه:</b> لطفاً پس از واریز، <b>رسید پرداخت (عکس)</b> را همینجا ارسال کنید.";

                $keyboard = [
                    [['text' => '📋 کپی شماره کارت', 'copy_text' => ['text' => $cardNumber]]],
                    [['text' => '❌ لغو سفارش', 'callback_data' => "cart_cancel_invoice_{$invoice->id}"]]
                ];
                $res = $this->editMessageText($text, ['reply_markup' => json_encode(['inline_keyboard' => $keyboard])]);

                $this->setSession('state', "cart_wait_receipt_{$invoice->id}");
                if (isset($res['result']['message_id'])) {
                    $this->setSession('message_id', $res['result']['message_id']);
                }
            }

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            $this->sendRequest('answerCallbackQuery', ['callback_query_id' => $this->update['callback_query']['id'], 'text' => "خطایی در ثبت سفارش رخ داد.", 'show_alert' => true]);
        }
    }
    /**
     * 🧠 متد کمکی برای هندل کردن ویرایش یا ارسال Rich Message (بدون هیچ‌گونه پرش)
     */
    protected function sendOrEditRichMessage($html)
    {
        $payload = [
            'chat_id' => $this->chatId,
            'rich_message' => ['html' => $html],
            'disable_notification' => true
        ];

        // اگر پیامی از قبل وجود دارد و کاربر روی دکمه کلیک کرده (مثل دکمه‌های + و -)
        if ($this->messageId && isset($this->update['callback_query'])) {
            $payload['message_id'] = $this->messageId;

            // 🚀 نکته کلیدی: تلگرام پارامتر text را برای این متد اجباری می‌داند!
            // این متن به کاربر نمایش داده نمی‌شود (چون rich_message جایگزین آن می‌شود)، اما برای رد شدن از ارور 400 الزامی است.
            $payload['text'] = '🛒 در حال بروزرسانی...';

            $res = \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$this->bot->bot_token}/editMessageText", $payload);

            $result = $res->json();

            // اگر تلگرام ارور داد
            if (!$res->successful() || empty($result['ok'])) {
                $errorDesc = strtolower($result['description'] ?? '');

                // 🛡 سپر ضد پرش: اگر کاربر تند تند کلیک کرد و تلگرام گفت پیام تغییری نکرده، اصلاً پیام رو پاک نکن!
                if (str_contains($errorDesc, 'message is not modified')) {
                    return;
                }

                // اگر پیام قبلی عکس‌دار بوده (مثل کپشن محصول) و قابل ادیتِ متنی نبود:
                $this->deleteMessage($this->messageId);
                unset($payload['message_id']);
                unset($payload['text']); // برای sendRichMessage پارامتر text نیاز نیست
                \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$this->bot->bot_token}/sendRichMessage", $payload);
            }
        } else {
            // ارسال به عنوان پیام جدید
            \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$this->bot->bot_token}/sendRichMessage", $payload);
        }
    }
    /**
     * 🔄 تغییر آنلاین تعداد یک آیتم در سبد
     */
    protected function updateCartItemQty($cartId, $newQty)
    {
        $newQty = (int) $newQty;
        $cartItem = \App\Models\Cart::with(['product', 'variant'])->find($cartId);

        if (!$cartItem)
            return;

        // اگر کاربر با دکمه ➖ تعداد را به صفر رساند، محصول حذف می‌شود
        if ($newQty < 1) {
            $cartItem->delete();
            $this->sendRequest('answerCallbackQuery', ['callback_query_id' => $this->update['callback_query']['id'], 'text' => "🗑 کالا از سبد خرید حذف شد."]);
            $this->showCart();
            return;
        }

        // 🛑 محافظت از موجودی انبار در لحظه تغییر
        $maxStock = $cartItem->variant ? $cartItem->variant->stock : $cartItem->product->stock;
        if ($maxStock !== null && $newQty > $maxStock) {
            $this->sendRequest('answerCallbackQuery', [
                'callback_query_id' => $this->update['callback_query']['id'],
                'text' => "⚠️ موجودی انبار برای این کالا کافی نیست (حداکثر {$maxStock} عدد موجود است).",
                'show_alert' => true
            ]);
            return;
        }

        // ذخیره تغییر و بروزرسانی لایو فاکتور
        $cartItem->quantity = $newQty;
        $cartItem->save();

        $this->showCart();
    }

    /**
     * 🗑 حذف کامل یک آیتم با دکمه سطل زباله
     */
    protected function removeCartItem($cartId)
    {
        $cartItem = \App\Models\Cart::find($cartId);
        if ($cartItem) {
            $cartItem->delete();
            $this->sendRequest('answerCallbackQuery', ['callback_query_id' => $this->update['callback_query']['id'], 'text' => "🗑 با موفقیت از فاکتور حذف شد."]);
        }
        $this->showCart();
    }


    /**
     * 📝 فرم دریافت آدرس جدید از کاربر
     */
    protected function askForNewAddress()
    {
        $text = "📍 <b>ثبت آدرس جدید</b>\n\n";
        $text .= "لطفاً <b>آدرس دقیق و کامل</b> خود را (شامل استان، شهر، خیابان، پلاک، واحد و کد پستی) در قالب یک پیام متنی ارسال کنید:";

        $keyboard = [[['text' => '🔙 انصراف و بازگشت به سبد', 'callback_data' => 'view_cart']]];

        $this->editMessageText($text, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),

        ]);

        $this->setSession('state', 'cart_wait_address_text');

        if ($this->messageId) {
            $this->setSession('message_id', $this->messageId);
        }
    }

    /**
     * 📍 نمایش منوی انتخاب آدرس‌ها
     */
    protected function showAddressSelection()
    {
        $addresses = \App\Models\UserAddress::where('user_id', $this->user->id)->latest()->take(4)->get();

        if ($addresses->isEmpty()) {
            $this->askForNewAddress();
            return;
        }

        $text = "📍 <b>انتخاب آدرس ارسال</b>\n\n";
        $text .= "لطفاً برای دریافت سفارش، یکی از آدرس‌های قبلی خود را انتخاب کرده یا آدرس جدیدی ثبت کنید:";

        $keyboard = [];
        foreach ($addresses as $addr) {
            $shortAddr = mb_substr($addr->address, 0, 30) . '...';
            // 💡 با کلیک روی هر آدرس، مسیر cart_select_addr_ فراخوانی می‌شود
            $keyboard[] = [
                ['text' => "📍 {$shortAddr}", 'callback_data' => "cart_select_addr_{$addr->id}"]
            ];
        }

        $keyboard[] = [['text' => '➕ افزودن آدرس جدید', 'callback_data' => 'cart_checkout_new_addr']];
        $keyboard[] = [['text' => '🔙 بازگشت به سبد خرید', 'callback_data' => 'view_cart']];

        $this->editMessageText($text, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),

        ]);
    }

    /**
     * 💳 نمایش هشدار قفل شدن سبد و تایید نهایی
     */
    protected function showFinalInvoice()
    {
        $addressId = $this->getSession('checkout_address_id');
        $address = \App\Models\UserAddress::find($addressId);

        if (!$address) {
            $this->showCart();
            return;
        }

        $cardNumber = $this->bot->settings->firstWhere('key', 'bank_card_number')?->value;

        $text = "⚠️ <b>مرحله نهایی قبل از پرداخت</b>\n\n";
        $text .= "📍 <b>ارسال به:</b> {$address->address}\n";
        $text .= "📞 <b>تلفن گیرنده:</b> {$address->phone}\n\n";
        $text .= "<i>با انتخاب روش پرداخت، سبد خرید فعلی شما بسته شده و <b>فاکتور رسمی</b> صادر می‌گردد.</i>\n\n";
        $text .= "👇 <b>لطفاً روش پرداخت خود را انتخاب کنید:</b>";

        $keyboard = [];

        // $keyboard[] = [['text' => '🌐 پرداخت آنلاین (درگاه زرین‌پال)', 'callback_data' => 'cart_checkout_online']];

        if (!empty($cardNumber)) {
            $keyboard[] = [['text' => '💳 کارت به کارت (ارسال فیش واریزی)', 'callback_data' => 'cart_checkout_card']];
        }

        $keyboard[] = [['text' => '🔙 انصراف و بازگشت به سبد', 'callback_data' => 'view_cart']];

        $this->editMessageText($text, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
        ]);
    }


    /**
     * 📦 نمایش لیست سفارشات قبلی کاربر
     */
    protected function showMyOrders()
    {
        // دریافت ۱۰ سفارش آخر کاربر
        $invoices = \App\Models\Invoice::where('bot_id', $this->bot->id)
            ->where('user_id', $this->user->id)
            ->orderBy('id', 'desc')
            ->take(10)
            ->get();

        if ($invoices->isEmpty()) {
            $text = "📦 <b>شما هنوز هیچ سفارشی ثبت نکرده‌اید!</b>\n\n<i>برای ثبت اولین سفارش خود از ویترین محصولات دیدن کنید.</i>";

            $keyboard = [[['text' => '🛍 مشاهده ویترین محصولات', 'callback_data' => 'main_menu']]];
            // اگر از روی دکمه شیشه‌ای کلیک شده بود ویرایش کند، در غیر این صورت پیام جدید بفرستد
            if (isset($this->update['callback_query'])) {
                $this->editMessageText($text, ['reply_markup' => json_encode(['inline_keyboard' => $keyboard]),]);
            } else {
                $this->sendMessage($text, ['reply_markup' => json_encode(['inline_keyboard' => $keyboard]),]);
            }
            return;
        }

        $text = "📦 <b>تاریخچه سفارشات شما</b>\n\n";
        $text .= "<i>👇 برای مشاهده جزئیات و دانلود فاکتور، روی سفارش مورد نظر کلیک کنید:</i>";

        $keyboard = [];
        foreach ($invoices as $invoice) {
            $statusIcon = $this->getStatusIcon($invoice->status);
            $date = \Morilog\Jalali\Jalalian::fromDateTime($invoice->created_at)->format('Y/m/d');
            $price = number_format($invoice->final_amount);

            $keyboard[] = [
                ['text' => "{$statusIcon} {$invoice->tracking_code} ◂ {$price} T", 'callback_data' => "view_order_{$invoice->id}"]
            ];
        }
        $keyboard[] = [['text' => '« منو ', 'callback_data' => 'main_menu']];

        if (isset($this->update['callback_query'])) {
            $this->editMessageText($text, ['reply_markup' => json_encode(['inline_keyboard' => $keyboard]),]);
        } else {
            $this->sendMessage($text, ['reply_markup' => json_encode(['inline_keyboard' => $keyboard]),]);
        }
    }


    /**
     * 👤 نمایش پروفایل کاربری با استفاده از Rich Message
     */
    protected function showUserProfile()
    {
        $user = $this->user;

        // قالب‌بندی تاریخ عضویت به شمسی
        $joinDate = \Morilog\Jalali\Jalalian::fromDateTime($user->created_at)->format('Y/m/d');

        // محاسبه تعداد سفارشات موفق این کاربر (فاکتورهای پرداخت‌شده و در جریان)
        $successfulOrders = \App\Models\Invoice::where('bot_id', $this->bot->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])
            ->count();

        // استخراج موجودی کیف پول
        $walletBalance = max(0, (float) ($user->wallet_balance ?? 0));

        // فرمت‌بندی نام و نام کاربری
        $fullName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'کاربر عزیز';
        $username = $user->username ? '@' . $user->username : 'ثبت نشده';

        // ==========================================
        // 📝 تولید HTML پروفایل
        // ==========================================
       $html = "👤 <b>حساب کاربری شما</b><br>";
        $html .= "<i>خلاصه وضعیت و اطلاعات شخصی شما در فروشگاه</i><br><br>";
        
        $phoneDisplay = $user->phone ?? 'ثبت نشده ❌'; 

        // بخش مشخصات فردی (آکاردئون)
        $html .= "<details open><summary>📋 <b>مشخصات فردی</b></summary><br>";
        $html .= "▫️ نام: <b>{$fullName}</b><br>";
        $html .= "▫️ نام کاربری: <span dir=\"ltr\">{$username}</span><br>";
        $html .= "▫️ تلفن: <span dir=\"ltr\">{$phoneDisplay}</span><br>"; // 👈 اضافه شدن تلفن
        $html .= "▫️ کد اشتراک: <code>{$user->chat_id}</code><br>";
        $html .= "▫️ تاریخ عضویت: <b>{$joinDate}</b><br>";
        $html .= "</details><br><br>";

        // بخش مالی و آماری (آکاردئون)
        $html .= "<details open><summary>💳 <b>کیف پول و آمار خرید</b></summary><br>";
        $html .= "💰 موجودی کیف پول: <b>" . number_format($walletBalance) . " تومان</b><br>";
        $html .= "🛍 سفارشات موفق: <b>{$successfulOrders} سفارش</b><br>";
        $html .= "</details><br><br>";

        // پیام راهنمای هوشمند بر اساس موجودی کیف پول
        if ($walletBalance > 0) {
            $html .= "💡 <i>موجودی کیف پول شما در هنگام پرداخت فاکتور بعدی، به‌صورت خودکار قابل استفاده خواهد بود.</i>";
        } else {
            $html .= "💡 <i>در صورتی که ادمین سفارشی را لغو کند یا وجهی را به شما هدیه دهد، مبلغ به این کیف پول واریز می‌شود.</i>";
        }

        // ==========================================
        // 🎛 دکمه‌های ناوبری سریع در پروفایل
        // ==========================================
        $keyboard = [
            [
                ['text' => '📦 تاریخچه سفارشات', 'callback_data' => 'my_orders_list'],
                ['text' => '❤️ علاقه‌مندی‌ها', 'callback_data' => 'my_favorites']
            ],
            [
                ['text' => '🛒 مشاهده سبد خرید', 'callback_data' => 'view_cart']
            ],
            [
                ['text' => '🏠 بازگشت به ویترین اصلی', 'callback_data' => 'main_menu']
            ]
        ];

        // 🌟 اگر شماره نداشت، مهم‌ترین دکمه پروفایل می‌شود ثبت شماره
        if (empty($this->user->phone)) {
            array_unshift($keyboard, [['text' => '📱 ثبت شماره موبایل', 'callback_data' => 'req_contact']]);
        }

        // 🧹 پیام قبلی را پاک می‌کنیم تا رندر آکاردئون‌ها و متن‌های Rich تمیز انجام شود
        if ($this->messageId) {
            $this->deleteMessage($this->messageId);
        }

        // 🚀 ارسال پروفایل از طریق اندپوینت Rich Message تلگرام
        \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$this->bot->bot_token}/sendRichMessage", [
            'chat_id' => $this->chatId,
            'rich_message' => [
                'html' => $html
            ],
            'reply_markup' => [
                'inline_keyboard' => $keyboard
            ],
            'disable_notification' => true
        ]);
    }
    /**
     * 🧾 نمایش جزئیات دقیق یک سفارش به مشتری (استایل Rich Message و خفن!)
     */
    protected function showOrderDetails($invoiceId)
    {
        $invoice = \App\Models\Invoice::with('items')->where('id', $invoiceId)->where('user_id', $this->user->id)->first();
        if (!$invoice)
            return;

        $statusIcon = $this->getStatusIcon($invoice->status);
        $statusText = $this->getStatusText($invoice->status);

        $shippingCost = $invoice->user_info['shipping_cost'] ?? 0;
        $productDiscount = $invoice->user_info['product_discount'] ?? 0;
        $couponDiscount = $invoice->user_info['coupon_discount'] ?? 0;

        $finalAmount = $invoice->total_amount - $invoice->discount_amount + $shippingCost - $invoice->wallet_amount_used;
        $date = \Morilog\Jalali\Jalalian::fromDateTime($invoice->created_at)->format('Y/m/d ساعت H:i');

        $html = "🧾 <b>فاکتور سفارش <code>{$invoice->tracking_code}</code></b><br>";
        $html .= "🚦 <b>وضعیت:</b> {$statusIcon} <b>{$statusText}</b><br>";
        $html .= "🗓 <b>تاریخ ثبت:</b> {$date}<br><br>";

        $html .= "🛒 <b>محصولات خریداری شده:</b><br>";
        $html .= "<table bordered striped compact>";
        $html .= "<tr><th align=\"right\">نام محصول</th><th align=\"center\">تعداد</th></tr>";

        foreach ($invoice->items as $item) {
            $itemName = $item->name_at_purchase;
            $html .= "<tr><td align=\"right\">{$itemName}</td><td align=\"center\"><b>{$item->quantity}</b></td></tr>";
        }
        $html .= "</table><br><br>";

        // 💰 تفکیک کامل و شفاف مالی
        $html .= "<details open><summary>💰 <b>جزئیات پرداختی</b></summary>";
        $html .= "▫️ جمع قیمت اصلی کالاها: " . number_format($invoice->total_amount) . " T<br>";

        if ($productDiscount > 0 || $couponDiscount > 0) {
            if ($productDiscount > 0) {
                $html .= "🎁 تخفیف کالاها: " . number_format($productDiscount) . " T<br>";
            }
            if ($couponDiscount > 0) {
                $html .= "🎟 تخفیف کد <code>({$invoice->applied_discount_code})</code>: " . number_format($couponDiscount) . " T<br>";
            }
        } elseif ($invoice->discount_amount > 0) {
            $codeStr = $invoice->applied_discount_code ? " <code>({$invoice->applied_discount_code})</code>" : "";
            $html .= "🎁 مجموع تخفیف‌ها{$codeStr}: " . number_format($invoice->discount_amount) . " T<br>";
        }

        $html .= "🚚 هزینه ارسال: " . ($shippingCost > 0 ? number_format($shippingCost) . " T" : "رایگان") . "<br>";

        if ($invoice->wallet_amount_used > 0) {
            $html .= "💰 کسر از کیف پول: " . number_format($invoice->wallet_amount_used) . " T<br>";
        }

        $html .= "<br>💳 <b>مبلغ نهایی پرداختی: " . number_format($finalAmount) . " تومان</b><br>";
        $html .= "</details><br><br>";

        if ($invoice->status === 'pending') {
            $html .= "⏳ <i>مشتری عزیز، سفارش شما در انتظار پرداخت است. لطفاً جهت جلوگیری از لغو خودکار، مبلغ را پرداخت نمایید.</i>";
        } elseif ($invoice->status === 'shipped') {
            $html .= "🚚 <i>سفارش شما تحویل پست/پیک شده است و به زودی به دستتان خواهد رسید.</i>";
        } elseif ($invoice->status === 'paid' || $invoice->status === 'processing') {
            $html .= "📦 <i>سفارش شما در حال پردازش و بسته‌بندی است.</i>";
        } else {
            $html .= "✨ <i>از اینکه ما را برای خرید انتخاب کردید سپاسگزاریم. ❤️</i>";
        }

        $keyboard = [];
        if ($invoice->status === 'pending') {
            $paymentMethod = $invoice->user_info['payment_method'] ?? 'online';
            if ($paymentMethod === 'card') {
                $keyboard[] = [['text' => '📸 مشاهده کارت و ارسال فیش واریزی', 'callback_data' => "cart_back_to_pay_{$invoice->id}"]];
            } else {
                $paymentUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute('payment.show', now()->addHours(12), ['invoice' => $invoice->id]);
                $keyboard[] = [['text' => '💳 پرداخت آنلاین و تکمیل سفارش', 'url' => $paymentUrl]];
            }
        }

        if ($invoice->receipt_file_id) {
            $keyboard[] = [['text' => '📥 دانلود فاکتور رسمی (PDF)', 'callback_data' => "dl_receipt_{$invoice->id}"]];
        }

        $keyboard[] = [['text' => '🔙 بازگشت به لیست سفارشات', 'callback_data' => 'my_orders_list']];

        if ($this->messageId)
            $this->deleteMessage($this->messageId);

        http::post("https://api.telegram.org/bot{$this->bot->bot_token}/sendRichMessage", [
            'chat_id' => $this->chatId,
            'rich_message' => ['html' => $html],
            'reply_markup' => ['inline_keyboard' => $keyboard],
            'disable_notification' => true
        ]);
    }

    /**
     * 📥 استخراج فایل PDF از سرور و ارسال به تلگرام مشتری/ادمین
     */
    protected function sendOrderReceipt($invoiceId)
    {
        // 💡 اصلاح مهم: کوئری هوشمند بر اساس سطح دسترسی (ادمین یا مشتری)
        $query = \App\Models\Invoice::where('bot_id', $this->bot->id)->where('id', $invoiceId);

        // اگر کاربر فعلی ادمین نیست، فقط اجازه دارد فاکتور خودش را ببیند
        if ($this->user->is_admin != 1) {
            $query->where('user_id', $this->user->id);
        }

        $invoice = $query->first();

        if (!$invoice || empty($invoice->receipt_file_id)) {
            $this->sendRequest('answerCallbackQuery', [
                'callback_query_id' => $this->update['callback_query']['id'],
                'text' => "❌ فاکتور این سفارش یافت نشد یا هنوز صادر نشده است.",
                'show_alert' => true
            ]);
            return;
        }

        $filePath = storage_path('app/public/' . $invoice->receipt_file_id);

        if (!file_exists($filePath)) {
            $this->sendRequest('answerCallbackQuery', [
                'callback_query_id' => $this->update['callback_query']['id'],
                'text' => "❌ فایل فاکتور از سرور حذف شده و در دسترس نیست.",
                'show_alert' => true
            ]);
            return;
        }

        // ارسال پاپ‌آپ کوچک در حال بارگذاری برای حس بهتر (UX)
        $this->sendRequest('answerCallbackQuery', [
            'callback_query_id' => $this->update['callback_query']['id'],
            'text' => "⏳ در حال ارسال فایل فاکتور..."
        ]);

        // ارسال فایل از سرور محلی به تلگرام
        http::attach(
            'document',
            file_get_contents($filePath),
            "Invoice-{$invoice->tracking_code}.pdf"
        )->post("https://api.telegram.org/bot{$this->bot->bot_token}/sendDocument", [
                    'chat_id' => $this->chatId,
                    'caption' => "🧾 <b>فاکتور رسمی سفارش</b>\nکد پیگیری: <code>{$invoice->tracking_code}</code>",
                    'parse_mode' => 'HTML' // 👈 پارس‌مد اضافه شد تا تگ‌های HTML در کپشنِ فایل هم به درستی کار کنند
                ]);
    }

    /**
     * 🎨 ترجمه کلمات وضعیت دیتابیس به فارسی روان
     */
    protected function getStatusText($status)
    {
        return match ($status) {
            'pending' => 'در انتظار پرداخت',
            'paid' => 'پرداخت شده - در حال پردازش',
            'processing' => 'در حال آماده‌سازی برای ارسال',
            'shipped' => 'سفارش ارسال شد 📦',
            'delivered' => 'تحویل داده شد 🎉',
            'canceled' => 'سفارش لغو شد',
            'failed' => 'پرداخت ناموفق',
            'awaiting_approval' => 'در انتظار تأیید مدیریت',
            default => 'وضعیت نامشخص'
        };
    }

    /**
     * 🎨 آیکون‌های وضعیت سفارش
     */
    protected function getStatusIcon($status)
    {
        return match ($status) {
            'pending' => '⏳',
            'paid' => '💰',
            'processing' => '🔧',
            'shipped' => '📦',
            'delivered' => '✅',
            'canceled' => '❌',
            'failed' => '⚠️',
            'awaiting_approval' => '👀',
            default => '📌'
        };
    }


    // ================================================================
    // ⚙️ تنظیمات فروشگاه (Store Settings)
    // ================================================================

    /**
     * نمایش تنظیمات پیشرفته فروشگاه (پنل ادمین)
     */
    /**
     * ⚙️ پنل تنظیمات پیشرفته فروشگاه (نسخه داینامیک دیتابیس + آکاردئون Bot API 10.3)
     */
    /**
     * ⚙️ پنل تنظیمات پیشرفته فروشگاه (نسخه فوق‌العاده شیک، خوانا و آکاردئونی)
     */
    protected function showAdminSettings()
    {
        // 🛡 بررسی امنیت: فقط ادمین
        if ($this->user->is_admin != 1) {
            $this->sendRequest('answerCallbackQuery', ['callback_query_id' => $this->update['callback_query']['id'], 'text' => "⛔ دسترسی غیرمجاز", 'show_alert' => true]);
            return;
        }

        $settings = $this->bot->settings()->get();

        $settingsMap = [
            'store_name' => ['icon' => '🏪', 'order' => 1, 'group' => 'تنظیمات عمومی'],
            'is_active' => ['icon' => '🚀', 'order' => 2, 'group' => 'تنظیمات عمومی'],
            'welcome_message' => ['icon' => '👋', 'order' => 3, 'group' => 'تنظیمات عمومی'],
            'channel_id' => ['icon' => '📢', 'order' => 4, 'group' => 'تنظیمات عمومی'],
            'shipping_cost' => ['icon' => '🚚', 'order' => 5, 'group' => 'مالی و ارسال'],
            'show_discounted_products' => ['icon' => '🔥', 'order' => 6, 'group' => 'ظاهر فروشگاه'],
            'support_text' => ['icon' => '📝', 'order' => 7, 'group' => 'ارتباط و پشتیبانی'],
            'support_telegram_id' => ['icon' => '💬', 'order' => 8, 'group' => 'ارتباط و پشتیبانی'],
            'support_phone' => ['icon' => '📞', 'order' => 9, 'group' => 'ارتباط و پشتیبانی'],
            'force_join' => ['icon' => '🔒', 'order' => 10, 'group' => 'سایر تنظیمات'],
        ];

        $groupedSettings = [];
        $sortedSettings = $settings->sortBy(function ($setting) use ($settingsMap) {
            return $settingsMap[$setting->key]['order'] ?? 99;
        });

        foreach ($sortedSettings as $setting) {
            $groupName = $settingsMap[$setting->key]['group'] ?? 'سایر تنظیمات';
            $groupedSettings[$groupName][] = $setting;
        }

        // 📝 هدر اصلی
        $html = "⚙️ <b>تنظیمات پیشرفته فروشگاه</b><br><br>";

        foreach ($groupedSettings as $groupName => $currentGroupSettings) {
            $groupIcon = match ($groupName) {
                'تنظیمات عمومی' => '📦',
                'مالی و ارسال' => '💳',
                'ظاهر فروشگاه' => '🎨',
                'ارتباط و پشتیبانی' => '📞',
                default => '🔒'
            };

            // باز کردن آکاردئون
            $html .= "<details><summary>{$groupIcon} <b>{$groupName}</b></summary><br>";

            foreach ($currentGroupSettings as $setting) {
                $icon = $settingsMap[$setting->key]['icon'] ?? '⚙️';
                $nameText = $setting->description ?? $setting->key;
                $val = $setting->value;

                // ✂️ مدیریت متن‌های طولانی و فرمت مقادیر
                if ($setting->type === 'boolean') {
                    $valueText = ($val == '1' || strtolower($val) == 'true') ? '🟢 روشن' : '🔴 خاموش';
                } elseif ($setting->type === 'number' && $setting->key === 'shipping_cost') {
                    $valueText = empty($val) ? 'رایگان' : number_format((int) $val) . ' تومان';
                } else {
                    if (empty($val)) {
                        $valueText = '<i>تنظیم نشده</i>';
                    } else {
                        // اگر متن خیلی طولانی بود، خلاصه‌اش می‌کنیم تا ظاهر کار تمیز بماند
                        $cleanVal = strip_tags(html_entity_decode($val));
                        $valueText = mb_strlen($cleanVal) > 60 ? mb_substr($cleanVal, 0, 60) . '...' : $cleanVal;
                    }
                }

                // 📐 چیدمان ساختاریافته: تایتل بالا، مقدار در زیر با فونت یا استایل متمایز
                $html .= "{$icon} <b>{$nameText}</b> <br>";

                if ($setting->type === 'boolean' || $setting->type === 'number') {
                    $html .= "<code>{$valueText}</code><br>";
                } else {
                    $html .= "<i>{$valueText}</i><br>";
                }

                // دکمه‌های کنترل درون‌متنی
                $html .= "<tg-button-row align=\"center\">";
                $html .= "<tg-button type=\"callback_data\" style=\"primary\" data=\"admin_set_edit_{$setting->id}\">✏️ ویرایش مقدار</tg-button>";
                $html .= "<tg-button type=\"callback_data\" data=\"admin_set_info_{$setting->id}\">ℹ️ راهنما</tg-button>";
                $html .= "</tg-button-row><br>";
            }

            $html .= "</details><br>";
        }

        // دکمه بازگشت نهایی
        $html .= "<tg-button-row align=\"center\">";
        $html .= "<tg-button type=\"callback_data\" style=\"danger\" data=\"admin_panel\">🔙 بازگشت به پنل مدیریت</tg-button>";
        $html .= "</tg-button-row>";

        // پاک کردن پیام قبلی برای رندر بی‌نقص
        if ($this->messageId) {
            $this->deleteMessage($this->messageId);
        }

        // ارسال از طریق اندپوینت Rich Message
        http::post("https://api.telegram.org/bot{$this->bot->bot_token}/sendRichMessage", [
            'chat_id' => $this->chatId,
            'rich_message' => [
                'html' => $html
            ],
            'disable_notification' => true
        ]);
    }
    protected function showSettingInfo($settingId)
    {
        $setting = $this->bot->settings()->find($settingId);
        if (!$setting)
            return;

        // 📝 آرایه توضیحات راهنما بر اساس کلید تنظیمات (میتوانید متن‌ها را به دلخواه پر یا ویرایش کنید)
        $guides = [
            'store_name' => "نام فروشگاه شما که در پیام‌های سیستمی و جاهای مختلف ربات نمایش داده می‌شود. (مثلاً: پوشاک شیک‌پوشان)",

            'is_active' => "اگر این گزینه را خاموش کنید، ربات برای همه کاربران غیرفعال می‌شود و مشتریان نمی‌توانند محصولی را ببینند یا خرید کنند. (مناسب برای زمان انبارگردانی یا تعطیلات)",

            'welcome_message' => "پیامی که مشتری بلافاصله بعد از زدن دکمه Start می‌بیند. می‌توانید از متغیر {name} برای صدا زدن اسم مشتری و از {bot_name} برای نمایش نام فروشگاه استفاده کنید.",

            'shipping_cost' => "هزینه ثابت ارسال سفارشات (به تومان). این مبلغ در هنگام نهایی کردن سبد خرید، به صورت خودکار به فاکتور مشتری اضافه می‌شود.",

            'channel_id' => "آیدی کانال تلگرام فروشگاه (مثال: @my_store). از این آیدی برای فوروارد کردن محصولات یا بررسی عضویت اجباری (در صورت نیاز) استفاده می‌شود.",

            'show_discounted_products' => "با روشن کردن این گزینه، یک دکمه جذاب به نام «🔥 محصولات تخفیف‌دار» به منوی اصلی ربات اضافه می‌شود تا پیشنهادات ویژه شما بیشتر دیده شوند.",

            'support_text' => "متن راهنمایی که در صفحه پشتیبانی به مشتری نمایش داده می‌شود. می‌توانید ساعات کاری یا قوانین پاسخگویی فروشگاه را در اینجا بنویسید.",

            'support_telegram_id' => "آیدی تلگرام اکانت پشتیبان (بدون @). با پر کردن این فیلد، یک دکمه شیشه‌ای ساخته می‌شود تا مشتری با یک کلیک مستقیماً وارد چت خصوصی پشتیبان شود.",

            'support_phone' => "شماره تماس فروشگاه جهت پاسخگویی تلفنی به مشتریان. (مثلاً: 09123456789 یا 02188776655)",
        ];

        // بررسی وجود توضیحات در آرایه بالا، در غیر این صورت یک متن پیش‌فرض قرار می‌گیرد
        $guideText = $guides[$setting->key] ?? "راهنمایی برای این گزینه ثبت نشده است.";

        // ساخت بدنه پیام خلاصه شده برای رعایت محدودیت ۲۰۰ کاراکتر تلگرام
        $info = "💡 راهنما: {$guideText}\n";

        // پاپ‌آپ کردن اطلاعات روی صفحه بدون ارسال پیام جدید
        $this->sendRequest('answerCallbackQuery', [
            'callback_query_id' => $this->update['callback_query']['id'],
            'text' => mb_substr($info, 0, 190), // برش هوشمند برای جلوگیری از کرش در صورت طولانی شدن مقدار فعلی
            'show_alert' => true
        ]);
    }

    protected function editSettingPrompt($settingId)
    {
        $setting = $this->bot->settings()->find($settingId);
        if (!$setting)
            return;

        // 💡 جادوی UX: اگر نوع تنظیم روشن/خاموش است، اصلاً منتظر تایپ نمی‌مانیم!
        if ($setting->type === 'boolean') {
            $currentVal = ($setting->value == '1' || strtolower($setting->value) == 'true');
            $newValue = $currentVal ? '0' : '1';

            // 🛑 بررسی امنیتی اختصاصی برای عضویت اجباری (force_join)
            if ($setting->key === 'force_join' && $newValue === '1') {
                $channelSetting = $this->bot->settings()->where('key', 'channel_id')->first();
                $channelId = $channelSetting ? $channelSetting->value : null;

                if (empty($channelId)) {
                    $this->sendRequest('answerCallbackQuery', [
                        'callback_query_id' => $this->update['callback_query']['id'],
                        'text' => "❌ خطا: برای روشن کردن عضویت اجباری، ابتدا باید «آیدی کانال تلگرام» را در تنظیمات ثبت کنید.",
                        'show_alert' => true // نمایش پنجره اخطار وسط صفحه تلگرام
                    ]);
                    return;
                }

                $chatId = str_starts_with($channelId, '@') ? $channelId : '@' . $channelId;
                $botId = explode(':', $this->bot->bot_token)[0];

                $response = http::timeout(5)->get("https://api.telegram.org/bot{$this->bot->bot_token}/getChatMember", [
                    'chat_id' => $chatId,
                    'user_id' => $botId
                ]);

                $resData = $response->json();

                if (!$response->successful() || empty($resData['ok'])) {
                    $this->sendRequest('answerCallbackQuery', [
                        'callback_query_id' => $this->update['callback_query']['id'],
                        'text' => "❌ خطا: ربات در کانال تنظیم شده ({$chatId}) یافت نشد!",
                        'show_alert' => true
                    ]);
                    return;
                }

                $status = $resData['result']['status'] ?? '';
                if (!in_array($status, ['administrator', 'creator'])) {
                    $this->sendRequest('answerCallbackQuery', [
                        'callback_query_id' => $this->update['callback_query']['id'],
                        'text' => "⚠️ خطا: ربات در کانال ادمین نیست! ابتدا ربات را در کانال ادمین کنید.",
                        'show_alert' => true
                    ]);
                    return;
                }
            }

            $setting->value = $newValue;
            $isSaved = $setting->save();

            Cache::forget("bot_config:{$this->bot->webhook_secret}");

            $this->sendRequest('answerCallbackQuery', [
                'callback_query_id' => $this->update['callback_query']['id'],
                'text' => $isSaved ? "✅ وضعیت تغییر کرد." : "❌ خطا در ذخیره تنظیمات!",
                'show_alert' => !$isSaved
            ]);

            // بلافاصله صفحه رفرش می‌شود
            $this->showAdminSettings();
            return;
        }

        // برای تنظیمات متنی و عددی (هدایت ربات به وضعیت انتظار برای تایپ کاربر)
        $this->setSession('state', "admin_wait_setting_{$setting->id}");
        $promptMessageId = $this->update['callback_query']['message']['message_id'] ?? null;
        if ($promptMessageId) {
            $this->setSession('message_id', $promptMessageId);
        }

        $typeLabel = match ($setting->type) {
            'number' => 'عدد',
            'text' => 'متن طولانی',
            default => 'متن کوتاه'
        };

        $text = "✏️ <b>ویرایش تنظیم: {$setting->description}</b>\n\n";
        $text .= "مقدار فعلی: <code>" . ($setting->value ?: "تنظیم نشده") . "</code>\n\n";
        $text .= "لطفاً <b>مقدار جدید</b> را (در قالب {$typeLabel}) تایپ و ارسال کنید:\n";
        $text .= "<i>(برای خالی کردن این تنظیم، کلمه <code>حذف</code> را ارسال کنید)</i>";

        $keyboard = [
            [['text' => '🔙 لغو و بازگشت', 'callback_data' => 'admin_store_settings']]
        ];

        $this->editMessageText($text, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    protected function processAdminSettingEdit($state)
    {
        $settingId = str_replace('admin_wait_setting_', '', $state);
        $setting = $this->bot->settings()->find($settingId);

        if (!$setting)
            return;

        $promptMessageId = $this->getSession('message_id');

        // پاک کردن پیام متنی ادمین
        if ($this->messageId && !isset($this->update['callback_query'])) {
            $this->deleteMessage($this->messageId);
        }

        $input = trim($this->text);

        // امکان حذف مقدار توسط ادمین
        if ($input === 'حذف') {
            $setting->value = null;
            $setting->save();
        } else {

            // 🛡 ۱. ولیدیشن‌های عمومی (مانند چک کردن عدد)
            if ($setting->type === 'number') {
                $cleanNumber = str_replace([',', '،', ' '], '', $input);
                if (!is_numeric($cleanNumber)) {
                    $this->sendMessage("❌ خطا: لطفاً برای این تنظیم فقط <b>عدد</b> ارسال کنید!");
                    return; // توقف فرآیند تا زمانی که کاربر عدد بفرستد
                }
                $input = $cleanNumber;
            }

            // 🛡 ۲. ولیدیشن‌های اختصاصی (بر اساس key تنظیمات)
            switch ($setting->key) {
                case 'channel_id':
                    // ۱. پاکسازی کامل لینک (پشتیبانی از t.me/, https://t.me/, http://t.me/ و حالت معمولی)
                    $input = str_replace(['https://t.me/', 'http://t.me/', 't.me/'], '', $input);
                    $input = trim($input, '/');

                    if (!str_starts_with($input, '@') && !str_starts_with($input, '-100')) {
                        $input = '@' . $input;
                    }

                    $botToken = $this->bot->bot_token;
                    $botId = explode(':', $botToken)[0];

                    // ۲. مرحله اول: بررسی اینکه آیا کانال اصلاً وجود دارد یا خیر (توسط getChat)
                    $chatResponse = http::timeout(5)->get("https://api.telegram.org/bot{$botToken}/getChat", [
                        'chat_id' => $input
                    ]);
                    $chatData = $chatResponse->json();

                    if (!$chatResponse->successful() || empty($chatData['ok'])) {
                        $this->sendMessage("❌ <b>کانال یافت نشد!</b>\n\n" .
                            "کانالی با مشخصات <code>{$input}</code> در تلگرام وجود ندارد یا ربات نمی‌تواند آن را پیدا کند.\n\n" .
                            "لطفاً آیدی یا لینک کانال را به درستی بررسی و دوباره ارسال کنید.");
                        return;
                    }

                    // ۳. مرحله دوم: بررسی اینکه آیا ربات در کانال ادمین است یا خیر (توسط getChatMember)
                    $memberResponse = http::timeout(5)->get("https://api.telegram.org/bot{$botToken}/getChatMember", [
                        'chat_id' => $input,
                        'user_id' => $botId
                    ]);
                    $memberData = $memberResponse->json();

                    $status = $memberData['result']['status'] ?? '';

                    // اگر ربات اصلاً عضو کانال نباشد یا وضعیتش ادمین/سازنده نباشد
                    if (!$memberResponse->successful() || empty($memberData['ok']) || !in_array($status, ['administrator', 'creator'])) {
                        $this->sendMessage("⚠️ <b>خطای دسترسی (ربات ادمین نیست)!</b>\n\n" .
                            "کانال مورد نظر یافت شد، اما <b>ربات در آن دسترسی مدیریت (Admin) ندارد</b>.\n\n" .
                            "برای استفاده از امکانات کانال، ابتدا ربات را به عنوان ادمین به کانال اضافه کنید و سپس دوباره آیدی را بفرستید.");
                        return;
                    }

                    // اگر همه چیز درست بود، آیدی استاندارد شده ذخیره می‌شود
                    $setting->value = $input;
                    break;

                default:
                    $setting->value = $input;
                    break;
            }

            $setting->save();
        }

        // 🚀 باطل کردن کش تنظیمات ربات
        \Illuminate\Support\Facades\Cache::forget("bot_config:{$this->bot->webhook_secret}");

        // پاکسازی وضعیت ربات از سشن
        $this->setSession('state', null);
        $this->setSession('message_id', null);

        // هدایت خودکار ربات به منوی تنظیمات و رفرش شدن شیشه‌ای
        if ($promptMessageId) {
            $this->messageId = $promptMessageId;
            $this->update['callback_query'] = ['message' => ['message_id' => $promptMessageId]];
        }

        $this->showAdminSettings();
    }
    /**
     * 🎁 نمایش ویترین محصولات تخفیف‌دار (نسخه مینیمال و شیک با کلاژ)
     */
    protected function showDiscountedProducts($page = 1)
    {
        $limit = 10;
        $products = \App\Models\Product::where('bot_id', $this->bot->id)
            ->where('discount_price', '>', 0)
            ->where(function ($q) {
                $q->whereNull('discount_expires_at')
                    ->orWhere('discount_expires_at', '>', now());
            })
            ->latest()
            ->paginate($limit, ['*'], 'page', $page);

        if ($products->isEmpty()) {
            $this->sendRequest('answerCallbackQuery', [
                'callback_query_id' => $this->update['callback_query']['id'],
                'text' => "در حال حاضر محصول تخفیف‌داری وجود ندارد.",
                'show_alert' => true
            ]);
            return;
        }

        $html = "🎁 <b>پیشنهادات شگفت‌انگیز و تخفیف‌دار</b>\n\n";

        // ==========================================
        // 🖼 ساخت کلاژ تصاویر (حداکثر ۴ عکس از ۴ محصول اول)
        // ==========================================
        $collageImages = [];
        foreach ($products as $p) {
            $firstImg = $p->images()->orderBy('sort_order')->first();
            if ($firstImg) {
                $collageImages[] = $firstImg;
                if (count($collageImages) == 4)
                    break;
            }
        }

        $mediaArray = [];
        $request = http::timeout(30);
        $hasLocalFiles = false;

        if (count($collageImages) > 0) {
            $html .= "<tg-collage>\n";
            foreach ($collageImages as $idx => $img) {
                $mediaId = "img_discount_{$idx}";
                $html .= "<img src=\"tg://photo?id={$mediaId}\"/>\n";

                if (!empty($img->telegram_file_id)) {
                    $mediaArray[] = [
                        'id' => $mediaId,
                        'media' => ['type' => 'photo', 'media' => $img->telegram_file_id]
                    ];
                } else {
                    $absolutePath = storage_path('app/public/' . $img->file_id);
                    if (file_exists($absolutePath)) {
                        $mediaArray[] = [
                            'id' => $mediaId,
                            'media' => ['type' => 'photo', 'media' => "attach://{$mediaId}"]
                        ];
                        $request->attach($mediaId, file_get_contents($absolutePath), "photo{$idx}.webp");
                        $hasLocalFiles = true;
                    }
                }
            }
            $html .= "</tg-collage>\n\n";
        }

        // یک متن راهنمای کوچک زیر کلاژ
        $html .= "👇 <i>برای مشاهده جزئیات پیشنهاد، روی محصول مورد نظر کلیک کنید:</i>";

        // ==========================================
        // 📝 ساخت دکمه‌های محصولات (طراحی مینیمال حاوی نام و قیمت تخفیف‌خورده)
        // ==========================================
        $keyboard = [];
        foreach ($products as $product) {
            // نمایش قیمت تخفیف خورده در دکمه
            $discountPrice = number_format($product->discount_price);

            // کوتاه کردن نام برای جلوگیری از به هم ریختن دکمه در موبایل
            $shortName = mb_substr($product->name, 0, 22) . (mb_strlen($product->name) > 22 ? '...' : '');

            $keyboard[] = [
                ['text' => "🎁 {$shortName} | 🔥 {$discountPrice} T", 'callback_data' => "show_prod_{$product->id}"]
            ];
        }

        // ==========================================
        // 🎛 دکمه‌های صفحه‌بندی متقارن و زیبا
        // ==========================================
        $paginationRow = [];

        if ($page > 1) {
            $paginationRow[] = ['text' => '◀️ قبلی', 'callback_data' => 'discounted_prods_' . ($page - 1)];
        }

        // نمایش شماره صفحه در وسط (غیرقابل کلیک)
        $paginationRow[] = ['text' => "📄 صفحه {$page}", 'callback_data' => 'ignore_cb'];

        if ($products->hasMorePages()) {
            $paginationRow[] = ['text' => 'بعدی ▶️', 'callback_data' => 'discounted_prods_' . ($page + 1)];
        }

        $keyboard[] = $paginationRow;
        $keyboard[] = [['text' => '🏠 بازگشت به منوی اصلی', 'callback_data' => 'main_menu']];

        // 🧹 پاک کردن پیام قبلی
        if ($this->messageId) {
            $this->deleteMessage($this->messageId);
        }

        // 🚀 ارسال پیام غنی
        if ($hasLocalFiles) {
            $request->post("https://api.telegram.org/bot{$this->bot->bot_token}/sendRichMessage", [
                'chat_id' => $this->chatId,
                'rich_message' => json_encode(['html' => $html, 'media' => $mediaArray]),
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
                'disable_notification' => true
            ]);
        } else {
            $payload = [
                'chat_id' => $this->chatId,
                'rich_message' => ['html' => $html],
                'reply_markup' => ['inline_keyboard' => $keyboard],
                'disable_notification' => true
            ];
            if (!empty($mediaArray)) {
                $payload['rich_message']['media'] = $mediaArray;
            }

            http::timeout(15)->post("https://api.telegram.org/bot{$this->bot->bot_token}/sendRichMessage", $payload);
        }
    }

    /**
     * تغییر وضعیت علاقه‌مندی بدون رفرش کل پیام (تعویض آیکون قلب)
     */
    protected function toggleFavorite($productId)
    {
        $favorite = \App\Models\Favorite::where('bot_id', $this->bot->id)
            ->where('user_id', $this->user->id)
            ->where('product_id', $productId)
            ->first();

        if ($favorite) {
            $favorite->delete();
            $msg = "💔 محصول از لیست علاقه‌مندی‌های شما حذف شد.";
        } else {
            \App\Models\Favorite::create([
                'bot_id' => $this->bot->id,
                'user_id' => $this->user->id,
                'product_id' => $productId
            ]);
            $msg = "❤️ محصول با موفقیت به علاقه‌مندی‌های شما اضافه شد.";
        }

        // یک پیام حبابی و سریع (Toast) به کاربر نشان می‌دهیم
        $this->sendRequest('answerCallbackQuery', [
            'callback_query_id' => $this->update['callback_query']['id'],
            'text' => $msg,
            'show_alert' => false
        ]);

        $product = Product::find($productId);
        if ($product) {
            // 💡 ترفند حفظ مسیر: پیدا کردن دکمه بازگشت قبلی از روی کیبورد فعلی برای اینکه کاربر مسیرش گم نشود
            $currentKeyboard = $this->update['callback_query']['message']['reply_markup']['inline_keyboard'] ?? [];
            $backButton = null;
            if (!empty($currentKeyboard)) {
                $lastRow = end($currentKeyboard);
                if (isset($lastRow[0]['text']) && str_contains($lastRow[0]['text'], 'بازگشت')) {
                    $backButton = $lastRow[0];
                }
            }

            // فقط دکمه‌های شیشه‌ای را مجدداً رندر می‌کنیم (بدون رفرش متن و عکس)
            $keyboard = \App\Services\TelegramProductService::generateKeyboard($product, false, $backButton, $this->user->id);
            $this->sendRequest('editMessageReplyMarkup', [
                'chat_id' => $this->chatId,
                'message_id' => $this->messageId,
                'reply_markup' => json_encode($keyboard)
            ]);
        }
    }

    /**
     * نمایش لیست محصولات علاقه‌مندی کاربر (کالکشن شخصی)
     */
    protected function showFavorites($page = 1)
    {
        $query = \App\Models\Favorite::where('bot_id', $this->bot->id)
            ->where('user_id', $this->user->id)
            ->with('product');

        $perPage = 8;
        $total = $query->count();
        $totalPages = (int) ceil($total / $perPage);

        if ($page < 1)
            $page = 1;
        if ($page > $totalPages && $totalPages > 0)
            $page = $totalPages;

        $favorites = $query->orderBy('created_at', 'desc')->skip(($page - 1) * $perPage)->take($perPage)->get();

        $text = "❤️ <b>لیست علاقه‌مندی‌های شما</b>\n\n";

        if ($favorites->isEmpty()) {
            $text .= "<i>شما هنوز هیچ محصولی را به علاقه‌مندی‌های خود اضافه نکرده‌اید!</i>\n";
            $text .= "برای اضافه کردن، در صفحه هر محصول روی دکمه «🤍 افزودن به علاقه‌مندی» کلیک کنید.";
        } else {
            $text .= "👇 <i>محصولات مورد علاقه شما:</i>\n";
        }

        $keyboard = [];

        foreach ($favorites as $favorite) {
            $product = $favorite->product;
            if (!$product)
                continue;

            $displayPrice = $product->price;
            $discountLabel = "";
            if (!empty($product->discount_price) && (empty($product->discount_expires_at) || \Carbon\Carbon::parse($product->discount_expires_at)->isFuture())) {
                $displayPrice = $product->discount_price;
                $discountLabel = " 🔥";
            }

            $priceFormatted = number_format($displayPrice) . " تومان";
            $buttonText = "{$product->name} | {$priceFormatted}{$discountLabel}";

            if ($product->stock !== null && $product->stock <= 0) {
                $buttonText = "🚫 {$product->name} | ناموجود";
            }

            $keyboard[] = [
                ['text' => $buttonText, 'callback_data' => "show_prod_{$product->id}"]
            ];
        }

        // صفحه‌بندی
        if ($totalPages > 1) {
            $pageControls = [];
            if ($page > 1)
                $pageControls[] = ['text' => '«', 'callback_data' => "fav_page_" . ($page - 1)];
            $pageControls[] = ['text' => "📄 {$page} از {$totalPages}", 'callback_data' => "ignore_cb"];
            if ($page < $totalPages)
                $pageControls[] = ['text' => '»', 'callback_data' => "fav_page_" . ($page + 1)];
            $keyboard[] = $pageControls;
        }

        $keyboard[] = [['text' => '🏠 بازگشت به منوی اصلی', 'callback_data' => 'main_menu']];

        $message = $this->update['callback_query']['message'] ?? [];
        $hasMedia = isset($message['photo']) || isset($message['video']) || isset($message['document']) || isset($message['animation']);

        if ($hasMedia) {
            $this->deleteMessage($this->messageId);
            $this->sendRequest('sendMessage', [
                'chat_id' => $this->chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
            ]);
        } else {
            $this->editMessageText($text, ['reply_markup' => json_encode(['inline_keyboard' => $keyboard])]);
        }
    }

    /**
     * نمایش گالری تصاویر محصول (با استفاده از قابلیت انقلابی اسلایدشو Bot API 10.3)
     */
    protected function showProductGallery($productId, $index = 0)
    {
        // 🛡 فیلتر امنیتی
        $product = \App\Models\Product::where('bot_id', $this->bot->id)->find($productId);
        if (!$product)
            return;

        // دریافت تمام تصاویر به ترتیب
        $images = $product->images()->orderBy('sort_order')->get();
        $total = $images->count();

        if ($total <= 1) {
            $this->sendRequest('answerCallbackQuery', [
                'callback_query_id' => $this->update['callback_query']['id'],
                'text' => "تصویر دیگری برای این محصول وجود ندارد.",
                'show_alert' => false
            ]);
            return;
        }

        // 🚀 ۱. ساخت بدنه HTML پیام غنی (Rich Message) با تگ جدید tg-slideshow
        $html = "🖼 <b>گالری تصاویر:</b> {$product->name}\n\n";
        $html .= "<tg-slideshow>";

        $mediaArray = [];
        $request = http::timeout(30);
        $hasLocalFiles = false;

        foreach ($images as $idx => $img) {
            $mediaId = "img_{$idx}";

            // اضافه کردن عکس به داخل تگ اسلایدشو
            $html .= "<img src=\"tg://photo?id={$mediaId}\"/>";

            if (!empty($img->telegram_file_id)) {
                // 🚀 مسیر سریع: فایل قبلاً روی سرور تلگرام آپلود شده است
                $mediaArray[] = [
                    'id' => $mediaId,
                    'media' => [
                        'type' => 'photo',
                        'media' => $img->telegram_file_id
                    ]
                ];
            } else {
                // مسیر آپلود: فایل روی هارد سرور ماست و باید ضمیمه شود
                $absolutePath = storage_path('app/public/' . $img->file_id);
                if (file_exists($absolutePath)) {
                    $mediaArray[] = [
                        'id' => $mediaId,
                        'media' => [
                            'type' => 'photo',
                            'media' => "attach://{$mediaId}"
                        ]
                    ];
                    // چسباندن فایل به درخواست با نام داینامیک
                    $request->attach($mediaId, file_get_contents($absolutePath), "photo{$idx}.webp");
                    $hasLocalFiles = true;
                }
            }
        }

        $html .= "</tg-slideshow>\n\n";
        $html .= "<i>(برای مشاهده عکس‌ها، آن‌ها را به چپ و راست بکشید 👆)</i>";

        // دکمه بازگشت به توضیحات اصلی محصول
        $keyboard = [
            [['text' => '🔙 بازگشت به توضیحات محصول', 'callback_data' => "show_prod_{$productId}"]]
        ];

        // 🧹 ۲. پاک کردن پیام قبلی (چون تلگرام پیام‌های عادی را به Rich Message تبدیل نمی‌کند و باید از نو ارسال شود)
        $this->deleteMessage($this->messageId);

        // 🚀 ۳. ارسال ریکوئست به اندپوینت جدید sendRichMessage
        if ($hasLocalFiles) {
            // حالت ارسال فایل‌های جدید (Multipart)
            $response = $request->post("https://api.telegram.org/bot{$this->bot->bot_token}/sendRichMessage", [
                'chat_id' => $this->chatId,
                'rich_message' => json_encode([
                    'html' => $html,
                    'media' => $mediaArray
                ]),
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
            ]);
        } else {
            // حالت فوق‌سریع (تمام فایل‌ها آیدی تلگرامی دارند)
            $response = http::timeout(15)->post("https://api.telegram.org/bot{$this->bot->bot_token}/sendRichMessage", [
                'chat_id' => $this->chatId,
                'rich_message' => [
                    'html' => $html,
                    'media' => $mediaArray
                ],
                'reply_markup' => ['inline_keyboard' => $keyboard]
            ]);
        }

        $result = $response->json();

        // ⚠️ مدیریت خطا
        if (!$response->successful() || empty($result['ok'])) {
            \Illuminate\Support\Facades\Log::error("RichMessage Gallery Error: " . json_encode($result));
            $this->sendRequest('answerCallbackQuery', [
                'callback_query_id' => $this->update['callback_query']['id'],
                'text' => "⚠️ گالری موقتاً در دسترس نیست.",
                'show_alert' => true
            ]);
        }
    }
    /**
     * 🌟 نمایش ویترین جدیدترین محصولات (نسخه مینیمال و شیک با کلاژ)
     */
    protected function showLatestProducts($page = 1)
    {
        $limit = 10;
        $products = \App\Models\Product::where('bot_id', $this->bot->id)
            ->latest()
            ->paginate($limit, ['*'], 'page', $page);

        if ($products->isEmpty()) {
            $this->sendRequest('answerCallbackQuery', [
                'callback_query_id' => $this->update['callback_query']['id'],
                'text' => "محصولی یافت نشد.",
                'show_alert' => true
            ]);
            return;
        }

        $html = "🌟 <b>جدیدترین محصولات فروشگاه</b>\n\n";

        // ==========================================
        // 🖼 ساخت کلاژ تصاویر (حداکثر ۴ عکس از ۴ محصول اول)
        // ==========================================
        $collageImages = [];
        foreach ($products as $p) {
            $firstImg = $p->images()->orderBy('sort_order')->first();
            if ($firstImg) {
                $collageImages[] = $firstImg;
                if (count($collageImages) == 4)
                    break;
            }
        }

        $mediaArray = [];
        $request = http::timeout(30);
        $hasLocalFiles = false;

        if (count($collageImages) > 0) {
            $html .= "<tg-collage>\n";
            foreach ($collageImages as $idx => $img) {
                $mediaId = "img_latest_{$idx}";
                $html .= "<img src=\"tg://photo?id={$mediaId}\"/>\n";

                if (!empty($img->telegram_file_id)) {
                    $mediaArray[] = [
                        'id' => $mediaId,
                        'media' => ['type' => 'photo', 'media' => $img->telegram_file_id]
                    ];
                } else {
                    $absolutePath = storage_path('app/public/' . $img->file_id);
                    if (file_exists($absolutePath)) {
                        $mediaArray[] = [
                            'id' => $mediaId,
                            'media' => ['type' => 'photo', 'media' => "attach://{$mediaId}"]
                        ];
                        $request->attach($mediaId, file_get_contents($absolutePath), "photo{$idx}.webp");
                        $hasLocalFiles = true;
                    }
                }
            }
            $html .= "</tg-collage>\n\n";
        }

        // یک متن راهنمای کوچک زیر کلاژ
        $html .= "👇 <i>برای مشاهده جزئیات و خرید، روی محصول مورد نظر کلیک کنید:</i>";

        // ==========================================
        // 📝 ساخت دکمه‌های محصولات (طراحی مینیمال حاوی نام و قیمت)
        // ==========================================
        $keyboard = [];
        foreach ($products as $product) {
            $price = number_format($product->price);

            // کوتاه کردن نام برای جلوگیری از به هم ریختن دکمه در موبایل
            $shortName = mb_substr($product->name, 0, 22) . (mb_strlen($product->name) > 22 ? '...' : '');

            $keyboard[] = [
                ['text' => "🛍 {$shortName} | 💰 {$price} T", 'callback_data' => "show_prod_{$product->id}"]
            ];
        }

        // ==========================================
        // 🎛 دکمه‌های صفحه‌بندی متقارن و زیبا
        // ==========================================
        $paginationRow = [];

        if ($page > 1) {
            $paginationRow[] = ['text' => '◀️ قبلی', 'callback_data' => 'latest_prods_' . ($page - 1)];
        }

        // نمایش شماره صفحه در وسط (غیرقابل کلیک)
        $paginationRow[] = ['text' => "📄 صفحه {$page}", 'callback_data' => 'ignore_cb'];

        if ($products->hasMorePages()) {
            $paginationRow[] = ['text' => 'بعدی ▶️', 'callback_data' => 'latest_prods_' . ($page + 1)];
        }

        $keyboard[] = $paginationRow;
        $keyboard[] = [['text' => '🏠 بازگشت به منوی اصلی', 'callback_data' => 'main_menu']];

        // 🧹 پاک کردن پیام قبلی
        if ($this->messageId) {
            $this->deleteMessage($this->messageId);
        }

        // 🚀 ارسال پیام غنی
        if ($hasLocalFiles) {
            $request->post("https://api.telegram.org/bot{$this->bot->bot_token}/sendRichMessage", [
                'chat_id' => $this->chatId,
                'rich_message' => json_encode(['html' => $html, 'media' => $mediaArray]),
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
                'disable_notification' => true
            ]);
        } else {
            $payload = [
                'chat_id' => $this->chatId,
                'rich_message' => ['html' => $html],
                'reply_markup' => ['inline_keyboard' => $keyboard],
                'disable_notification' => true
            ];
            if (!empty($mediaArray)) {
                $payload['rich_message']['media'] = $mediaArray;
            }

            http::timeout(15)->post("https://api.telegram.org/bot{$this->bot->bot_token}/sendRichMessage", $payload);
        }
    }
    /**
     * نمایش اطلاعات پشتیبانی فروشگاه (همراه با قابلیت کپی سریع)
     */
    protected function showSupportInfo()
    {
        // دریافت تنظیمات از دیتابیس
        $supportText = $this->bot->settings->firstWhere('key', 'support_text')?->value
            ?? "🎧 <b>پشتیبانی مشتریان</b>\n\nکاربر گرامی، برای پیگیری سفارشات، مشاوره یا طرح سوالات خود می‌توانید از طریق راه‌های ارتباطی زیر با ما در تماس باشید:";

        $telegramId = $this->bot->settings->firstWhere('key', 'support_telegram_id')?->value;
        $phone = $this->bot->settings->firstWhere('key', 'support_phone')?->value;

        $text = $supportText . "\n\n";

        $keyboard = [];

        // اگر آیدی تلگرام ثبت شده بود
        if (!empty($telegramId)) {
            // حذف @ در صورت وجود تا لینک به درستی کار کند
            $cleanId = ltrim($telegramId, '@');
            $text .= "💬 <b>آیدی تلگرام:</b> @{$cleanId}\n";

            // دکمه اتصال مستقیم به پی‌وی پشتیبان
            $keyboard[] = [
                ['text' => '💬 ارسال پیام به پشتیبانی', 'url' => "https://t.me/{$cleanId}"]
            ];
        }

        // اگر شماره تماس ثبت شده بود
        if (!empty($phone)) {
            $text .= "📞 <b>شماره تماس:</b> <code>{$phone}</code>\n";

            // 🚀 اضافه شدن دکمه کپی سریع برای شماره تلفن
            $keyboard[] = [
                ['text' => '📋 کپی شماره تماس', 'copy_text' => ['text' => $phone]]
            ];
        }

        // اگر هیچ راه ارتباطی ثبت نشده بود
        if (empty($telegramId) && empty($phone)) {
            $text .= "<i>(راه‌های ارتباطی هنوز توسط مدیریت فروشگاه تکمیل نشده است)</i>";
        }

        // دکمه بازگشت به منوی اصلی
        $keyboard[] = [
            ['text' => '🏠 بازگشت به منوی اصلی', 'callback_data' => 'main_menu']
        ];

        // مدیریت رندر پیام (جلوگیری از خطای پرش عکس)
        $message = $this->update['callback_query']['message'] ?? [];
        $hasMedia = isset($message['photo']) || isset($message['video']) || isset($message['document']) || isset($message['animation']);

        if ($hasMedia) {
            $this->deleteMessage($this->messageId);
            $this->sendMessage($text, [
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
                'parse_mode' => 'HTML'
            ]);
        } else {
            $this->editMessageText($text, [
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
                'parse_mode' => 'HTML'
            ]);
        }
    }

    /**
     * نمایش آمار و جزئیات محصول به صورت پاپ‌آپ (فقط برای ادمین)
     */
    protected function showProductStats($productId)
    {
        // 🛡 لایه امنیتی: فقط ادمین حق دیدن این آمار را دارد
        if ($this->user->is_admin != 1) {
            return;
        }

        $product = \App\Models\Product::find($productId);
        if (!$product)
            return;

        // محاسبه تعداد کاربرانی که این کالا هم‌اکنون در سبد خریدشان است
        $cartsUserCount = \App\Models\Cart::where('bot_id', $this->bot->id)
            ->where('product_id', $productId)
            ->count();

        // قالب‌بندی مقادیر
        $stock = $product->stock !== null ? $product->stock . ' عدد' : 'نامحدود';
        $purchasePrice = $product->purchase_price ? number_format($product->purchase_price) . ' ت' : 'ثبت نشده';

        // 📝 تولید متن پاپ‌آپ (حداکثر طول مجاز برای تلگرام ۲۰۰ کاراکتر است)
        $text = "📊 آمار: {$product->name}\n\n";
        $text .= "👁 بازدید: {$product->views_count} نفر\n";
        $text .= "🛍 فروش موفق: {$product->sales_count} عدد\n";
        $text .= "🛒 در سبد خرید: {$cartsUserCount} نفر\n";
        $text .= "📦 موجودی انبار: {$stock}\n";
        $text .= "💰 قیمت خرید (سود): {$purchasePrice}\n";
        $text .= "⭐️ امتیاز: {$product->average_rating} (از {$product->review_count} رای)";

        // نمایش پاپ‌آپ در مرکز صفحه نمایش کاربر (Show Alert)
        $this->sendRequest('answerCallbackQuery', [
            'callback_query_id' => $this->update['callback_query']['id'],
            'text' => $text,
            'show_alert' => true // این گزینه باعث می‌شود پیام وسط صفحه پاپ‌آپ شود
        ]);
    }

    /**
     * نمایش لیست سفارشات به ادمین (با صفحه‌بندی)
     */
    protected function showAdminOrdersList($page = 1)
    {
        $query = \App\Models\Invoice::where('bot_id', $this->bot->id)->orderBy('created_at', 'desc');

        $perPage = 8;
        $totalOrders = $query->count();
        $totalPages = (int) ceil($totalOrders / $perPage);

        if ($page < 1)
            $page = 1;
        if ($page > $totalPages && $totalPages > 0)
            $page = $totalPages;

        // لود کردن کاربر برای نمایش نام مشتری در لیست
        $orders = $query->with('user')->skip(($page - 1) * $perPage)->take($perPage)->get();

        $text = "📦 <b>پنل مدیریت سفارشات</b>\n\n";

        if ($orders->isEmpty()) {
            $text .= "<i>هیچ سفارشی در سیستم ثبت نشده است.</i>";
        } else {
            $text .= "👇 برای مشاهده جزئیات و تغییر وضعیت، روی هر سفارش کلیک کنید:";
        }

        $keyboard = [];

        foreach ($orders as $order) {
            // 💡 استفاده از متد قدرتمند خودتان برای آیکون‌ها
            $statusIcon = $this->getStatusIcon($order->status);

            // 💡 استخراج مبلغ از فیلد صحیح فاکتور
            $price = number_format($order->final_amount ?? $order->total_amount ?? 0) . " ت";

            // نمایش نام کاربر
            $buyerName = $order->user ? ($order->user->first_name ?? 'کاربر') : 'نامشخص';

            $buttonText = "{$statusIcon} | {$buyerName} | {$price}";

            // 💡 اتصال دکمه به مسیر صحیح (admin_view_order_)
            $keyboard[] = [
                ['text' => $buttonText, 'callback_data' => "admin_view_order_{$order->id}"]
            ];
        }

        // صفحه‌بندی
        if ($totalPages > 1) {
            $pageControls = [];
            if ($page > 1)
                $pageControls[] = ['text' => '« قبلی', 'callback_data' => "admin_orders_page_" . ($page - 1)];
            $pageControls[] = ['text' => "📄 {$page} از {$totalPages}", 'callback_data' => "ignore_cb"];
            if ($page < $totalPages)
                $pageControls[] = ['text' => 'بعدی »', 'callback_data' => "admin_orders_page_" . ($page + 1)];
            $keyboard[] = $pageControls;
        }

        $keyboard[] = [['text' => '🔙 بازگشت به داشبورد', 'callback_data' => 'admin_panel']];

        $this->editMessageText($text, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
            'parse_mode' => 'HTML'
        ]);
    }
    /**
     * نمایش جزئیات دقیق یک سفارش به ادمین
     */
    protected function showAdminOrderDetail($orderId)
    {
        // لود کردن سفارش به همراه کاربر و آیتم‌های خریداری شده
        $order = \App\Models\Order::with(['user', 'items.product'])->find($orderId);

        if (!$order) {
            $this->sendRequest('answerCallbackQuery', ['callback_query_id' => $this->update['callback_query']['id'], 'text' => "❌ سفارش یافت نشد", 'show_alert' => true]);
            return;
        }

        $statusText = match ($order->status) {
            'pending' => '🔴 بررسی نشده (جدید)',
            'processing' => '🟡 تایید شده (در حال آماده‌سازی)',
            'completed' => '🟢 تکمیل و ارسال شده',
            'cancelled' => '⚫️ لغو شده',
            default => $order->status
        };

        $date = \Morilog\Jalali\Jalalian::fromDateTime($order->created_at)->format('Y/m/d ساعت H:i');

        $text = "🧾 <b>جزئیات سفارش شماره #{$order->id}</b>\n";
        $text .= "➖➖➖➖➖➖➖➖➖➖\n";
        $text .= "👤 <b>مشتری:</b> " . ($order->user->first_name ?? 'بدون نام') . " (@" . ($order->user->username ?? 'ندارد') . ")\n";
        $text .= "🗓 <b>تاریخ ثبت:</b> {$date}\n";
        $text .= "🚦 <b>وضعیت فعلی:</b> {$statusText}\n";
        $text .= "➖➖➖➖➖➖➖➖➖➖\n";
        $text .= "🛒 <b>سبد خرید:</b>\n";

        // لیست کردن اقلام خریداری شده
        foreach ($order->items as $item) {
            $productName = $item->product ? $item->product->name : 'محصول حذف شده';
            $itemPrice = number_format($item->price) . " ت";
            $text .= "🔹 {$productName} (تعداد: {$item->quantity}) - {$itemPrice}\n";
        }

        $text .= "➖➖➖➖➖➖➖➖➖➖\n";
        $text .= "🚚 <b>هزینه ارسال:</b> " . number_format($order->shipping_cost ?? 0) . " ت\n";
        $text .= "💰 <b>مبلغ کل پرداختی:</b> <b>" . number_format($order->total_price) . " تومان</b>\n\n";

        // اطلاعات تماس و آدرس
        if (!empty($order->address)) {
            $text .= "📍 <b>آدرس ارسال:</b>\n<code>{$order->address}</code>\n";
        }
        if (!empty($order->phone)) {
            $text .= "📞 <b>شماره تماس:</b> <code>{$order->phone}</code>\n";
        }

        // دکمه‌های تغییر وضعیت
        $keyboard = [];

        if ($order->status === 'pending') {
            $keyboard[] = [
                ['text' => '✅ تایید و شروع آماده‌سازی', 'callback_data' => "admin_order_status_{$order->id}_processing"]
            ];
        }

        if (in_array($order->status, ['pending', 'processing'])) {
            $keyboard[] = [
                ['text' => '🚚 تحویل/ارسال شد (تکمیل)', 'callback_data' => "admin_order_status_{$order->id}_completed"]
            ];
            $keyboard[] = [
                ['text' => '❌ لغو سفارش (عودت وجه)', 'callback_data' => "admin_order_status_{$order->id}_cancelled"]
            ];
        }

        $keyboard[] = [['text' => '🔙 بازگشت به لیست سفارشات', 'callback_data' => 'admin_manage_orders']];

        $this->editMessageText($text, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
            'parse_mode' => 'HTML'
        ]);
    }

    /**
     * تغییر وضعیت سفارش توسط ادمین و ارسال پیام به مشتری
     */
    protected function changeOrderStatus($orderId, $newStatus)
    {
        $order = \App\Models\Order::find($orderId);
        if (!$order)
            return;

        $order->status = $newStatus;
        $order->save();

        // پیام پاپ‌آپ موفقیت به ادمین
        $this->sendRequest('answerCallbackQuery', [
            'callback_query_id' => $this->update['callback_query']['id'],
            'text' => "✅ وضعیت سفارش با موفقیت به روز رسانی شد.",
            'show_alert' => false
        ]);

        // // 🚀 ارسال نوتیفیکیشن به مشتری
        // if ($order->user && $order->user->chat_id) {
        //     $buyerChatId = $order->user->chat_id;

        //     $userMsg = "🔔 <b>به روز رسانی سفارش شماره #{$order->id}</b>\n\n";
        //     $storeName = $this->bot->settings->firstWhere('key', 'store_name')?->value ?? $this->bot->bot_name;

        //     if ($newStatus === 'processing') {
        //         $userMsg .= "✅ مشتری عزیز، سفارش شما توسط فروشگاه <b>{$storeName}</b> تایید شد و هم‌اکنون در مرحله <b>آماده‌سازی و بسته‌بندی</b> قرار دارد.\n📦 به زودی جهت ارسال با شما هماهنگ خواهد شد.";
        //     } elseif ($newStatus === 'completed') {
        //         $userMsg .= "🎉 <b>سفارش شما ارسال شد!</b>\n\nمحصولات شما از طرف فروشگاه <b>{$storeName}</b> تحویل پست/پیک گردید. از خرید شما سپاسگزاریم! ❤️";
        //     } elseif ($newStatus === 'cancelled') {
        //         $userMsg .= "❌ <b>سفارش شما لغو شد!</b>\n\nمتاسفانه سفارش شما از سمت مدیریت فروشگاه <b>{$storeName}</b> لغو گردید. جهت پیگیری دلیل لغو یا عودت وجه با پشتیبانی در ارتباط باشید.";
        //     }

        //     // ارسال پیام مستقیم (Push Notification) به مشتری
        //     http::post("https://api.telegram.org/bot{$this->bot->bot_token}/sendMessage", [
        //         'chat_id' => $buyerChatId,
        //         'text' => $userMsg,
        //         'parse_mode' => 'HTML',
        //         'reply_markup' => json_encode([
        //             'inline_keyboard' => [[['text' => '🎧 تماس با پشتیبانی', 'callback_data' => 'support_info']]]
        //         ])
        //     ]);
        // }

        // رفرش کردن صفحه جزئیات سفارش برای ادمین تا وضعیت جدید را ببیند
        $this->showAdminOrderDetail($orderId);
    }



    protected function showAdminSubscription()
    {
        $pendingPayment = \App\Models\SubscriptionPayment::where('bot_id', $this->bot->id)
            ->where('status', 'awaiting_approval')
            ->first();

        if ($pendingPayment) {
            $text = "⏳ <b>درخواست شما در حال بررسی است</b>\n\n";
            $text .= "فیش واریزی شما برای پلن <b>{$pendingPayment->plan_name}</b> با موفقیت دریافت شده و به زودی توسط مدیریت بررسی خواهد شد.\n\n";
            $text .= "<i>لطفاً تا تعیین وضعیت منتظر بمانید...</i>";

            $keyboard = [
                [['text' => '🔙 بازگشت به پنل مدیریت', 'callback_data' => 'admin_panel']]
            ];

            $this->editMessageText($text, [
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
                'parse_mode' => 'HTML'
            ]);
            return;
        }

        $currentPlanSlug = data_get($this->bot, 'subscription_plan') ?? data_get($this->bot, 'plan_slug') ?? null;
        $expiresAt = data_get($this->bot, 'subscription_expires_at') ?? data_get($this->bot, 'plan_expires_at') ?? null;

        // پیدا کردن اطلاعات پلن فعلی از جدول plans
        $plan = \App\Models\Plan::where('slug', $currentPlanSlug)->first();
        $planName = $plan ? $plan->name : 'نامشخص';

        Log::info("Admin Subscription Info: json: " . json_encode([
            'currentPlanSlug' => $currentPlanSlug,
            'plan' => $plan ? $plan->toArray() : null,
            'expiresAt' => $expiresAt
        ]));

        $text = "💳 <b>وضعیت اشتراک فروشگاه شما</b>\n\n";
        $text .= "🔹 <b>پلن فعلی:</b> $planName\n";

        if ($expiresAt) {
            // تاریخ انقضا (میلادی برای محاسبه)
            $expireDate = \Carbon\Carbon::parse($expiresAt);

            // تبدیل به جلالی برای نمایش
            $jalaliExpire = \Morilog\Jalali\Jalalian::fromDateTime($expireDate);

            // بررسی انقضا
            if ($expireDate->isPast()) {
                $text .= "⚠️ <b>وضعیت:</b> ❗️ اشتراک شما منقضی شده است.\n";
                $text .= "📅 <b>تاریخ پایان:</b> " . $jalaliExpire->format('Y-m-d') . "\n";
            } else {
                // تعداد روز باقی‌مانده
                $daysLeft = (int) now()->diffInDays($expireDate, false);

                // هشدار نزدیک بودن پایان
                if ($daysLeft <= 3) {
                    $text .= "⚠️ <b>هشدار:</b> زمان اشتراک شما رو به پایان است.\n";
                } else {
                    $text .= "✅ <b>وضعیت:</b> فعال\n";
                }

                $text .= "⏳ <b>اعتبار باقی‌مانده:</b> {$daysLeft} روز\n";
                $text .= "📅 <b>تاریخ انقضا:</b> " . $jalaliExpire->format('Y-m-d') . "\n";
            }
        } else {
            $text .= "♾️ <b>وضعیت:</b> اشتراک دائمی\n";
        }

        $text .= "\n💡 برای ارتقا یا تمدید اشتراک خود، روی دکمه زیر کلیک کنید.";

        $keyboard = [
            [
                ['text' => '🛍 مشاهده و خرید پلن‌ها', 'callback_data' => 'admin_view_plans']
            ],
            [
                ['text' => '🔙 بازگشت به منوی مدیریت', 'callback_data' => 'admin_panel']
            ]
        ];

        $this->editMessageText($text, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
            'parse_mode' => 'HTML'
        ]);
    }
    /**
     * 🛒 ۱. نمایش لیست تمام پلن‌های فعال
     */
    protected function showAvailablePlans()
    {
        // دریافت پلن‌های فعال به ترتیب مرتب‌سازی
        $plans = \App\Models\Plan::where('is_active', 1)->orderBy('sort_order', 'asc')->get();

        $text = "🛍 <b>لیست پلن‌های ارتقای فروشگاه</b>\n\n";
        $text .= "با ارتقای پلن خود، محدودیت‌ها را برداشته و از امکانات حرفه‌ای‌تری برای مدیریت فروشگاهتان بهره‌مند شوید.\n\n";
        $text .= "👇 <i>برای مشاهده جزئیات و قیمت، روی پلن مورد نظر کلیک کنید:</i>";

        $keyboard = [];

        foreach ($plans as $plan) {
            $keyboard[] = [
                ['text' => "💎 " . $plan->name, 'callback_data' => "admin_plan_{$plan->id}"]
            ];
        }

        $keyboard[] = [
            ['text' => '🔙 بازگشت', 'callback_data' => 'admin_subscription']
        ];

        $this->editMessageText($text, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
            'parse_mode' => 'HTML'
        ]);
    }

    /**
     * 💎 ۲. نمایش جزئیات، امکانات و دکمه‌های خرید یک پلن خاص (با سیستم جلوگیری از سوءاستفاده)
     */
    protected function showPlanDetails($planId)
    {
        $plan = \App\Models\Plan::find($planId);

        if (!$plan) {
            $this->sendRequest('answerCallbackQuery', [
                'callback_query_id' => $this->update['callback_query']['id'],
                'text' => "❌ پلن مورد نظر یافت نشد.",
                'show_alert' => true
            ]);
            return;
        }

        // ==========================================
        // 🛡 لایه امنیتی: بررسی وضعیت اشتراک فعلی ربات
        // ==========================================
        $currentPlanSlug = data_get($this->bot, 'subscription_plan') ?? data_get($this->bot, 'plan_slug') ?? null;
        $expiresAt = data_get($this->bot, 'subscription_expires_at') ?? data_get($this->bot, 'plan_expires_at') ?? null;


        $isActive = false;
        $daysLeft = 0;

        if ($currentPlanSlug && $expiresAt) {
            $expireDate = \Carbon\Carbon::parse($expiresAt);
            if ($expireDate->isFuture()) {
                $isActive = true;
                $daysLeft = (int) now()->diffInDays($expireDate, false);
            }
        }

        // آیا پلنی که کاربر الان باز کرده، همان پلن فعلی خودش است؟
        $isSamePlan = ($currentPlanSlug === $plan->slug);

        // متغیر کنترل‌کننده نمایش دکمه‌های خرید
        $canBuy = true;
        $warningMessage = "";

        // اگر اشتراک فعال است، پلن متفاوتی انتخاب کرده، و بیشتر از ۳ روز زمان دارد -> قفل می‌شود
        if ($isActive && !$isSamePlan && $daysLeft > 3) {
            $canBuy = false;
            $warningMessage = "⚠️ <b>محدودیت تغییر پلن:</b>\nشما در حال حاضر <b>{$daysLeft} روز</b> از اشتراک پلن دیگری را در اختیار دارید. برای جلوگیری از تداخل، تغییر یا ارتقای پلن تنها در <b>۳ روز پایانی</b> اشتراک فعلی امکان‌پذیر است.\n\n<i>(شما هم‌اکنون فقط می‌توانید پلن فعلی خودتان را تمدید کنید)</i>\n\n";
        }

        // ==========================================
        // 📝 ساخت متن پیام
        // ==========================================
        $text = "💎 <b>{$plan->name}</b>\n\n";
        $text .= "📝 {$plan->description}\n\n";
        $text .= "✨ <b>امکانات و ویژگی‌ها:</b>\n";

        // استخراج امکانات (Features) از فرمت JSON دیتابیس
        $features = is_string($plan->features) ? json_decode($plan->features, true) : $plan->features;
        if (is_array($features)) {
            foreach ($features as $feature) {
                $text .= "✔️ $feature\n";
            }
        }

        // اضافه کردن اخطار امنیتی به متن (اگر وجود داشته باشد)
        $text .= "\n" . $warningMessage;

        $keyboard = [];

        // 🔘 نمایش دکمه‌های خرید فقط در صورتی که کاربر مجاز به خرید باشد
        if ($canBuy) {
            $text .= "👇 <i>مدت زمان اشتراک خود را انتخاب کنید:</i>";

            // استخراج و ساخت دکمه‌های خرید بر اساس دوره‌های زمانی (Prices JSON)
            $prices = is_string($plan->prices) ? json_decode($plan->prices, true) : $plan->prices;
            if (is_array($prices)) {
                foreach ($prices as $priceData) {
                    $months = $priceData['duration_months'];
                    $priceAmount = number_format($priceData['price']);

                    $keyboard[] = [
                        ['text' => "💳 خرید اشتراک {$months} ماهه - {$priceAmount} تومان", 'callback_data' => "admin_buy_plan_{$plan->id}_{$months}"]
                    ];
                }
            }
        }

        $keyboard[] = [
            ['text' => '🔙 بازگشت به لیست پلن‌ها', 'callback_data' => 'admin_view_plans']
        ];

        $this->editMessageText($text, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
            'parse_mode' => 'HTML' // مهم برای رندر شدن تگ‌های <b> و <i>
        ]);
    }
    /**
     * 🧾 ۳. تولید فاکتور و ایجاد لینک درگاه پرداخت برای اشتراک
     */

    protected function generateSubscriptionInvoice($planId, $months)
    {
        $plan = \App\Models\Plan::find($planId);
        if (!$plan)
            return;

        // استخراج قیمت بر اساس ماه از JSON
        $prices = is_string($plan->prices) ? json_decode($plan->prices, true) : $plan->prices;
        $payableAmount = 0;

        if (is_array($prices)) {
            foreach ($prices as $priceData) {
                if ($priceData['duration_months'] == $months) {
                    $payableAmount = $priceData['price'];
                    break;
                }
            }
        }

        if ($payableAmount <= 0) {
            $this->sendRequest('answerCallbackQuery', [
                'callback_query_id' => $this->update['callback_query']['id'],
                'text' => "❌ خطایی در استخراج قیمت رخ داد.",
                'show_alert' => true
            ]);
            return;
        }

        // 🧮 تبدیل ماه به روز (بر اساس فیلد duration_days مدل شما)
        $durationDays = (int) $months * 30;

        // 🚀 استفاده از مدل اختصاصی شما
        $payment = \App\Models\SubscriptionPayment::create([
            'bot_id' => $this->bot->id,
            'user_id' => $this->user->id,
            'plan_key' => $plan->slug,
            'plan_name' => $plan->name,
            'duration_days' => $durationDays,
            'amount' => $payableAmount,
            'status' => 'pending',
        ]);

        $paymentUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'subscription.payment',
            now()->addMinutes(30),
            ['payment' => $payment->id]
        );

        $text = "🧾 <b>پیش‌فاکتور خرید اشتراک</b>\n➖➖➖➖➖➖➖➖➖➖\n";
        $text .= "🔹 <b>فروشگاه:</b> {$this->bot->bot_name}\n";
        $text .= "🔹 <b>پلن انتخابی:</b> {$plan->name}\n";
        $text .= "⏳ <b>مدت اعتبار:</b> {$durationDays} روز\n";
        $text .= "💳 <b>مبلغ قابل پرداخت:</b> " . number_format($payableAmount) . " تومان\n\n";

        $text .= "⚠️ <i>این لینک پرداخت تنها ۳۰ دقیقه اعتبار دارد. پس از پرداخت موفق، اشتراک شما بلافاصله فعال/تمدید خواهد شد.</i>\n\n";
        $text .= "👇 برای پرداخت امن، روی دکمه زیر کلیک کنید:";

        $keyboard = [
            // [
            //     ['text' => '🌐 ورود به درگاه پرداخت آنلاین', 'url' => $paymentUrl]
            // ],
            [
                ['text' => '💳 کارت به کارت', 'callback_data' => "admin_sub_card_pay_{$payment->id}"]
            ],
            [
                ['text' => '🔙 بازگشت', 'callback_data' => "admin_plan_{$plan->id}"]
            ]
        ];

        $this->editMessageText($text, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
            'parse_mode' => 'HTML'
        ]);
    }

    /**
     * ارسال فیش واریزی به تمام ادمین‌ها با استفاده از Rich Message (بدون محدودیت کاراکتر)
     */
    protected function notifyAdminsForReceipt($transaction, $invoice, $fileId)
    {
        $admins = \App\Models\User::where('bot_id', $this->bot->id)->whereIn('is_admin', [1, 2])->whereNotNull('chat_id')->get();
        $addressId = $invoice->user_info['address_id'] ?? null;
        $address = $addressId ? \App\Models\UserAddress::find($addressId) : null;
        $phone = $address ? $address->phone : 'ثبت نشده';
        $invoice->load('items');

        $html = "<img src=\"tg://photo?id=receipt\"/><br><br>";
        $html .= "💳 <b>بررسی فیش واریزی (کارت به کارت)</b><br>";
        $html .= "➖➖➖➖➖➖➖➖➖➖<br>";
        $html .= "🔸 <b>شماره فاکتور:</b> <code>{$invoice->tracking_code}</code><br>";
        $html .= "👤 <b>مشتری:</b> {$this->user->first_name} (@{$this->user->username})<br>";
        $html .= "📞 <b>تلفن تماس:</b> <code>{$phone}</code><br>";
        $html .= "➖➖➖➖➖➖➖➖➖➖<br><br>";

        $html .= "🛒 <b>اقلام سفارش:</b><br>";
        $html .= "<table bordered striped compact>";
        $html .= "<tr><th align=\"right\">نام محصول</th><th align=\"center\">تعداد</th></tr>";
        foreach ($invoice->items as $item) {
            $html .= "<tr><td align=\"right\">{$item->name_at_purchase}</td><td align=\"center\"><b>{$item->quantity}</b></td></tr>";
        }
        $html .= "</table><br><br>";

        // ۴. تفکیک دقیق مالی در گزارش فیش
        $shippingCost = $invoice->user_info['shipping_cost'] ?? 0;
        $productDiscount = $invoice->user_info['product_discount'] ?? 0;
        $couponDiscount = $invoice->user_info['coupon_discount'] ?? 0;
        $finalAmount = $invoice->total_amount - $invoice->discount_amount + $shippingCost - $invoice->wallet_amount_used;

        $html .= "💰 <b>جزئیات مالی:</b><br>";
        $html .= "🔹 جمع قیمت اصلی کالاها: " . number_format($invoice->total_amount) . " T<br>";

        if ($productDiscount > 0 || $couponDiscount > 0) {
            if ($productDiscount > 0) {
                $html .= "🎁 تخفیف کالاها: " . number_format($productDiscount) . " T<br>";
            }
            if ($couponDiscount > 0) {
                $html .= "🎟 تخفیف کد <code>({$invoice->applied_discount_code})</code>: " . number_format($couponDiscount) . " T<br>";
            }
        } elseif ($invoice->discount_amount > 0) {
            $discountCodeStr = $invoice->applied_discount_code ? " <code>({$invoice->applied_discount_code})</code>" : "";
            $html .= "🎁 مجموع تخفیف‌ها{$discountCodeStr}: " . number_format($invoice->discount_amount) . " T<br>";
        }

        $html .= "🚚 هزینه ارسال: " . ($shippingCost > 0 ? number_format($shippingCost) . " T" : "رایگان") . "<br>";
        if ($invoice->wallet_amount_used > 0) {
            $html .= "💰 کسر از کیف پول: " . number_format($invoice->wallet_amount_used) . " T<br>";
        }

        $html .= "<br>💳 <b>مبلغ فیش (نهایی): " . number_format($finalAmount) . " تومان</b><br><br>";
        $html .= "👇 <i>لطفاً فیش واریزی را با دقت بررسی کرده و عملیات را تایید یا رد کنید:</i>";

        $keyboard = [
            [['text' => '✅ تایید فیش و پرداخت', 'callback_data' => "admin_rcpt_approve_{$transaction->id}"]],
            [['text' => '❌ رد فیش (مغایرت/نامعتبر)', 'callback_data' => "admin_rcpt_reject_{$transaction->id}"]],
            [['text' => '👁 مشاهده کامل داشبورد سفارش', 'callback_data' => "admin_view_order_{$invoice->id}"]]
        ];

        $mediaArray = [['id' => 'receipt', 'media' => ['type' => 'photo', 'media' => $fileId]]];

        foreach ($admins as $admin) {
            \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$this->bot->bot_token}/sendRichMessage", [
                'chat_id' => $admin->chat_id,
                'rich_message' => ['html' => $html, 'media' => $mediaArray],
                'reply_markup' => ['inline_keyboard' => $keyboard]
            ]);
        }
    }

    /**
     * پردازش کلیک ادمین روی تایید یا رد فیش (سازگار با Rich Message)
     */
    protected function processAdminReceiptAction($action, $transactionId)
    {
        // 🔒 لایه امنیتی ۱: آیا کاربر ادمین است؟
        if ($this->user->is_admin != 1)
            return;

        // استفاده از lockForUpdate برای جلوگیری از تداخل (دو ادمین همزمان کلیک کنند)
        $transaction = \App\Models\Transaction::where('id', $transactionId)->first();
        if (!$transaction)
            return;

        // 🔒 لایه امنیتی ۲ (Race Condition): آیا قبلاً توسط ادمین دیگری تایید/رد شده است؟
        if ($transaction->status !== 'pending') {
            $this->sendRequest('answerCallbackQuery', [
                'callback_query_id' => $this->update['callback_query']['id'],
                'text' => "⛔️ این فیش قبلاً توسط یکی دیگر از مدیران بررسی و تعیین تکلیف شده است.",
                'show_alert' => true
            ]);

            // بروزرسانی پیام ادمین به صورت Rich Message تا دکمه‌ها حذف شوند
            \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$this->bot->bot_token}/editMessageText", [
                'chat_id' => $this->chatId,
                'message_id' => $this->messageId,
                'text' => 'در حال بروزرسانی...',
                'rich_message' => ['html' => "⛔️ <b>این فیش قبلاً بررسی شده است.</b>"],
                'reply_markup' => json_encode(['inline_keyboard' => []])
            ]);
            return;
        }

        $invoice = \App\Models\Invoice::find($transaction->invoice_id);

        if ($action === 'approve') {
            $transaction->update(['status' => 'success', 'description' => "تایید شده توسط ادمین: {$this->user->first_name}"]);
            $invoice->update(['status' => 'paid']);

            $this->sendRequest('answerCallbackQuery', ['callback_query_id' => $this->update['callback_query']['id'], 'text' => "✅ فیش تایید و فاکتور پرداخت شد."]);

            // آپدیت پیام فیش برای ادمین به یک رسید تاییدیه‌ی زیبا
            $html = "✅ <b>فیش واریزی با موفقیت تایید شد.</b><br><br>";
            $html .= "👤 <b>بررسی کننده:</b> {$this->user->first_name}<br>";
            $html .= "🔸 <b>سفارش:</b> <code>{$invoice->tracking_code}</code><br>";
            $html .= "✨ وضعیت این فاکتور به «پرداخت شده» تغییر یافت.";

            \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$this->bot->bot_token}/editMessageText", [
                'chat_id' => $this->chatId,
                'message_id' => $this->messageId,
                'text' => 'در حال بروزرسانی...',
                'rich_message' => ['html' => $html],
                'reply_markup' => json_encode(['inline_keyboard' => []])
            ]);

            // ==========================================
            // 📄 تولید و ارسال فاکتور PDF به مشتری
            // ==========================================
            try {
                // ۱. واکشی اطلاعات مورد نیاز برای ویو PDF
                $addressId = $invoice->user_info['address_id'] ?? $invoice->user_address_id;
                $address = $addressId ? \App\Models\UserAddress::find($addressId) : null;
                $items = $invoice->items;
                $refId = $transaction->reference_id ?? 'کارت به کارت';

                // ۲. ساخت فایل PDF
                $pdf = \Mccarlosen\LaravelMpdf\Facades\LaravelMpdf::loadView('payment.pdf', [
                    'invoice' => $invoice,
                    'address' => $address,
                    'items' => $items,
                    'refId' => $refId
                ]);
                $pdfContent = $pdf->output();

                // ۳. ذخیره در سرور
                $fileName = 'invoices/' . $invoice->tracking_code . '.pdf';
                \Illuminate\Support\Facades\Storage::disk('public')->put($fileName, $pdfContent);
                $invoice->update(['receipt_file_id' => $fileName]);

                // ۴. ساخت متن پیام برای مشتری
                $userCaption = "🎉 <b>پرداخت شما تایید شد!</b>\n\n";
                $userCaption .= "مشتری عزیز، فیش واریزی شما برای سفارش <code>{$invoice->tracking_code}</code> مورد تایید قرار گرفت و سفارش شما وارد مرحله پردازش شد. 📦\n\n";
                $userCaption .= "📥 <b>فاکتور رسمی شما در قالب PDF پیوست این پیام است.</b>";

                // ۵. ارسال فایل PDF مستقیماً به پی‌وی مشتری
                \Illuminate\Support\Facades\Http::attach(
                    'document',
                    $pdfContent,
                    "Invoice-{$invoice->tracking_code}.pdf"
                )->post("https://api.telegram.org/bot{$this->bot->bot_token}/sendDocument", [
                            'chat_id' => $transaction->user->chat_id,
                            'caption' => $userCaption,
                            'parse_mode' => 'HTML',
                            'reply_markup' => json_encode([
                                'inline_keyboard' => [[['text' => '📦 پیگیری سفارش', 'callback_data' => 'my_orders_list']]]
                            ])
                        ]);

            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('PDF Generation Error (Admin Approve): ' . $e->getMessage());

                // در صورت بروز هرگونه خطا در تولید PDF، حداقل پیام متنی تاییدیه برای مشتری ارسال شود
                \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$this->bot->bot_token}/sendMessage", [
                    'chat_id' => $transaction->user->chat_id,
                    'text' => "🎉 <b>پرداخت شما تایید شد!</b>\n\nمشتری عزیز، فیش واریزی شما برای سفارش <code>{$invoice->tracking_code}</code> مورد تایید قرار گرفت و سفارش شما وارد مرحله پردازش شد. 📦",
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [[['text' => '📦 پیگیری سفارش', 'callback_data' => 'my_orders_list']]]
                    ])
                ]);
            }

        } else if ($action === 'reject') {
            $transaction->update(['status' => 'failed', 'description' => "رد شده توسط ادمین: {$this->user->first_name}"]);
            $invoice->update(['status' => 'failed']); // اگر مایل بودید می‌توانید به 'canceled' تغییر دهید تا کالا به انبار برگردد

            $this->sendRequest('answerCallbackQuery', ['callback_query_id' => $this->update['callback_query']['id'], 'text' => "❌ فیش رد شد."]);

            // 🚀 آپدیت پیام فیش برای ادمین به عنوان فیشِ باطل‌شده
            $html = "❌ <b>این فیش رد و باطل شد.</b><br><br>";
            $html .= "👤 <b>بررسی کننده:</b> {$this->user->first_name}<br>";
            $html .= "🔸 <b>سفارش:</b> <code>{$invoice->tracking_code}</code>";

            \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$this->bot->bot_token}/editMessageText", [
                'chat_id' => $this->chatId,
                'message_id' => $this->messageId,
                'text' => 'در حال بروزرسانی...',
                'rich_message' => ['html' => $html],
                'reply_markup' => json_encode(['inline_keyboard' => []])
            ]);

            // 📢 ارسال پیام رد به مشتری
            \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$this->bot->bot_token}/sendMessage", [
                'chat_id' => $transaction->user->chat_id,
                'text' => "⚠️ <b>فیش واریزی تایید نشد!</b>\n\nمشتری عزیز، فیش ارسالی شما برای سفارش <code>{$invoice->tracking_code}</code> توسط مدیریت فروشگاه تایید نگردید. لطفاً برای پیگیری با پشتیبانی در تماس باشید.",
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [[['text' => '🎧 ارتباط با پشتیبانی', 'callback_data' => 'support_info']]]
                ])
            ]);
        }
    }
  /**
     * 📱 درخواست شماره تماس از کاربر (شروع احراز هویت)
     */
    protected function askForContact()
    {
        $text = "📱 <b>تایید و اتصال شماره موبایل</b>\n\n";
        $text .= "برای اتصال حساب تلگرام شما به وب‌سایت فروشگاه، ابتدا باید شماره موبایل شما با <b>پیامک</b> تایید شود.\n\n";
        $text .= "👇 <b>شما می‌توانید به دو روش شماره خود را وارد کنید:</b>\n";
        $text .= "۱. روی دکمه <b>«☎️ ارسال شماره تلگرام من»</b> کلیک کنید.\n";
        $text .= "۲. به صورت دستی شماره خود را (مثلاً <code>09123456789</code>) تایپ کرده و ارسال کنید.\n\n";
        $text .= "<i>(برای انصراف کلمه <code>لغو</code> را بفرستید)</i>";

        $keyboard = [
            'keyboard' => [
                [['text' => '☎️ ارسال شماره تلگرام من', 'request_contact' => true]]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ];

        if ($this->messageId) {
            $this->deleteMessage($this->messageId);
        }

        $this->sendMessage($text, [
            'reply_markup' => json_encode($keyboard)
        ]);

        $this->setSession('state', 'wait_for_contact_input');
    }

    /**
     * 📥 پردازش شماره تماس ارسال شده توسط دکمه Share Contact
     */
    protected function processContact()
    {
        $contact = $this->update['message']['contact'];
        $phone = $contact['phone_number'];

        // استانداردسازی شماره
        if (str_starts_with($phone, '+98')) {
            $phone = '0' . substr($phone, 3);
        } elseif (str_starts_with($phone, '98') && strlen($phone) == 12) {
            $phone = '0' . substr($phone, 2);
        } elseif (!str_starts_with($phone, '0')) {
            $phone = '0' . $phone;
        }

        // بررسی تکراری نبودن شماره در این ربات
        $exists = \App\Models\User::where('bot_id', $this->bot->id)
            ->where('phone', $phone)
            ->where('id', '!=', $this->user->id)
            ->exists();

        if ($exists) {
            $this->sendMessage("❌ <b>این شماره قبلاً تایید شده است!</b>\nشماره <code>{$phone}</code> در حال حاضر به یک حساب کاربری دیگر متصل است. لطفاً شماره دیگری را به صورت دستی تایپ کنید.");
            return;
        }

        // 🚀 به جای ذخیره مستقیم، سیکل احراز هویت پیامکی را استارت می‌زنیم:
        $this->initiateOtpFlow($phone);
    }

    /**
     * 📱 ارسال پیامک احراز هویت با استفاده از سرویس اختصاصی سیستم
     */
    protected function sendSms($phone, $code)
    {
        try {
            // فراخوانی کلاس SmsService اختصاصی خودتان که در پوشه Services قرار دارد
            app(\App\Services\SmsService::class)->sendOtp($phone, $code);
            
            // ثبت در لاگ برای اطمینان از عملکرد صحیح
            \Illuminate\Support\Facades\Log::info("ShopBot: SMS OTP sent to {$phone}");
            
        } catch (\Exception $e) {
            // جلوگیری از کرش کردن ربات در صورت قطعی موقت درگاه پیامکی و ثبت خطای دقیق
            \Illuminate\Support\Facades\Log::error('ShopBot SMS Send Error: ' . $e->getMessage());
            
            // در صورت تمایل می‌توانید به کاربر هم پیام بدهید که "ارسال پیامک با مشکل مواجه شد"
            // $this->sendMessage("⚠️ در حال حاضر سیستم پیامکی با اختلال مواجه است. لطفاً چند دقیقه دیگر تلاش کنید.");
        }
    }

    /**
     * 🔄 شروع چرخه احراز هویت پیامکی (OTP)
     */
    protected function initiateOtpFlow($phone)
    {
        // تولید کد ۵ رقمی تصادفی
        $otp = rand(10000, 99999);
        
        // ذخیره در دیتابیس با انقضای ۲ دقیقه‌ای
        $this->user->otp_code = (string) $otp;
        $this->user->otp_expires_at = now()->addMinutes(2);
        $this->user->save();

        // ذخیره شماره موبایل در حافظه موقت (تا بعد از تایید، آن را در دیتابیس بنویسیم)
        $this->setSession('pending_phone', $phone);
        
        // قرار دادن ربات در وضعیت انتظار برای دریافت کد
        $this->setSession('state', 'wait_for_otp_input');

        // ارسال پیامک
        $this->sendSms($phone, $otp);

        // پیام به کاربر + بستن کیبورد دکمه‌ای قبلی
        $this->sendMessage("📲 <b>کد تایید ارسال شد!</b>\n\nیک کد ۵ رقمی به شماره <code>{$phone}</code> پیامک شد.\nلطفاً کد دریافت شده را در همین چت تایپ کرده و ارسال کنید:\n\n⏳ <i>(کد ارسال شده تا ۲ دقیقه معتبر است)</i>\n\n<i>(برای تغییر شماره کلمه <code>لغو</code> را بفرستید)</i>", [
            'reply_markup' => json_encode(['remove_keyboard' => true])
        ]);
    }
}
