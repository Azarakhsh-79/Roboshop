<?php

namespace App\Bot\Shop;

use App\Models\ManagedBot;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use \Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopBotHandler
{
    use ShopBotHelpersTrait;

    protected $bot;
    protected $user;
    protected $update;
    protected $isExpired;

    // متغیرهای موقت که در init پر می‌شوند
    protected $chatId;
    protected $messageId;
    protected $text;
    protected $data;

    // متغیرهای موقت برای inline query
    protected $inlineQueryId;
    protected $inlineQueryText;

    protected float $telegramTimeMs = 0;



    public function __construct(ManagedBot $bot, ?User $user, array $update, bool $isExpired)
    {
        $this->bot = $bot;
        $this->user = $user;
        $this->update = $update;
        $this->isExpired = $isExpired;

        $this->init();
    }
    public function handle()
    {

        // 🛑 سپر امنیتی: نادیده گرفتن تمام پیام‌ها و ویرایش‌های داخل کانال
        if (isset($this->update['channel_post']) || isset($this->update['edited_channel_post']) || isset($this->update['my_chat_member'])) {
            return;
        }
        // Log::info('update: ' . json_encode($this->update));

        // 🛡 جلوگیری از کرش کردن PHP (در صورتی که آپدیت نوع دیگری بود و text مقداردهی نشد)
        $this->text = $this->text ?? '';

        if (str_starts_with($this->text, '/start verify_')) {
            $this->handleStart();
            return;
        }

        // ⛔ فیلتر دوم: اگر کاربر عادی است ولی در دیتابیس ثبت نشده، هیچ کاری نکن
        if (!$this->user) {
            return;
        }

        // ۰. بررسی انقضای اشتراک ربات (حالا با خیال راحت چک می‌کنیم چون مطمئنیم یوزر نال نیست)
        $isOwner = ($this->bot->owner_id === $this->user->id) || ($this->user->is_admin);

        if ($this->isExpired && !$isOwner) {
            $this->sendMessage("⚠️ متاسفیم، اشتراک سرویس‌دهی این ربات به پایان رسیده است.");
            return;
        }

        if (!$isOwner && $this->checkForceJoin()) {
            // اگر checkForceJoin مقدار true برگرداند، یعنی قفل عمل کرده و نباید هیچ پیامی پردازش شود
            return;
        }

        // ۱. پردازش کال‌بک‌ها
        if (isset($this->update['callback_query'])) {
            $this->handleCallback();
            return;
        }

        // ۲. پردازش پیام‌های متنی
        if (isset($this->update['message'])) {
            $this->handleMessage();
        }

        // ۳. پردازش اینلاین کوئری‌ها
        if (isset($this->update['inline_query'])) {
            $this->handleInlineSearch();
            return;
        }
    }

    public function getTelegramTimeMs(): float
    {
        return $this->telegramTimeMs;
    }

    protected function handleMessage()
    {

        // 🚀 پردازش دریافت شماره تماس (Contact)
        if (isset($this->update['message']['contact'])) {
            $this->processContact();
            return;
        }

        // نادیده گرفتن پیام‌های سیستمی
        if (str_starts_with($this->text, '💬 :')) {
            return;
        }

        if (str_starts_with($this->text, '/start')) {
            $this->handleStart();
            return;
        }



        // بررسی State های فعال
        $state = $this->getSession('state');
        if ($state) {
            if (in_array($this->text, ['/cancel', 'انصراف', 'بازگشت'])) {
                $this->setSession('state', null);
                $this->setSession('temp_category_name', null);
                $this->sendMessage("✅ عملیات لغو شد.");
                if (str_starts_with($state, 'admin_')) {
                    $this->showCategories(null, true);
                } else {
                    $this->showMainMenu();
                }
                return;
            }
            $this->handleActiveProcess($state);
            return;
        }

        // 🛡 ۱. نرمال‌سازی اعداد فارسی/عربی به انگلیسی
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $normalizedText = str_replace($persian, $english, $this->text);
        $normalizedText = str_replace($arabic, $english, $normalizedText);

        // 🛡 ۲. تبدیل به حروف بزرگ برای جستجوی دقیق فاکتور
        $upperText = strtoupper(trim($normalizedText));

        // =========================================================
        // بررسی کد فاکتور
        // =========================================================
        if (str_starts_with($upperText, 'INV-')) {
            $invoice = \App\Models\Invoice::where('bot_id', $this->bot->id)
                ->where('tracking_code', $upperText)
                ->first();

            if ($invoice) {
                if ($this->user->is_admin == 1) {
                    $this->adminShowOrderDetails($invoice->id);
                } elseif ($invoice->user_id == $this->user->id) {
                    $this->showOrderDetails($invoice->id);
                } else {
                    $this->sendMessage("⚠️ <b>خطای دسترسی!</b>\nاین فاکتور متعلق به شما نیست.");
                }
            } else {
                $this->sendMessage("فاکتوری با کد <code>{$upperText}</code> پیدا نشد.");
            }
            return; // توقف اجرا و جلوگیری از ارسال به هوش مصنوعی
        }

        // =========================================================
        // دستورات اصلی منو
        // =========================================================

        // 🔒 بررسی نیاز به ثبت شماره موبایل برای دستورات متنی حساس
        $authRequiredCommands = [
            '/my_orders',
            '📦 پیگیری سفارشات',
            'سفارشات من',
            '/favorites',
            '❤️ علاقه‌مندی‌ها',
            'موردعلاقه‌ها',
            '/cart',
            '🛒 سبد خرید',
            'سبد خرید'
        ];

        if (in_array($this->text, $authRequiredCommands) && empty($this->user->phone)) {
            $this->sendMessage("⚠️ <b>دسترسی محدود!</b>\nبرای استفاده از این بخش باید شماره موبایل خود را ثبت و تایید کنید.");
            $this->askForContact();
            return; // توقف پردازش
        }

        match ($this->text) {
            '/start', '🏠 منوی اصلی', 'بازگشت' => $this->showMainMenu(),
            '/categories', '🛍 دسته‌بندی‌ها', 'محصولات' => $this->showCategories(),
            '/my_orders', '📦 پیگیری سفارشات', 'سفارشات من' => $this->showMyOrders(),
            '/about', 'درباره ما', 'درباره', 'درباره فروشگاه' => $this->showAboutInfo(),
            '/search', '🔍 جستجو', 'جستجو' => $this->sendMessage(
                "👇 برای جستجوی محصول، روی دکمه زیر کلیک کنید:",
                [
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [['text' => '🔍 جستجوی محصولات', 'switch_inline_query_current_chat' => '']]
                        ]
                    ])
                ]
            ),
            '/favorites', '❤️ علاقه‌مندی‌ها', 'موردعلاقه‌ها' => $this->showFavorites(1),
            '/discounts', 'تخفیف‌ها', 'شگفت‌انگیز' => $this->showDiscountedProducts(1),
            '/latest', '✨ جدیدترین‌ها', 'جدیدترین' => $this->showLatestProducts(1),
            '/support', '📞 پشتیبانی', 'پشتیبانی' => $this->showSupportInfo(),
            '/cart', '🛒 سبد خرید', 'سبد خرید' => $this->showCart(),
            '/cache_clear' => $this->clearCache(),
            default => $this->handleUnknownOrSkuSearch(),
        };
    }
    public function clearCache()
    {
        // بررسی دسترسی ادمین/مالک
        if (!$this->isBotOwner()) {
            return;
        }

        $webhookSecret = data_get($this->bot, 'webhook_secret');
        // پاک کردن کش اصلی ربات با کلید secret (اگر موجود باشد)
        if ($webhookSecret) {
            \Illuminate\Support\Facades\Cache::forget("bot_config:{$webhookSecret}");
        }

        // پاک کردن سشن جاری کاربر
        $this->clearSession();

        $this->sendMessage("کش تنظیمات ربات با موفقیت پاک شد.");
    }



    protected function handleUnknownOrSkuSearch()
    {
        // 🛡 نرمال‌سازی کد SKU (تبدیل اعداد فارسی و اصلاح فاصله‌ها)
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $normalizedText = str_replace($persian, $english, $this->text);
        $normalizedText = str_replace($arabic, $english, $normalizedText);

        $cleanText = trim($normalizedText);
        $upperText = strtoupper($cleanText);

        // جستجوی مستقیم کالا با کد محصول (SKU)
        $product = Product::where('bot_id', $this->bot->id)
            ->where('is_active', true)
            ->where(function ($query) use ($cleanText, $upperText) {
                $query->where('sku', $cleanText)
                    ->orWhere('sku', $upperText);
            })
            ->first();

        if ($product) {
            $this->sendProductMessage($product);
            return;
        }

        // جلوگیری از اسپم هوش مصنوعی (Rate Limiting)
        $rateLimitKey = "ai_rate_limit:{$this->bot->id}:{$this->chatId}";
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $this->sendMessage("⚠️ درخواست‌های شما بیش از حد مجاز است. لطفاً کمی صبر کنید.");
            return;
        }
        \Illuminate\Support\Facades\RateLimiter::hit($rateLimitKey, 60);

        // در صورت پیدا نشدن محصول، پیام به هوش مصنوعی می‌رود
        $waitRes = $this->sendMessage("🤖 <i>در حال بررسی...</i>");
        $waitMsgId = $waitRes['result']['message_id'] ?? null;

        \App\Jobs\RunShopAiAgentJob::dispatchSync(
            $this->bot,
            $this->user,
            \App\AI\Agents\ShopSalesAgent::class,
            $cleanText,
            $this->chatId,
            $waitMsgId
        );
    }


    protected function handleCallback(): void
    {
        $callbackId = $this->update['callback_query']['id'];
        $this->messageId = $this->update['callback_query']['message']['message_id'] ?? null;


        // =========================================================
        // 🛒 مسیرهای کاربری (مشتریان)
        // =========================================================

        // 🚀 دکمه درخواست شماره موبایل
        if ($this->data === 'req_contact') {
            $this->askForContact();
            return;
        }

        // 🔒 قفل‌گذاری هوشمند: سد امنیتی برای بخش‌های نیازمند شماره تماس
        $authRequiredPrefixes = [
            'toggle_fav_',
            'my_favorites',
            'fav_page_',           // علاقه‌مندی‌ها
            'add_to_cart_',
            'select_variant_',
            'cart_qty_',       // افزودن به سبد و تعیین تعداد
            'cart_commit_',
            'view_cart',
            'ucart_',                // مدیریت سبد خرید و حذف کالا
            'cart_choose_address',
            'cart_checkout_',              // مراحل تسویه حساب
            'my_orders_list',
            'view_order_',
            'dl_receipt_'        // پیگیری سفارشات و دانلود فاکتور
        ];

        foreach ($authRequiredPrefixes as $prefix) {
            if (str_starts_with($this->data, $prefix) && empty($this->user->phone)) {

                // ۱. نمایش یک پاپ‌آپ اخطار وسط صفحه گوشی کاربر
                $this->sendRequest('answerCallbackQuery', [
                    'callback_query_id' => $callbackId,
                    'text' => "⚠️ برای استفاده از این امکان، ابتدا باید شماره موبایل خود را تایید کنید.",
                    'show_alert' => true
                ]);

                // ۲. هدایت خودکار به صفحه دریافت شماره و استارت سیستم پیامکی
                $this->askForContact();

                // ۳. توقف کامل عملیات (کاربر تا ثبت نام نکند نمی‌تواند جلوتر برود)
                return;
            }
        }

        if ($this->data === 'main_menu') {
            $this->showMainMenu();
            return;
        }

        if ($this->data === 'main_menu') {
            $this->showMainMenu();
            return;
        }

        if ($this->data === 'show_categories') {
            $this->showCategories();
            return;
        }

        if (str_starts_with($this->data, 'subcat_')) {
            $this->showCategories(str_replace('subcat_', '', $this->data));
            return;
        }

        if (str_starts_with($this->data, 'products_')) {
            $this->showProductsList(str_replace('products_', '', $this->data), 1);
            return;
        }

        if (str_starts_with($this->data, 'prod_page_')) {
            // فرمت: prod_page_{categoryId}_{page}
            [$categoryId, $page] = explode('_', str_replace('prod_page_', '', $this->data));
            $this->showProductsList($categoryId, (int) $page);
            return;
        }

        if (str_starts_with($this->data, 'show_prod_')) {
            $product = Product::find(str_replace('show_prod_', '', $this->data));

            if (!$product) {
                $this->sendMessage("❌ <i>این محصول یافت نشد یا از فروشگاه حذف شده است.</i>");
                return;
            }

            $this->deleteMessage($this->messageId);

            $backButton = ['text' => '🔙 بازگشت به لیست محصولات', 'callback_data' => "products_{$product->category_id}"];
            $isAdmin = ($this->user->is_admin == 1);
            \App\Services\TelegramProductService::send($product, $this->chatId, false, $backButton, $isAdmin);
            return;
        }

        if (str_starts_with($this->data, 'cat_page_')) {
            // فرمت: cat_page_{parentId}_{isAdmin}_{page}
            $parts = explode('_', str_replace('cat_page_', '', $this->data));
            $parentId = $parts[0] === 'null' ? null : $parts[0];
            $this->showCategories($parentId, $parts[1] == '1', (int) $parts[2]);
            return;
        }

        if ($this->data === 'ignore_cb') {
            $this->sendRequest('answerCallbackQuery', ['callback_query_id' => $callbackId]);
            return;
        }

        if ($this->data === 'about') {
            $this->showAboutInfo();
            return;
        }

        // 🚀 علاقه‌مندی‌ها
        if (str_starts_with($this->data, 'toggle_fav_')) {
            $this->toggleFavorite(str_replace('toggle_fav_', '', $this->data));
            return;
        }

        if ($this->data === 'my_favorites') {
            $this->showFavorites(1);
            return;
        }

        if (str_starts_with($this->data, 'fav_page_')) {
            $page = (int) str_replace('fav_page_', '', $this->data);
            $this->showFavorites($page);
            return;
        }

        // 🚀 سیستم گالری تصاویر
        if (str_starts_with($this->data, 'gallery_')) {
            $parts = explode('_', str_replace('gallery_', '', $this->data));
            if (count($parts) >= 2) {
                $this->showProductGallery($parts[0], (int) $parts[1]);
            }
            return;
        }


        // 🚀 نمایش جدیدترین محصولات
        if ($this->data === 'latest_products') {
            $this->showLatestProducts(1);
            return;
        }

        if (str_starts_with($this->data, 'latest_page_')) {
            $page = (int) str_replace('latest_page_', '', $this->data);
            $this->showLatestProducts($page);
            return;
        }

        // =========================================================
        // ⚙️ مسیرهای مدیریتی (پنل ادمین)
        // =========================================================

        if ($this->data === 'admin_panel') {
            $this->showAdminPanel();
            return;
        }

        if ($this->data === 'admin_manage_categories') {
            $this->setSession('state', null);
            $this->setSession('message_id', null);
            $this->showCategories(null, true);
            return;
        }

        if ($this->data === 'admin_add_main_category') {
            $keyboard = [[['text' => '🔙 لغو و بازگشت', 'callback_data' => 'admin_manage_categories']]];
            $this->editMessageText(
                "✨ <b>ساخت دسته‌بندی جدید</b>\n\n"
                . "🏷 لطفاً <b>نام دسته‌بندی</b> موردنظر خود را وارد کنید.\n\n"
                . "💡 <b>یک پیشنهاد برای جذاب‌تر شدن فروشگاه:</b>\n"
                . "می‌توانید از ایموجی مرتبط در نام دسته استفاده کنید.\n\n"
                . "مثلاً:\n"
                . "👕 پوشاک\n"
                . "📱 موبایل و لوازم جانبی\n"
                . "💻 لپ‌تاپ و کامپیوتر\n"
                . "🏠 لوازم خانه\n\n"
                . "🎨 انتخاب یک ایموجی مناسب باعث می‌شود منوی فروشگاه زیباتر و دسته‌بندی‌ها برای مشتریان قابل تشخیص‌تر باشند.\n\n"
                . "<i>برای انصراف، دکمه زیر را انتخاب کنید.</i>",
                [
                    'reply_markup' => json_encode([
                        'inline_keyboard' => $keyboard
                    ])
                ]
            );

            $this->setSession('state', 'admin_add_main_category');
            if ($this->messageId) {
                $this->setSession('message_id', $this->messageId);
            }
            return;
        }

        if ($this->data === 'admin_save_cat_main_nodesc') {
            if ($name = $this->getSession('temp_category_name')) {
                Category::create([
                    'bot_id' => $this->bot->id,
                    'name' => $name,
                    'description' => null,
                    'is_active' => true,
                    'sort_order' => Category::where('bot_id', $this->bot->id)->whereNull('parent_id')->max('sort_order') + 1,
                ]);
            }
            $this->setSession('state', null);
            $this->setSession('message_id', null);
            $this->setSession('temp_category_name', null);
            $this->showCategories(null, true);
            return;
        }

        if (str_starts_with($this->data, 'admin_save_cat_sub_')) {
            // فرمت: admin_save_cat_sub_{parentId}_nodesc
            $parentId = str_replace(['admin_save_cat_sub_', '_nodesc'], '', $this->data);
            if ($name = $this->getSession('temp_category_name')) {
                Category::create([
                    'bot_id' => $this->bot->id,
                    'parent_id' => $parentId,
                    'name' => $name,
                    'description' => null,
                    'is_active' => true,
                    'sort_order' => Category::where('bot_id', $this->bot->id)->where('parent_id', $parentId)->max('sort_order') + 1,
                ]);
            }
            $this->setSession('state', null);
            $this->setSession('message_id', null);
            $this->setSession('temp_category_name', null);
            $this->showCategories($parentId, true);
            return;
        }

        if ($this->data === 'admin_cat_invalidtarget') {
            $this->sendRequest('answerCallbackQuery', [
                'callback_query_id' => $callbackId,
                'text' => "❌ این دسته دارای زیردسته است و نمی‌توانید مستقیماً به آن محصول اضافه کنید!",
                'show_alert' => true,
            ]);
            return;
        }

        if (str_starts_with($this->data, 'admin_cat_')) {
            // فرمت: admin_cat_{action}_{id}
            $parts = explode('_', str_replace('admin_cat_', '', $this->data));
            $action = array_shift($parts);
            $id = implode('_', $parts);
            if ($action && $id !== '') {
                $this->handleAdminCategoryAction($action, $id);
            }
            return;
        }

        if ($this->data === 'admin_manage_products') {
            $this->setSession('state', null);
            $this->setSession('message_id', null);
            $this->showManageProductsMenu();
            return;
        }

        if ($this->data === 'admin_prod_add') {
            $this->setSession('state', 'admin_prod_step_name');
            $this->setSession('temp_product_data', []);
            $keyboard = [[['text' => '🔙 لغو و بازگشت', 'callback_data' => 'admin_manage_products']]];
            $this->editMessageText(
                "🏷 <b>مرحله ۱: نام محصول</b>\n\nلطفاً <b>نام محصول جدید</b> را به صورت متنی ارسال کنید:\n\n<i>(برای انصراف روی دکمه زیر کلیک کنید)</i>",
                ['reply_markup' => json_encode(['inline_keyboard' => $keyboard])]
            );
            if ($this->messageId) {
                $this->setSession('message_id', $this->messageId);
            }
            return;
        }

        if ($this->data === 'admin_prod_finish_photos') {
            $tempProduct = $this->getSession('temp_product_data');
            if (empty($tempProduct['photos'])) {
                $this->sendRequest('answerCallbackQuery', [
                    'callback_query_id' => $callbackId,
                    'text' => "❌ شما هنوز هیچ عکسی ارسال نکرده‌اید!",
                    'show_alert' => true,
                ]);
                return;
            }
            $this->askProductCategoryToFinish($this->getSession('message_id'), $tempProduct);
            return;
        }

        if (str_starts_with($this->data, 'admin_prod_list')) {
            // فرمت: admin_prod_list_{catId}_{page}
            $parts = explode('_', str_replace('admin_prod_list', '', $this->data));
            $catId = $parts[1] ?? null;
            $page = (int) ($parts[2] ?? 1);
            $catId ? $this->showAdminProductList($catId, $page) : $this->showCategories(null, true);
            return;
        }

        if (str_starts_with($this->data, 'admin_prod_save_cat_')) {
            $this->saveProductToDatabase(str_replace('admin_prod_save_cat_', '', $this->data));
            return;
        }

        if (str_starts_with($this->data, 'admin_prod_')) {
            $allowedActions = [
                'manage',
                'toggle',
                'del',
                'editname',
                'editsku',
                'editprice',
                'editdiscount',
                'editstock',
                'editdesc',
                'editcat',
                'savecat',
                'editphoto',
                'delphoto',
                'setmainphoto',
                'donephoto',
            ];
            $parts = explode('_', str_replace('admin_prod_', '', $this->data));
            $action = array_shift($parts);
            $id = implode('_', $parts);
            if (in_array($action, $allowedActions, true) && $id !== '') {
                $this->handleAdminProductAction($action, $id);
            }
            return;
        }

        // =========================================================
        // 🛠 مدیریت سفارشات توسط ادمین در تلگرام
        // =========================================================

        // ۱. ادمین روی مشاهده سفارش کلیک کرده است
        if (str_starts_with($this->data, 'admin_view_order_')) {
            $invoiceId = str_replace('admin_view_order_', '', $this->data);
            $this->adminShowOrderDetails($invoiceId);
            return;
        }

        // 🛑 دریافت تاییدیه ادمین برای لغو سفارش و استرداد وجه
        if (str_starts_with($this->data, 'admin_confirm_cancel_')) {
            $invoiceId = str_replace('admin_confirm_cancel_', '', $this->data);
            $this->adminConfirmCancelOrder($invoiceId);
            return;
        }

        // ۲. ادمین قصد تغییر وضعیت سفارش را دارد
        if (str_starts_with($this->data, 'admin_change_status_')) {
            // ساختار: admin_change_status_{id}_{status}
            $parts = explode('_', str_replace('admin_change_status_', '', $this->data));
            $this->adminChangeOrderStatus($parts[0], $parts[1]);
            return;
        }


        // =========================================================
        // 🛒 سبد خرید
        // =========================================================

        if (str_starts_with($this->data, 'add_to_cart_')) {
            $this->showQuantitySelector(str_replace('add_to_cart_', '', $this->data), 1);
            return;
        }

        if (str_starts_with($this->data, 'select_variant_')) {
            $productId = str_replace('select_variant_', '', $this->data);
            \Illuminate\Support\Facades\Cache::forget("temp_cart_var_{$this->chatId}_{$productId}");
            $this->showUnifiedVariantSelector($productId);
            return;
        }

        if (str_starts_with($this->data, 'cart_qty_')) {
            // فرمت: cart_qty_{productId}_{qty}_{variantId?}
            $parts = explode('_', str_replace('cart_qty_', '', $this->data));
            $productId = $parts[0];
            $newQty = (int) $parts[1];
            $variantId = $parts[2] ?? null;
            $variantId
                ? $this->updateTempVariantCart($productId, $variantId, $newQty)
                : $this->showQuantitySelector($productId, $newQty);
            return;
        }

        if (str_starts_with($this->data, 'cart_commit_vars_')) {
            $this->commitVariantsToCart(str_replace('cart_commit_vars_', '', $this->data));
            return;
        }

        if (str_starts_with($this->data, 'cart_commit_')) {
            // فرمت: cart_commit_{productId}_{qty}_{variantId?}
            $parts = explode('_', str_replace('cart_commit_', '', $this->data));
            $variantId = (!empty($parts[2])) ? $parts[2] : null;
            $this->commitToCart($parts[0], $parts[1], $variantId);
            return;
        }

        if ($this->data === 'view_cart') {
            $this->showCart();
            return;
        }

        // 👈 این ۳ شرط جدید را اینجا اضافه کنید
        if ($this->data === 'cart_enter_discount') {
            $this->promptDiscountCode();
            return;
        }

        if ($this->data === 'cart_remove_discount') {
            $this->setSession('applied_discount_code', null);
            $this->showCart();
            return;
        }

        if ($this->data === 'cart_toggle_wallet') {
            $current = $this->getSession('use_wallet', false);
            $this->setSession('use_wallet', !$current);
            $this->showCart();
            return;
        }

        if (str_starts_with($this->data, 'ucart_qty_')) {
            $parts = explode('_', str_replace('ucart_qty_', '', $this->data));
            $this->updateCartItemQty($parts[0], $parts[1]);
            return;
        }

        if (str_starts_with($this->data, 'ucart_del_')) {
            $this->removeCartItem(str_replace('ucart_del_', '', $this->data));
            return;
        }

        if ($this->data === 'ucart_clear') {
            \App\Models\Cart::where('bot_id', $this->bot->id)->where('user_id', $this->user->id)->delete();
            $this->sendRequest('answerCallbackQuery', [
                'callback_query_id' => $callbackId,
                'text' => "🗑 سبد خرید شما به طور کامل خالی شد.",
                'show_alert' => false,
            ]);
            $this->showCart();
            return;
        }

        if ($this->data === 'alert_outofstock') {
            $this->sendRequest('answerCallbackQuery', [
                'callback_query_id' => $callbackId,
                'text' => "💔 متاسفانه این محصول در حال حاضر تمام شده است.",
                'show_alert' => true,
            ]);
            return;
        }

        if ($this->data === 'cart_choose_address') {
            $this->showAddressSelection();
            return;
        }

        if ($this->data === 'cart_checkout_new_addr') {
            $this->askForNewAddress();
            return;
        }

        if (str_starts_with($this->data, 'cart_select_addr_')) {
            $this->setSession('checkout_address_id', str_replace('cart_select_addr_', '', $this->data));
            $this->showCart();
            return;
        }

        if ($this->data === 'ucart_checkout') {
            $this->showFinalInvoice();
            return;
        }

        if ($this->data === 'cart_checkout_online') {
            $this->generateInvoice('online');
            return;
        }

        if ($this->data === 'cart_checkout_card') {
            $this->generateInvoice('card');
            return;
        }

        // ⚠️ ۱. نمایش پیام هشدار قبل از لغو
        if (str_starts_with($this->data, 'cart_cancel_invoice_')) {
            $invoiceId = str_replace('cart_cancel_invoice_', '', $this->data);

            $text = "⚠️ <b>هشدار لغو فاکتور</b>\n\n";
            $text .= "آیا مطمئن هستید که می‌خواهید این فاکتور را لغو کنید؟\n";
            $text .= "<i>در صورت لغو، فاکتور کاملاً باطل شده و محصولات رزرو شده به انبار فروشگاه بازمی‌گردند. این عملیات غیرقابل بازگشت است و امکان پرداخت این فاکتور دیگر وجود نخواهد داشت.</i>";

            $keyboard = [
                [['text' => '❌ بله، فاکتور کاملاً لغو شود', 'callback_data' => "cart_do_cancel_{$invoiceId}"]],
                [['text' => '🔙 خیر، بازگشت به صفحه پرداخت', 'callback_data' => "cart_back_to_pay_{$invoiceId}"]]
            ];

            $this->editMessageText($text, ['reply_markup' => json_encode(['inline_keyboard' => $keyboard])]);
            return;
        }

        // 🔙 ۲. بازگشت به صفحه پرداخت (در صورت پشیمانی از لغو)
        if (str_starts_with($this->data, 'cart_back_to_pay_')) {
            $invoiceId = str_replace('cart_back_to_pay_', '', $this->data);
            $invoice = \App\Models\Invoice::find($invoiceId);

            if (!$invoice || $invoice->status !== 'pending') {
                $this->editMessageText("❌ فاکتور شما منقضی یا لغو شده است.", ['reply_markup' => json_encode(['inline_keyboard' => [[['text' => '🏠 منوی اصلی', 'callback_data' => 'main_menu']]]])]);
                return;
            }

            $cardNumber = $this->bot->settings->firstWhere('key', 'bank_card_number')?->value;
            $cardName = $this->bot->settings->firstWhere('key', 'bank_card_name')?->value;

            $text = "💳 <b>پرداخت کارت به کارت</b>\n\n";
            $text .= "فاکتور شما با شماره پیگیری <code>{$invoice->tracking_code}</code> صادر شد.\n";
            $text .= "مبلغ قابل پرداخت: <b>" . number_format($invoice->final_amount ?? $invoice->total_amount) . " تومان</b>\n➖➖➖➖➖➖➖➖➖➖\n";
            $text .= "💳 <b>شماره کارت:</b> <code>{$cardNumber}</code>\n";
            $text .= "👤 <b>به نام:</b> {$cardName}\n➖➖➖➖➖➖➖➖➖➖\n\n";
            $text .= "📸 <b>مرحله آخر:</b> لطفاً مبلغ را واریز کرده و <b>تصویر فیش واریزی (اسکرین‌شات)</b> را همینجا در ربات ارسال کنید.";

            $keyboard = [
                [['text' => '📋 کپی سریع شماره کارت', 'copy_text' => ['text' => $cardNumber]]],
                [['text' => '🔙 انصراف و لغو فاکتور', 'callback_data' => "cart_cancel_invoice_{$invoice->id}"]]
            ];

            $this->editMessageText($text, [
                'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
            ]);
            return;
        }

        // ==========================================
        // 🌟 صفحه‌بندی جدیدترین محصولات
        // ==========================================
        if (str_starts_with($this->data, 'latest_prods_')) {
            $page = (int) str_replace('latest_prods_', '', $this->data);
            $this->showLatestProducts($page);
            return;
        }

        // ==========================================
        // 🎁 صفحه‌بندی محصولات تخفیف‌دار
        // ==========================================
        if (str_starts_with($this->data, 'discounted_prods_')) {
            $page = (int) str_replace('discounted_prods_', '', $this->data);
            $this->showDiscountedProducts($page);
            return;
        }

        // 🗑 ۳. لغو قطعی فاکتور
        if (str_starts_with($this->data, 'cart_do_cancel_')) {
            $invoiceId = str_replace('cart_do_cancel_', '', $this->data);

            $this->adminChangeOrderStatus($invoiceId, 'canceled'); // تغییر وضعیت به لغو شده و بازگشت موجودی انبار
            $this->setSession('state', null);
            $this->setSession('message_id', null);

            $this->editMessageText("🚫 <b>فاکتور با موفقیت لغو شد.</b>\n\nمحصولات از رزرو شما خارج شده و به انبار فروشگاه بازگشتند.", [
                'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '🏠 بازگشت به منوی اصلی', 'callback_data' => 'main_menu']]]])
            ]);
            return;
        }

        // --- پردازش دکمه‌های تایید یا رد فیش توسط ادمین‌ها ---
        if (str_starts_with($this->data, 'admin_rcpt_')) {
            $parts = explode('_', str_replace('admin_rcpt_', '', $this->data));
            $action = $parts[0]; // 'approve' یا 'reject'
            $transactionId = $parts[1];
            $this->processAdminReceiptAction($action, $transactionId);
            return;
        }


        // =========================================================
        // 📦 پیگیری سفارشات
        // =========================================================

        if ($this->data === 'my_orders_list') {
            $this->showMyOrders();
            return;
        }

        if (str_starts_with($this->data, 'view_order_')) {
            $this->showOrderDetails(str_replace('view_order_', '', $this->data));
            return;
        }

        if (str_starts_with($this->data, 'dl_receipt_')) {
            $this->sendOrderReceipt(str_replace('dl_receipt_', '', $this->data));
            return;
        }




        // =========================================================
        // 🚧 تنظیمات
        // =========================================================

        if ($this->data === 'admin_store_settings') {
            $this->showAdminSettings(0);
            return;
        }

        if (str_starts_with($this->data, 'admin_settings_page_')) {
            $page = (int) str_replace('admin_settings_page_', '', $this->data);
            $this->showAdminSettings($page);
            return;
        }

        if (str_starts_with($this->data, 'admin_set_info_')) {
            $this->showSettingInfo(str_replace('admin_set_info_', '', $this->data));
            return;
        }

        if (str_starts_with($this->data, 'admin_set_edit_')) {
            $this->editSettingPrompt(str_replace('admin_set_edit_', '', $this->data));
            return;
        }

        // =========================================================
        // 👥 مسیرهای مدیریت ادمین‌ها
        // =========================================================

        if ($this->data === 'admin_manage_admins') {
            $this->showAdminsList();
            return;
        }

        if ($this->data === 'admin_add_admin_prompt') {
            $this->promptAddAdmin();
            return;
        }

        if (str_starts_with($this->data, 'admin_del_admin_')) {
            $this->removeAdmin(str_replace('admin_del_admin_', '', $this->data));
            return;
        }

        // =========================================================
        // 🛒 مسیرهای کاربری (مشتریان)
        // =========================================================

        // 🚀 مسیرهای جدید برای تخفیف خورده‌ها
        if ($this->data === 'discounted_products') {
            $this->showDiscountedProducts(1);
            return;
        }

        if (str_starts_with($this->data, 'discounted_page_')) {
            $page = (int) str_replace('discounted_page_', '', $this->data);
            $this->showDiscountedProducts($page);
            return;
        }

        // 🚀 نمایش صفحه پشتیبانی
        if ($this->data === 'support_info') {
            $this->showSupportInfo();
            return;
        }

        // 📊 آمار سریع محصول (مخصوص ادمین)
        if (str_starts_with($this->data, 'prod_stats_')) {
            $productId = str_replace('prod_stats_', '', $this->data);
            $this->showProductStats($productId);
            return;
        }

        // ⚙️ هدایت به صفحه مدیریت و تنظیمات محصول (مخصوص ادمین)
        if (str_starts_with($this->data, 'admin_manage_prod_')) {
            $productId = str_replace('admin_manage_prod_', '', $this->data);
            $this->handleAdminProductManage($productId);
            return;
        }

        // =========================================================
        // 🛠 مسیرهای مدیریت ادمین
        // =========================================================

        // 📦 لیست سفارشات
        if ($this->data === 'admin_manage_orders') {
            $this->showAdminOrdersList(1);
            return;
        }

        if (str_starts_with($this->data, 'admin_orders_page_')) {
            $page = (int) str_replace('admin_orders_page_', '', $this->data);
            $this->showAdminOrdersList($page);
            return;
        }

        // 👁 مشاهده جزئیات یک سفارش
        if (str_starts_with($this->data, 'admin_order_detail_')) {
            $orderId = str_replace('admin_order_detail_', '', $this->data);
            $this->showAdminOrderDetail($orderId);
            return;
        }

        // 🔄 تغییر وضعیت سفارش
        if (str_starts_with($this->data, 'admin_order_status_')) {
            // فرمت دیتا: admin_order_status_OrderId_NewStatus
            $parts = explode('_', str_replace('admin_order_status_', '', $this->data));
            if (count($parts) === 2) {
                $this->changeOrderStatus($parts[0], $parts[1]);
            }
            return;
        }

        if (str_starts_with($this->data, 'about_')) {

            $parts = explode('_', substr($this->data, strlen('about_')));

            switch ($parts[0]) {
                case 'features':
                    $this->showAboutFeatures();
                    break;

                case 'developer':
                    $this->showAboutDeveloper();
                    break;

                case 'contact':
                    $this->showAboutContact();
                    break;
                case 'menu':
                    $this->showAboutInfo();
                    break;
            }


            return;
        }
        //admin_subscription
        if ($this->data === 'admin_subscription') {
            $this->showAdminSubscription();
            return;
        }
        // 🚀 نمایش پروفایل کاربری
        if ($this->data === 'user_profile') {
            $this->showUserProfile();
            return;
        }
        // نمایش لیست پلن‌ها
        if ($this->data === 'admin_view_plans') {
            $this->showAvailablePlans();
            return;
        }

        // مشاهده جزئیات یک پلن خاص
        if (str_starts_with($this->data, 'admin_plan_')) {
            $planId = str_replace('admin_plan_', '', $this->data);
            $this->showPlanDetails($planId);
            return;
        }

        // ایجاد فاکتور خرید برای پلن انتخاب شده (بر اساس مدت زمان)
        if (str_starts_with($this->data, 'admin_buy_plan_')) {
            // فرمت دیتا: admin_buy_plan_{planId}_{months}
            $parts = explode('_', str_replace('admin_buy_plan_', '', $this->data));
            if (count($parts) === 2) {
                $this->generateSubscriptionInvoice($parts[0], $parts[1]);
            }
            return;
        }

        // کلیک روی دکمه پرداخت کارت به کارت برای اشتراک
        if (str_starts_with($this->data, 'admin_sub_card_pay_')) {
            $paymentId = str_replace('admin_sub_card_pay_', '', $this->data);
            $payment = \App\Models\SubscriptionPayment::find($paymentId);

            if (!$payment || $payment->status !== 'pending') {
                $this->sendMessage("❌ فاکتور نامعتبر یا منقضی شده است.");
                return;
            }

            // شماره کارت شما به عنوان مالک پلتفرم (از فایل env خوانده شود یا هاردکد کنید)
            $superAdminCard = env('SUPERADMIN_CARD_NUMBER', '6037998234433232');
            $superAdminName = env('SUPERADMIN_CARD_NAME', 'امیر سلیمانی');

            $text = "💳 <b>پرداخت کارت به کارت (تمدید اشتراک)</b>\n\n";
            $text .= "مبلغ قابل پرداخت: <b>" . number_format($payment->amount) . " تومان</b>\n";
            $text .= "💳 شماره کارت: <code>{$superAdminCard}</code>\n";
            $text .= "👤 به نام: {$superAdminName}\n\n";
            $text .= "📸 <b>مرحله آخر:</b> لطفاً مبلغ را واریز کرده و تصویر فیش واریزی را همینجا ارسال کنید.";

            $keyboard = [
                [['text' => '🔙 انصراف و بازگشت', 'callback_data' => 'admin_subscription']]
            ];

            $this->editMessageText($text, ['reply_markup' => json_encode(['inline_keyboard' => $keyboard])]);

            // قرار دادن ربات در وضعیت انتظار دریافت فیش اشتراک
            $this->setSession('state', "admin_wait_sub_receipt_{$payment->id}");
            if ($this->messageId) {
                $this->setSession('message_id', $this->messageId);
            }
            return;
        }
        // =========================================================
        // 🚧 بخش‌های در حال توسعه
        // =========================================================


        $this->sendRequest('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => "🚧 این بخش به زودی فعال می‌شود...",
            'show_alert' => true,
        ]);
        return;

    }

    /**
     * 🔍 موتور جستجوی زنده و اینلاین تلگرام
     */
    /**
     * 🔍 موتور جستجوی زنده و اینلاین تلگرام
     */
    protected function handleInlineSearch()
    {
        $query = trim($this->inlineQueryText);
        $results = [];

        Log::info("🔍 جستجوی اینلاین: {$query}");

        // 🌟 اگر کاربر هنوز چیزی تایپ نکرده است (نمایش اطلاعات فروشگاه و راهنما)
        if (empty($query)) {
            // دریافت نام فروشگاه به صورت مستقیم از تنظیمات
            $shopName = \App\Models\Setting::where('bot_id', $this->bot->id)->where('key', 'store_name')->value('value') ?? $this->bot->bot_name;

            $results[] = [
                'type' => 'article',
                'id' => 'guide_search',
                'title' => '🏪 ' . $shopName,
                'description' => '🔍 برای پیدا کردن کالا، نام یا کد آن را تایپ کنید...',
                'input_message_content' => [
                    'message_text' => "🏪 <b>{$shopName}</b>\n\n🔍 <b>راهنمای جستجوی سریع:</b>\nبرای پیدا کردن محصول مورد نظر، کافیست در همین کادر، بخشی از <b>نام محصول</b> یا <b>کد کالا (SKU)</b> را تایپ کنید تا نتایج به صورت زنده برای شما لیست شود.",
                    'parse_mode' => 'HTML',
                ],
                'reply_markup' => [
                    'inline_keyboard' => [
                        [['text' => '🔍 شروع تایپ و جستجو', 'switch_inline_query_current_chat' => '']]
                    ]
                ]
            ];
        }
        // 🌟 اگر کاربر شروع به تایپ کرده است (جستجوی واقعی محصولات)
        else {
            $products = \App\Models\Product::where('bot_id', $this->bot->id)
                ->where('is_active', true)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('sku', 'like', "%{$query}%");
                })
                ->orderBy('created_at', 'desc')
                ->take(30)
                ->get();

            foreach ($products as $product) {
                $displayPrice = $product->price;
                $discountLabel = "";

                if (!empty($product->discount_price) && (empty($product->discount_expires_at) || \Carbon\Carbon::parse($product->discount_expires_at)->isFuture())) {
                    $displayPrice = $product->discount_price;
                    $discountLabel = " 🎁 (تخفیف ویژه)";
                }

                $priceFormatted = number_format($displayPrice) . " تومان";
                $skuText = $product->sku ? " | کد: {$product->sku}" : "";

                $results[] = [
                    'type' => 'article',
                    'id' => (string) $product->id,
                    'title' => $product->name . $discountLabel,
                    'description' => "💳 {$priceFormatted}{$skuText}",
                    'input_message_content' => [
                        'message_text' => "🎯 <b>کالای انتخاب شده:</b>\n\n✨ <b>{$product->name}</b>\n💳 بها: {$priceFormatted}\n\n<i>⏳ در حال بارگذاری تصاویر و جزئیات محصول...</i>",
                        'parse_mode' => 'HTML',
                    ],
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [['text' => '🛍 باز کردن مشخصات محصول', 'callback_data' => "show_prod_{$product->id}"]]
                        ]
                    ]
                ];
            }

            // اگر با متن تایپ شده هیچ محصولی پیدا نشد
            if (empty($results)) {
                $results[] = [
                    'type' => 'article',
                    'id' => 'no_result',
                    'title' => '❌ محصولی یافت نشد',
                    'description' => "هیچ کالایی با عبارت «{$query}» پیدا نکردیم.",
                    'input_message_content' => [
                        'message_text' => "❌ <b>نتیجه‌ای یافت نشد.</b>\nعبارت جستجو شده: <code>{$query}</code>\n\nلطفاً کلمه یا کد دیگری را امتحان کنید.",
                        'parse_mode' => 'HTML'
                    ],
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [['text' => '🔄 تلاش مجدد', 'switch_inline_query_current_chat' => '']]
                        ]
                    ]
                ];
            }
        }

        // ارسال نتایج به سرور تلگرام
        \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$this->bot->bot_token}/answerInlineQuery", [
            'inline_query_id' => $this->inlineQueryId,
            'results' => json_encode($results),
            'cache_time' => 1, // زمان کش را روی ۱ ثانیه گذاشتیم تا نتایج کاملاً زنده (Live) رندر شوند
            'is_personal' => true,
        ]);
    }

    /**
     * پردازش ورودی‌های کاربر زمانی که ربات در وضعیتِ انتظار قرار دارد (State Machine)
     */
    protected function handleActiveProcess($state)
    {
        // ==========================================
        // 🔹 مرحله اول: دریافت نام و پرسیدن توضیحات
        // ==========================================

        // ۱-الف: دریافت نام دسته مادر
        if ($state === 'admin_add_main_category') {
            $categoryName = trim($this->text);
            $this->setSession('temp_category_name', $categoryName); // ذخیره موقت نام
            $this->setSession('state', 'admin_wait_desc_main');     // تغییر وضعیت به انتظار برای توضیحات

            $promptMessageId = $this->getSession('message_id');
            $this->deleteMessage($this->messageId); // پاک کردن پیام متنی مدیر

            if ($promptMessageId) {
                $this->messageId = $promptMessageId;
                $keyboard = [
                    [['text' => '💾 ذخیره بدون توضیحات', 'callback_data' => 'admin_save_cat_main_nodesc']],
                    [['text' => '🔙 لغو و بازگشت', 'callback_data' => 'admin_manage_categories']]
                ];
                $this->editMessageText(
                    "✅ <b>نام دسته‌بندی ثبت شد!</b>\n\n"
                    . "🏷 دسته‌بندی: <b>{$categoryName}</b>\n\n"
                    . "📝 حالا یک <b>توضیح کوتاه و کاربردی</b> برای این دسته‌بندی ارسال کنید.\n\n"
                    . "💡 این توضیحات می‌تواند به مشتری کمک کند بهتر متوجه شود چه محصولاتی در این بخش قرار دارند.\n\n"
                    . "مثلاً برای «👕 پوشاک»:\n"
                    . "<i>انواع لباس و پوشاک مردانه و زنانه</i>\n\n"
                    . "✨ <b>نکته:</b> اگر این دسته‌بندی نیازی به توضیح ندارد، می‌توانید آن را بدون توضیحات ذخیره کنید.\n\n"
                    . "👇 <i>توضیحات را ارسال کنید یا گزینه مناسب را انتخاب کنید:</i>",
                    [
                        'reply_markup' => json_encode([
                            'inline_keyboard' => $keyboard
                        ]),
                    ]
                );

            }
            return;
        }


        // ==========================================
        // 🔹 مرحله اول: دریافت دستی شماره موبایل کاربر
        // ==========================================
        if ($state === 'wait_for_contact_input') {
            $input = trim($this->text);

            $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
            $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
            $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
            $phone = str_replace($persian, $english, $input);
            $phone = str_replace($arabic, $english, $phone);

            if (str_starts_with($phone, '+98')) {
                $phone = '0' . substr($phone, 3);
            } elseif (str_starts_with($phone, '98') && strlen($phone) == 12) {
                $phone = '0' . substr($phone, 2);
            }

            if (!preg_match('/^09[0-9]{9}$/', $phone)) {
                $this->sendMessage("❌ <b>شماره موبایل نامعتبر است!</b>\nلطفاً یک شماره ۱۱ رقمی صحیح (مانند 09123456789) ارسال کنید.");
                return;
            }

            $exists = \App\Models\User::where('bot_id', $this->bot->id)
                ->where('phone', $phone)
                ->where('id', '!=', $this->user->id)
                ->exists();

            if ($exists) {
                $this->sendMessage("❌ <b>این شماره قبلاً ثبت شده است!</b>\nشماره <code>{$phone}</code> در حال حاضر به حساب کاربری دیگری متصل است. لطفاً شماره دیگری وارد کنید.");
                return;
            }

            // 🚀 شروع احراز هویت پیامکی برای شماره تایپ شده
            $this->initiateOtpFlow($phone);
            return;
        }

        // ==========================================
        // 🔹 مرحله دوم: دریافت و تایید کد پیامکی (OTP)
        // ==========================================
        if ($state === 'wait_for_otp_input') {
            $input = trim($this->text);

            // تبدیل اعداد فارسی به انگلیسی
            $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
            $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
            $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
            $code = str_replace($persian, $english, $input);
            $code = str_replace($arabic, $english, $code);

            // ۱. بررسی انقضای کد
            if (empty($this->user->otp_code) || empty($this->user->otp_expires_at) || \Carbon\Carbon::parse($this->user->otp_expires_at)->isPast()) {
                $this->user->update(['otp_code' => null, 'otp_expires_at' => null]);
                $this->sendMessage("⏳ <b>کد تایید منقضی شده است!</b>\nلطفاً مجدداً شماره موبایل خود را ارسال کنید تا کد جدید برایتان صادر شود.");
                $this->askForContact();
                return;
            }

            // ۲. بررسی صحت کد وارد شده
            if ($code !== $this->user->otp_code) {
                $this->sendMessage("❌ <b>کد وارد شده اشتباه است!</b>\nلطفاً کد ۵ رقمی پیامک شده را با دقت ارسال کنید:");
                return;
            }

            // ۳. احراز هویت موفق! ذخیره نهایی شماره تلفن
            $verifiedPhone = $this->getSession('pending_phone');

            $this->user->phone = $verifiedPhone;
            $this->user->otp_code = null;
            $this->user->otp_expires_at = null;
            $this->user->save();

            // پاکسازی حافظه موقت
            $this->setSession('state', null);
            $this->setSession('pending_phone', null);

            $this->sendMessage("✅ <b>تایید موفق!</b>\n\nشماره موبایل شما (<code>{$verifiedPhone}</code>) با موفقیت در سیستم تایید و ثبت شد.\n\n🎉 اکنون حساب تلگرام شما به طور کامل به وب‌سایت متصل است.");
            $this->showMainMenu();
            return;
        }

        // دریافت فیش واریزی برای تمدید اشتراک
        if (str_starts_with($state, 'admin_wait_sub_receipt_')) {
            $paymentId = str_replace('admin_wait_sub_receipt_', '', $state);
            $payment = \App\Models\SubscriptionPayment::find($paymentId);
            $promptMessageId = $this->getSession('message_id');

            if ($this->messageId && !isset($this->update['callback_query'])) {
                $this->deleteMessage($this->messageId);
            }

            $photo = $this->update['message']['photo'] ?? null;

            if (!$photo) {
                $this->sendMessage("❌ خطا: لطفاً فقط عکس فیش واریزی را ارسال کنید.");
                return;
            }

            $fileId = end($photo)['file_id'];

            // آپدیت وضعیت پرداخت
            $payment->update([
                'status' => 'awaiting_approval',
                'receipt_file_id' => $fileId
            ]);

            $this->setSession('state', null);
            $this->setSession('message_id', null);

            if ($promptMessageId) {
                $this->editMessageText("✅ <b>فیش شما دریافت شد.</b>\n\nپس از تایید مدیریت پلتفرم، اشتراک شما فعال خواهد شد.", [
                    'message_id' => $promptMessageId,
                    'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '🔙 بازگشت به پنل', 'callback_data' => 'admin_panel']]]])
                ]);
            }

            // ۱. دریافت فایل عکس از سرور تلگرام فروشگاه
            $getFileRes = Http::get("https://api.telegram.org/bot{$this->bot->bot_token}/getFile?file_id={$fileId}");
            $filePath = $getFileRes->json('result.file_path');

            $photoBinary = null;
            if ($filePath) {
                $photoBinary = Http::get("https://api.telegram.org/file/bot{$this->bot->bot_token}/{$filePath}")->body();
            }

            $superAdminChatId = env('SUPERADMIN_CHAT_ID', '199057701');

            $adminText = "🚨 <b>فیش واریزی جدید (تمدید اشتراک)</b>\n\n";
            $adminText .= "🏪 فروشگاه: {$this->bot->bot_name} (@{$this->bot->bot_username})\n";
            $adminText .= "👤 مدیر: {$this->user->first_name}\n";
            $adminText .= "💎 پلن: {$payment->plan_name} ({$payment->duration_days} روزه)\n";
            $adminText .= "💰 مبلغ: " . number_format($payment->amount) . " تومان\n";

            $adminKeyboard = [
                [
                    ['text' => '✅ تایید و فعال‌سازی', 'callback_data' => "superadmin_sub_approve_{$payment->id}"],
                    ['text' => '❌ رد فیش', 'callback_data' => "superadmin_sub_reject_{$payment->id}"]
                ]
            ];

            // ۲. ارسال امن عکس به ربات ادمین با قابلیت ضمیمه باینری (مستقیماً از بایت فایل)
            $adminBotToken = config('telegram.admin.bot_token');

            if ($photoBinary) {
                $response = Http::timeout(30)
                    ->attach('photo', $photoBinary, 'receipt.jpg')
                    ->post("https://api.telegram.org/bot{$adminBotToken}/sendPhoto", [
                        'chat_id' => $superAdminChatId,
                        'caption' => $adminText,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode(['inline_keyboard' => $adminKeyboard])
                    ]);
            } else {
                // فال‌بک معمولی اگر دانلود عکس ناموفق بود
                $response = Http::timeout(15)
                    ->post("https://api.telegram.org/bot{$adminBotToken}/sendPhoto", [
                        'chat_id' => $superAdminChatId,
                        'photo' => $fileId,
                        'caption' => $adminText,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode(['inline_keyboard' => $adminKeyboard])
                    ]);
            }

            // ۳. بررسی و ثبت خطا در صورت عدم موفقیت ارسال به ادمین
            if (!$response->successful()) {
                Log::error("Failed to send subscription receipt to superadmin: " . json_encode($response->json()));
            }
        }
        // ۱-ب: دریافت نام زیردسته
        if (str_starts_with($state, 'admin_add_sub_category_')) {
            $parentId = str_replace('admin_add_sub_category_', '', $state);
            $categoryName = trim($this->text);
            $this->setSession('temp_category_name', $categoryName);
            $this->setSession('state', "admin_wait_desc_sub_{$parentId}");

            $promptMessageId = $this->getSession('message_id');
            $this->deleteMessage($this->messageId);

            if ($promptMessageId) {
                $this->messageId = $promptMessageId;
                $keyboard = [
                    [['text' => '💾 ذخیره بدون توضیحات', 'callback_data' => "admin_save_cat_sub_{$parentId}_nodesc"]],
                    [['text' => '« بازگشت', 'callback_data' => "admin_cat_show_{$parentId}"]]
                ];
                $this->editMessageText(
                    "✅ <b>زیردسته با موفقیت ثبت شد!</b>\n\n"
                    . "🏷 زیردسته: <b>{$categoryName}</b>\n\n"
                    . "📝 حالا یک <b>توضیح کوتاه و کاربردی</b> برای این زیردسته ارسال کنید.\n\n"
                    . "💡 این توضیحات به مشتری کمک می‌کند بهتر با محتوای این بخش آشنا شود.\n\n"
                    . "مثلاً برای «👟 کفش و کتانی»:\n"
                    . "<i>انواع کفش، کتانی و مدل‌های مناسب برای استفاده روزمره و ورزشی</i>\n\n"
                    . "✨ <b>اگر این زیردسته نیازی به توضیح ندارد،</b> کافی است روی گزینه «ذخیره بدون توضیحات» بزنید.\n\n"
                    . "👇 <i>توضیحات را ارسال کنید یا گزینه مناسب را انتخاب کنید:</i>",
                    [
                        'reply_markup' => json_encode([
                            'inline_keyboard' => $keyboard
                        ]),
                    ]
                );

            }
            return;
        }

        // ==========================================
        // 🔹 مرحله دوم: دریافت توضیحات و ثبت نهایی در DB
        // ==========================================

        // ۲-الف: ذخیره دسته مادر با توضیحات
        if ($state === 'admin_wait_desc_main') {
            $categoryName = $this->getSession('temp_category_name');
            $description = trim($this->text);

            Category::create([
                'bot_id' => $this->bot->id,
                'name' => $categoryName,
                'description' => $description,
                'is_active' => true,
                'sort_order' => Category::where('bot_id', $this->bot->id)->whereNull('parent_id')->max('sort_order') + 1
            ]);

            $promptMessageId = $this->getSession('message_id');
            $this->setSession('state', null);
            $this->setSession('message_id', null);
            $this->setSession('temp_category_name', null);

            $this->deleteMessage($this->messageId);

            if ($promptMessageId) {
                $this->messageId = $promptMessageId;
                $this->update['callback_query'] = ['message' => ['message_id' => $promptMessageId]];
            }
            $this->showCategories(null, true);
            return;
        }

        // ۲-ب: ذخیره زیردسته با توضیحات
        if (str_starts_with($state, 'admin_wait_desc_sub_')) {
            $parentId = str_replace('admin_wait_desc_sub_', '', $state);
            $categoryName = $this->getSession('temp_category_name');
            $description = trim($this->text);

            Category::create([
                'bot_id' => $this->bot->id,
                'parent_id' => $parentId,
                'name' => $categoryName,
                'description' => $description,
                'is_active' => true,
                'sort_order' => Category::where('bot_id', $this->bot->id)->where('parent_id', $parentId)->max('sort_order') + 1
            ]);

            $promptMessageId = $this->getSession('message_id');
            $this->setSession('state', null);
            $this->setSession('message_id', null);
            $this->setSession('temp_category_name', null);

            $this->deleteMessage($this->messageId);

            if ($promptMessageId) {
                $this->messageId = $promptMessageId;
                $this->update['callback_query'] = ['message' => ['message_id' => $promptMessageId]];
            }
            $this->showCategories($parentId, true);
            return;
        }

        // ۳. دریافت توضیحات دسته‌بندی (ویرایش توضیحات)
        if (str_starts_with($state, 'admin_edit_category_desc_')) {
            $categoryId = str_replace('admin_edit_category_desc_', '', $state);
            $category = Category::where('bot_id', $this->bot->id)->find($categoryId);

            if ($category) {
                // اگر ادمین کلمه "حذف" را تایپ کرد، توضیحات NULL می‌شود
                $category->description = (trim($this->text) === 'حذف') ? null : trim($this->text);
                $category->save();
            }

            $promptMessageId = $this->getSession('message_id');

            // پاک کردن حافظه موقت
            $this->setSession('state', null);
            $this->setSession('message_id', null);

            // پاک کردن پیام متنی ادمین از صفحه چت
            $this->deleteMessage($this->messageId);

            // 🚀 تزریق آیدی پیام اصلی برای رفرش شدنِ شیشه‌ایِ صفحه
            if ($promptMessageId) {
                $this->messageId = $promptMessageId;
                $this->update['callback_query'] = [
                    'message' => [
                        'message_id' => $promptMessageId,
                        'chat' => ['id' => $this->chatId]
                    ]
                ];
            }

            // بازگشت هوشمند به صفحه جزئیاتِ همان دسته‌بندی
            $this->handleAdminCategoryAction('show', $categoryId);
            return;
        }

        // ۴. دریافت نام جدید دسته‌بندی (ویرایش نام)
        if (str_starts_with($state, 'admin_edit_cat_name_')) {
            $categoryId = str_replace('admin_edit_cat_name_', '', $state);
            $category = Category::where('bot_id', $this->bot->id)->find($categoryId);

            if ($category) {
                // نام جدید را جایگزین و ذخیره می‌کنیم
                $category->name = trim($this->text);
                $category->save();
            }

            $promptMessageId = $this->getSession('message_id');

            // پاک کردن حافظه موقت
            $this->setSession('state', null);
            $this->setSession('message_id', null);

            // پاک کردن پیام متنی ادمین از صفحه چت
            $this->deleteMessage($this->messageId);

            // 🚀 تزریق آیدی پیام اصلی برای رفرش شدنِ شیشه‌ایِ صفحه
            if ($promptMessageId) {
                $this->messageId = $promptMessageId;
                $this->update['callback_query'] = [
                    'message' => [
                        'message_id' => $promptMessageId,
                        'chat' => ['id' => $this->chatId]
                    ]
                ];
            }

            // بازگشت هوشمند به صفحه جزئیاتِ همان دسته‌بندی
            $this->handleAdminCategoryAction('show', $categoryId);
            return;
        }

        // ۵. الف) مرحله اول تخفیف گروهی: دریافت درصد و پرسیدن تعداد روز
        if (str_starts_with($state, 'admin_bulk_discount_percent_')) {
            $categoryId = str_replace('admin_bulk_discount_percent_', '', $state);
            $percent = trim($this->text);

            $promptMessageId = $this->getSession('message_id');
            $this->deleteMessage($this->messageId); // پیام عددِ ادمین پاک می‌شود

            if (!is_numeric($percent) || $percent < 0 || $percent > 99) {
                $this->sendMessage("❌ خطا: لطفاً یک درصد معتبر بین 0 تا 99 ارسال کنید.");
                return;
            }

            // اگر مدیر عدد 0 را فرستاد (یعنی حذف تخفیف)، دیگر نیازی به پرسیدن روز نیست و مستقیم به صف می‌فرستیم
            if ($percent == 0) {
                $this->setSession('state', null);
                $this->setSession('message_id', null);

                if ($promptMessageId) {
                    $this->editMessageText("⏳ <b>سیستم در حال انتقال لغو تخفیف‌ها به صف...</b>", ['message_id' => $promptMessageId]);
                }

                // ارسال به جاب با صفر روز
                \App\Jobs\ApplyBulkDiscountJob::dispatch($this->bot, $categoryId, 0, 0, $promptMessageId, $this->chatId);
                return;
            }

            // ذخیره موقت درصد در سشن و تغییر وضعیت ربات برای دریافت روز
            $this->setSession('temp_discount_percent', $percent);
            $this->setSession('state', "admin_bulk_discount_days_{$categoryId}");

            if ($promptMessageId) {
                $this->editMessageText(
                    "⏳ <b>درصد تخفیف ({$percent}٪) تایید شد. (مرحله ۲ از ۲)</b>\n\n" .
                    "لطفاً <b>مدت زمان اعتبار</b> این تخفیف را به «روز» ارسال کنید (مثلاً برای سه روز عدد <code>3</code> را بفرستید).\n\n" .
                    "<i>(اگر می‌خواهید تخفیف دائمی باشد و هرگز منقضی نشود، عدد <code>0</code> را ارسال کنید)</i>",
                    [
                        'message_id' => $promptMessageId,
                        'reply_markup' => json_encode(['inline_keyboard' => []])
                    ]
                );
            }
            return;
        }

        // ۵. ب) مرحله دوم تخفیف گروهی: دریافت روز و شلیک به صف لاراول
        if (str_starts_with($state, 'admin_bulk_discount_days_')) {
            $categoryId = str_replace('admin_bulk_discount_days_', '', $state);
            $days = trim($this->text);
            $percent = $this->getSession('temp_discount_percent'); // استخراج درصد از حافظه

            $promptMessageId = $this->getSession('message_id');
            $this->deleteMessage($this->messageId); // پیام روزِ ادمین پاک می‌شود

            if (!is_numeric($days) || $days < 0) {
                $this->sendMessage("❌ خطا: لطفاً یک عدد معتبر برای تعداد روزها (مثلاً 3) ارسال کنید.");
                return;
            }

            // فوراً حافظه کش آزاد می‌شود
            $this->setSession('state', null);
            $this->setSession('message_id', null);
            $this->setSession('temp_discount_percent', null);

            // 🚀 تبدیل فرم به نوار پیشرفت
            if ($promptMessageId) {
                $this->editMessageText("⏳ <b>سیستم در حال انتقال پردازش به صف (Queue)...</b>\n\n<i>این عملیات کاملاً در پس‌زمینه انجام می‌شود. لطفاً شکیبا باشید...</i>", [
                    'message_id' => $promptMessageId,
                    'reply_markup' => json_encode(['inline_keyboard' => []])
                ]);
            } else {
                $res = $this->sendMessage("⏳ <b>سیستم در حال انتقال پردازش به صف...</b>");
                $promptMessageId = $res['result']['message_id'] ?? null;
            }

            // 📦 شلیک کردن Job به صف (اضافه شدن متغیر روز به جاب)
            \App\Jobs\ApplyBulkDiscountJob::dispatch(
                $this->bot,
                $categoryId,
                $percent,
                (int) $days,   // 👈 ارسال تعداد روزها به جاب
                $promptMessageId,
                $this->chatId
            );

            Log::info("Webhook [Bot {$this->bot->id}]: ✅ درخواست تخفیف {$percent} درصدی برای {$days} روز به صف ارسال شد.");
            return;
        }

        if (str_starts_with($state, 'admin_prod_step_')) {
            $this->handleProductCreationSteps($state);
            return;
        }

        // 🚀 ویرایش فیلدهای محصول
        if (str_starts_with($state, 'admin_wait_prod_')) {
            $this->processAdminProductEdit($state);
            return;
        }

        // 🚀 دریافت آدرس و شماره تماس مشتری در مراحل تسویه حساب
        if (str_starts_with($state, 'cart_wait_address_')) {
            $this->processCheckoutAddress($state);
            return;
        }
        // 🚀 دریافت فیش واریزی کارت به کارت
        if (str_starts_with($state, 'cart_wait_receipt_')) {
            $invoiceId = str_replace('cart_wait_receipt_', '', $state);
            $invoice = \App\Models\Invoice::find($invoiceId);
            $promptMessageId = $this->getSession('message_id');

            // 🧹 پاک کردن پیام کاربر (عکس، متن، استیکر یا هرچیزی که فرستاده) تا چت خلوت بماند
            if ($this->messageId && !isset($this->update['callback_query'])) {
                $this->deleteMessage($this->messageId);
            }

            if (!$invoice || $invoice->status !== 'pending') {
                if ($promptMessageId) {
                    $this->editMessageText("❌ فاکتور شما منقضی یا نامعتبر شده است.", ['message_id' => $promptMessageId]);
                }
                $this->setSession('state', null);
                return;
            }

            $photo = $this->update['message']['photo'] ?? null;

            // ⚠️ اگر کاربر به جای عکس، متن یا چیز دیگری فرستاد
            if (!$photo) {
                $cardNumber = $this->bot->settings->firstWhere('key', 'bank_card_number')?->value;
                $cardName = $this->bot->settings->firstWhere('key', 'bank_card_name')?->value;

                $text = "❌ <b>خطا: لطفاً فقط عکس (تصویر) فیش واریزی را ارسال کنید.</b>\n\n";
                $text .= "💳 <b>شماره کارت:</b> <code>{$cardNumber}</code>\n";
                $text .= "👤 <b>به نام:</b> {$cardName}\n";

                if ($promptMessageId) {
                    $this->editMessageText($text, [
                        'message_id' => $promptMessageId,
                        'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '🔙 انصراف و لغو فاکتور', 'callback_data' => "cart_cancel_invoice_{$invoice->id}"]]]])
                    ]);
                }
                return;
            }

            // اگر عکس درست بود
            $fileId = end($photo)['file_id'];

            $transaction = \App\Models\Transaction::create([
                'bot_id' => $this->bot->id,
                'user_id' => $this->user->id,
                'invoice_id' => $invoice->id,
                'amount' => $invoice->final_amount,
                'gateway' => 'card_to_card',
                'reference_id' => $fileId,
                'status' => 'pending',
                'description' => 'فیش واریزی منتظر تایید مدیریت'
            ]);

            $invoice->update(['status' => 'awaiting_approval']);
            $this->setSession('state', null);
            $this->setSession('message_id', null);

            // ✅ متن و دکمه‌های موفقیت برای مشتری
            $successText = "✅ <b>فیش شما با موفقیت ثبت شد و برای مدیریت ارسال گردید.</b>\n\n⏳ پس از بررسی و تایید، سفارش شما پردازش خواهد شد.";

            $keyboard = [
                [['text' => '📦 سفارشات من', 'callback_data' => 'my_orders_list']],
                [['text' => '🏠 بازگشت به منوی اصلی', 'callback_data' => 'main_menu']]
            ];

            // 🚀 ادیت کردن پیام اصلی فاکتور به پیام تایید (بدون ارسال پیام جدید!)
            if ($promptMessageId) {
                $this->editMessageText($successText, [
                    'message_id' => $promptMessageId,
                    'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
                ]);
            } else {
                $this->sendMessage($successText, ['reply_markup' => json_encode(['inline_keyboard' => $keyboard])]);
            }

            // 📢 ارسال فیش برای ادمین‌ها
            $this->notifyAdminsForReceipt($transaction, $invoice, $fileId);
            return;
        }

        // 🚀 دریافت مقادیر تنظیمات فروشگاه
        if (str_starts_with($state, 'admin_wait_setting_')) {
            $this->processAdminSettingEdit($state);
            return;
        }

        // 🚀 دریافت آیدی یا پیام فوروارد شده برای افزودن ادمین
        if ($state === 'admin_wait_add_admin') {
            $this->processAddAdmin();
            return;
        }

        // 👈 این شرط را داخل handleActiveProcess قرار دهید
        if ($state === 'cart_wait_discount_code') {
            $this->processDiscountCode();
            return;
        }

    }

    protected function handleProductCreationSteps($state)
    {
        $tempProduct = $this->getSession('temp_product_data') ?? [];
        $promptMessageId = $this->getSession('message_id');

        // پاک کردن پیام ادمین برای تمیز ماندن صفحه چت
        if ($this->messageId && !isset($this->update['callback_query'])) {
            $this->deleteMessage($this->messageId);
        }

        switch ($state) {
            case 'admin_prod_step_name': // ⬅️ مرحله ۱: دریافت نام و پرسش قیمت
                $tempProduct['name'] = trim($this->text);
                $this->setSession('state', 'admin_prod_step_price');
                $this->setSession('temp_product_data', $tempProduct);

                $this->editMessageText("✅ نام محصول: <b>{$tempProduct['name']}</b>\n\n💰 مرحله ۲: حالا <b>قیمت</b> محصول را به تومان وارد کنید (فقط عدد):", [
                    'message_id' => $promptMessageId,
                    'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '🔙 لغو عملیات', 'callback_data' => 'admin_manage_products']]]])
                ]);
                break;

            case 'admin_prod_step_price': // ⬅️ مرحله ۲: دریافت قیمت و پرسش موجودی
                $price = str_replace([',', ''], '', trim($this->text));
                if (!is_numeric($price)) {
                    $this->sendMessage("❌ لطفاً برای قیمت فقط از اعداد استفاده کنید!");
                    return;
                }

                $tempProduct['price'] = $price;
                $this->setSession('state', 'admin_prod_step_stock'); // مرحله جدید
                $this->setSession('temp_product_data', $tempProduct);

                $this->editMessageText("✅ قیمت ثبت شد: <b>" . number_format($price) . " تومان</b>\n\n📦 مرحله ۳: لطفاً <b>تعداد موجودی</b> محصول در انبار را وارد کنید:", [
                    'message_id' => $promptMessageId,
                    'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '🔙 لغو عملیات', 'callback_data' => 'admin_manage_products']]]])
                ]);
                break;

            case 'admin_prod_step_stock': // ⬅️ مرحله جدید: دریافت موجودی و پرسش توضیحات
                $stock = trim($this->text);
                if (!is_numeric($stock) || $stock < 0) {
                    $this->sendMessage("❌ لطفاً عدد صحیح و مثبت برای موجودی وارد کنید!");
                    return;
                }

                $tempProduct['stock'] = (int) $stock;
                $this->setSession('state', 'admin_prod_step_desc'); // ادامه به مرحله توضیحات
                $this->setSession('temp_product_data', $tempProduct);

                $this->editMessageText("✅ موجودی ثبت شد: <b>{$stock} عدد</b>\n\n📝 مرحله ۴: لطفاً <b>توضیحات کامل</b> محصول را بنویسید:", [
                    'message_id' => $promptMessageId,
                    'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '🔙 لغو عملیات', 'callback_data' => 'admin_manage_products']]]])
                ]);
                break;

            case 'admin_prod_step_desc': // ⬅️ مرحله ۳: دریافت توضیحات و پرسش عکس
                if (empty(trim($this->text))) {
                    $this->sendMessage("❌ لطفاً توضیحات محصول را به صورت متنی ارسال کنید.");
                    return;
                }

                $tempProduct['description'] = trim($this->text);
                $tempProduct['photos'] = []; // 💡 یک آرایه خالی برای ذخیره لیست عکس‌ها ایجاد می‌کنیم

                $this->setSession('state', 'admin_prod_step_photo');
                $this->setSession('temp_product_data', $tempProduct);

                $this->editMessageText("✅ توضیحات ثبت شد.\n\n🖼 مرحله ۴: حالا <b>تصاویر محصول</b> را ارسال کنید (می‌توانید یک یا چند عکس بفرستید).\n\n<i>⚠️ نکته: اولین عکسی که بفرستید به عنوان تصویر اصلی (کاور) و بقیه به عنوان گالری محصول در نظر گرفته می‌شوند.\n\n👇 پس از ارسال تمام عکس‌ها، حتماً روی دکمه زیر کلیک کنید:</i>", [
                    'message_id' => $promptMessageId,
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [['text' => '✅ اتمام و انتخاب دسته‌بندی', 'callback_data' => 'admin_prod_finish_photos']],
                            [['text' => '🔙 لغو عملیات', 'callback_data' => 'admin_manage_products']]
                        ]
                    ])
                ]);
                break;

            case 'admin_prod_step_photo': // ⬅️ مرحله ۴: دریافت عکس‌ها (حلقه تکرارشونده)
                $photo = $this->update['message']['photo'] ?? null;

                if (!$photo) {
                    $this->sendMessage("❌ لطفاً فقط فرمت عکس ارسال کنید!");
                    return;
                }

                // برداشتن با کیفیت‌ترین سایز عکس و افزودن به آرایه
                $fileId = end($photo)['file_id'];
                $tempProduct['photos'][] = $fileId;
                $this->setSession('temp_product_data', $tempProduct);

                $photosCount = count($tempProduct['photos']);

                // 💡 آپدیت کردن پیام شیشه‌ای برای نمایش تعداد عکس‌های دریافت شده
                // از try-catch استفاده میکنیم تا اگر کاربر آلبوم فرستاد و پیام همزمان آپدیت شد، ربات ارور 400 ندهد
                try {
                    $this->editMessageText("✅ عکس دریافت شد! (تعداد تا الان: <b>{$photosCount}</b> عکس)\n\n<i>عکس‌های بعدی را بفرستید و در نهایت روی «اتمام» کلیک کنید.</i>", [
                        'message_id' => $promptMessageId,
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [
                                [['text' => '✅ اتمام و انتخاب دسته‌بندی', 'callback_data' => 'admin_prod_finish_photos']],
                                [['text' => '🔙 لغو عملیات', 'callback_data' => 'admin_manage_products']]
                            ]
                        ])
                    ]);
                } catch (\Exception $e) {
                    // نادیده گرفتن خطای آپدیت تکراری در تلگرام
                }
                break;
        }
    }

    /**
     * پردازش ورودی کاربر برای ویرایش مشخصات محصول
     */
    protected function processAdminProductEdit($state)
    {
        $payload = str_replace('admin_wait_prod_', '', $state);
        $parts = explode('_', $payload);
        $field = $parts[0];
        $productId = $parts[1];

        $product = Product::find($productId);
        if (!$product)
            return;

        $promptMessageId = $this->getSession('message_id');

        if ($this->messageId && !isset($this->update['callback_query'])) {
            $this->deleteMessage($this->messageId);
        }

        $textInput = trim($this->text);

        switch ($field) {
            case 'name':
                $product->name = $textInput;
                break;
            case 'sku':
                $product->sku = ($textInput === 'حذف') ? null : $textInput;
                break;
            case 'price':
                $val = str_replace([',', '،'], '', $textInput);
                if (!is_numeric($val) || $val < 0) {
                    $this->sendMessage("❌ لطفاً فقط عدد معتبر وارد کنید!");
                    return;
                }
                $product->price = $val;
                break;
            case 'discountpercent':
                // پاک کردن علامت‌های اضافی احتمالی از ورودی
                $percent = str_replace(['%', '٪', ' '], '', $textInput);

                if (!is_numeric($percent) || $percent < 0 || $percent >= 100) {
                    $this->sendMessage("❌ لطفاً یک درصد معتبر (بین 0 تا 99) وارد کنید!");
                    return;
                }

                if ($percent == 0) {
                    // اگر عدد 0 بود، تخفیف فوراً حذف شده و در دیتابیس ذخیره می‌شود
                    $product->discount_price = null;
                    $product->discount_expires_at = null;
                } else {
                    // 🧮 محاسبه قیمت جدید پس از کسر درصد تخفیف
                    $discountPrice = (int) ($product->price - ($product->price * ($percent / 100)));

                    // ذخیره موقت قیمت محاسبه‌شده در سشن و رفتن به مرحله بعد
                    $this->setSession('temp_discount_price', $discountPrice);
                    $this->setSession('state', "admin_wait_prod_discountdays_{$product->id}");

                    $markup = json_encode(['inline_keyboard' => [[['text' => '🔙 لغو و بازگشت', 'callback_data' => "admin_prod_manage_{$product->id}"]]]]);

                    $text = "✅ تخفیف <b>{$percent}٪</b> با موفقیت اعمال شد.\n";
                    $text .= "مبلغ فروش محصول: <b>" . number_format($discountPrice) . " تومان</b>\n\n";
                    $text .= "⏳ حالا <b>تعداد روزهای اعتبار</b> این تخفیف را ارسال کنید:\n";
                    $text .= "<i>(اگر می‌خواهید این تخفیف همیشگی باشد، عدد <code>0</code> را بفرستید)</i>";

                    $this->editMessageText($text, [
                        'message_id' => $promptMessageId,
                        'reply_markup' => $markup,

                    ]);

                    return;
                }
                break;

            case 'discountdays':
                $days = str_replace(' ', '', $textInput);
                if (!is_numeric($days) || $days < 0) {
                    $this->sendMessage("❌ لطفاً تعداد روزها را فقط با عدد صحیح و مثبت وارد کنید!");
                    return;
                }

                // خواندن قیمتِ محاسبه‌شده از مرحله قبل
                $product->discount_price = $this->getSession('temp_discount_price');

                // ثبت تاریخ انقضا (اگر بزرگتر از 0 بود تاریخ می‌خورد، وگرنه null یعنی دائمی)
                $product->discount_expires_at = ($days > 0)
                    ? now()->addDays((int) $days)->endOfDay()->toDateTimeString()
                    : null;

                // پاکسازی حافظه موقت
                $this->setSession('temp_discount_price', null);
                break;

            case 'stock':
                if (!is_numeric($textInput) || $textInput < 0) {
                    $this->sendMessage("❌ لطفاً عدد صحیح وارد کنید!");
                    return;
                }
                $product->stock = (int) $textInput;
                break;

            case 'desc':
                $product->description = ($textInput === 'حذف') ? null : $textInput;
                break;

            case 'addphoto':
                $photo = $this->update['message']['photo'] ?? null;
                if (!$photo) {
                    $this->sendMessage("❌ لطفاً یک تصویر (عکس) ارسال کنید!");
                    return;
                }

                $fileId = end($photo)['file_id'];

                // پیدا کردن بالاترین sort_order برای اینکه عکس جدید به آخر لیست اضافه شود
                $maxSort = \App\Models\ProductImage::where('product_id', $product->id)->max('sort_order');
                $nextIndex = ($maxSort !== null) ? $maxSort + 1 : 0;

                // 🚀 ارسال به Job قدرتمند شما (با حفظ ایندکس برای مرتب‌سازی درست)
                $photosArray = [$nextIndex => $fileId];
                \App\Jobs\ProcessProductImagesJob::dispatch($product->id, $photosArray, $this->bot->bot_token);

                $res = $this->sendMessage("✅ تصویر دریافت شد و به لیست اضافه گردید.\n<i>(چون در پس‌زمینه آپلود می‌شود ممکن است چند ثانیه طول بکشد)</i>");

                // اضافه کردن آیدی پیامِ تاییدیه به لیست حذف‌شونده‌ها
                $tempMsgs = $this->getSession('temp_photo_msgs') ?? [];
                if (isset($res['result']['message_id'])) {
                    $tempMsgs[] = $res['result']['message_id'];
                    $this->setSession('temp_photo_msgs', $tempMsgs);
                }

                return; // ⛔️ توقف. استیت پاک نمی‌شود تا ادمین بتواند پشت سر هم عکس بفرستد
        }

        $product->save();

        $this->setSession('state', null);
        $this->setSession('message_id', null);

        // 📢 آپدیت کانال با استفاده از کلاس سرویس قدرتمند خودتان
        \App\Services\TelegramProductService::updateChannelPost($product);

        if ($promptMessageId) {
            $this->messageId = $promptMessageId;
            $this->update['callback_query'] = ['message' => ['message_id' => $promptMessageId]];
        }
        $this->handleAdminProductManage($productId);
    }

    /**
     * پردازش ورودی کاربر برای ثبت آدرس و شماره تماس
     */
    protected function processCheckoutAddress($state)
    {
        $promptMessageId = $this->getSession('message_id');

        // پاک کردن پیام متنی کاربر برای تمیز ماندن چت ربات
        if ($this->messageId && !isset($this->update['callback_query'])) {
            $this->deleteMessage($this->messageId);
        }

        $textInput = trim($this->text);

        // مرحله اول: دریافت متن آدرس
        if ($state === 'cart_wait_address_text') {
            if (strlen($textInput) < 10) {
                $this->sendMessage("❌ آدرس وارد شده خیلی کوتاه است. لطفاً آدرس دقیق‌تری وارد کنید:");
                return;
            }

            $this->setSession('temp_checkout_address', $textInput);
            $this->setSession('state', 'cart_wait_address_phone');

            $markup = json_encode(['inline_keyboard' => [[['text' => '🔙 انصراف و بازگشت به سبد', 'callback_data' => 'view_cart']]]]);

            if ($promptMessageId) {
                $this->editMessageText("📍 <b>آدرس با موفقیت ثبت شد.</b>\n\n📞 <b>حالا لطفاً شماره تماس (موبایل) گیرنده را وارد کنید:</b>\n<i>(مثال: 09123456789)</i>", [
                    'message_id' => $promptMessageId,
                    'reply_markup' => $markup,

                ]);
            }
            return;
        }

        // مرحله دوم: دریافت شماره تماس و ذخیره نهایی
        if ($state === 'cart_wait_address_phone') {
            if (!preg_match('/^[0-9۰-۹]{10,14}$/u', $textInput)) {
                $this->sendMessage("❌ لطفاً یک شماره موبایل معتبر وارد کنید!");
                return;
            }

            $addressText = $this->getSession('temp_checkout_address');

            $newAddress = \App\Models\UserAddress::create([
                'bot_id' => $this->bot->id,
                'user_id' => $this->user->id,
                'address' => $addressText,
                'phone' => $textInput,
            ]);
            // پاکسازی سشن‌های موقت ثبت نام
            $this->setSession('state', null);
            $this->setSession('temp_checkout_address', null);

            // 💡 آدرس جدید را به عنوان آدرس انتخاب شده برای این خرید ثبت می‌کنیم
            $this->setSession('checkout_address_id', $newAddress->id);

            // انتقال پیام ادمین و بازگشت به سبد خرید
            if ($promptMessageId) {
                $this->messageId = $promptMessageId;
                $this->update['callback_query'] = ['message' => ['message_id' => $promptMessageId]];
            }

            // 🔙 بازگشت مستقیم به سبد خرید که حالا آدرس دارد
            $this->showCart();
            return;
        }
    }

    /**
     * 🧾 نمایش جزئیات دقیق یک سفارش به ادمین (نسخه اصلاح شده با <br> و جدول دو ستونه)
     */
    protected function adminShowOrderDetails($invoiceId)
    {
        if ($this->user->is_admin != 1) {
            $this->sendRequest('answerCallbackQuery', ['callback_query_id' => $this->update['callback_query']['id'], 'text' => "⛔ شما به پنل مدیریت دسترسی ندارید.", 'show_alert' => true]);
            return;
        }

        $invoice = \App\Models\Invoice::with(['items', 'user'])->find($invoiceId);
        if (!$invoice)
            return;

        if ($invoice->status === 'paid') {
            $invoice->update(['status' => 'processing']);
        }

        $statusText = $this->getStatusText($invoice->status);
        $statusIcon = $this->getStatusIcon($invoice->status);

        $addressId = $invoice->user_info['address_id'] ?? null;
        $address = $addressId ? \App\Models\UserAddress::find($addressId) : null;

        $shippingCost = $invoice->user_info['shipping_cost'] ?? 0;
        $productDiscount = $invoice->user_info['product_discount'] ?? 0;
        $couponDiscount = $invoice->user_info['coupon_discount'] ?? 0;

        $finalAmount = $invoice->total_amount - $invoice->discount_amount + $shippingCost - $invoice->wallet_amount_used;
        $date = \Morilog\Jalali\Jalalian::fromDateTime($invoice->created_at)->format('Y/m/d ساعت H:i');

        $html = "🧾 <b>جزئیات سفارش <code>{$invoice->tracking_code}</code></b><br>";
        $html .= "🚦 <b>وضعیت:</b> {$statusIcon} {$statusText}<br>";
        $html .= "🗓 <b>تاریخ ثبت:</b> {$date}<br><br>";

        $html .= "<details open><summary>👤 <b>اطلاعات گیرنده</b></summary>";
        $html .= "▫️ نام: " . ($invoice->user->first_name ?? 'بدون نام') . " " . ($invoice->user->last_name ?? '') . " (@" . ($invoice->user->username ?? 'ندارد') . ")<br>";
        $html .= "▫️ تلفن: " . ($address ? $address->phone : 'ندارد') . "\u{200f}<br>";
        $html .= "▫️ آدرس: " . ($address ? $address->address : 'ندارد') . "<br>";
        $html .= "</details><br><br>";

        $html .= "🛒 <b>اقلام سفارش:</b><br>";
        $html .= "<table bordered striped compact>";
        $html .= "<tr><th align=\"right\">نام محصول</th><th align=\"center\">تعداد</th></tr>";
        foreach ($invoice->items as $item) {
            $html .= "<tr><td align=\"right\">{$item->name_at_purchase}</td><td align=\"center\"><b>{$item->quantity}</b></td></tr>";
        }
        $html .= "</table><br><br>";

        // 💰 تفکیک شفاف مالی برای ادمین
        $html .= "<details open><summary>💰 <b>جزئیات مالی</b></summary>";
        $html .= "▫️ جمع قیمت اصلی کالاها: " . number_format($invoice->total_amount) . " T<br>";

        if ($productDiscount > 0 || $couponDiscount > 0) {
            if ($productDiscount > 0) {
                $html .= "🎁 تخفیف کالاها: " . number_format($productDiscount) . " T<br>";
            }
            if ($couponDiscount > 0) {
                $html .= "🎟 کد تخفیف <code>({$invoice->applied_discount_code})</code>: " . number_format($couponDiscount) . " T<br>";
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
        $html .= "👇 <b>برای تغییر وضعیت سفارش کلیک کنید:</b>";

        $keyboard = [];
        $makeStatusBtn = function ($label, $statusKey) use ($invoice) {
            if ($invoice->status === $statusKey) {
                return ['text' => "✅ {$label}", 'callback_data' => 'ignore_cb', 'disabled' => true];
            }
            // 👈 اگر دکمه مربوط به لغو بود، به جای تغییر مستقیم، به صفحه هشدار هدایت شود
            if ($statusKey === 'canceled') {
                return ['text' => $label, 'callback_data' => "admin_confirm_cancel_{$invoice->id}"];
            }
            return ['text' => $label, 'callback_data' => "admin_change_status_{$invoice->id}_{$statusKey}"];
        };

        $keyboard[] = [$makeStatusBtn('📦 آماده‌سازی', 'processing'), $makeStatusBtn('🚚 ارسال شد', 'shipped')];
        $keyboard[] = [$makeStatusBtn('✅ تحویل داده شد', 'delivered'), $makeStatusBtn('❌ لغو سفارش', 'canceled')];

        if ($invoice->receipt_file_id) {
            $keyboard[] = [['text' => '📥 دانلود فیش / فاکتور PDF', 'callback_data' => "dl_receipt_{$invoice->id}"]];
        }
        $keyboard[] = [['text' => '🔙 بازگشت به لیست سفارشات', 'callback_data' => 'admin_manage_orders']];

        if ($this->messageId)
            $this->deleteMessage($this->messageId);

        Http::post("https://api.telegram.org/bot{$this->bot->bot_token}/sendRichMessage", [
            'chat_id' => $this->chatId,
            'rich_message' => ['html' => $html],
            'reply_markup' => ['inline_keyboard' => $keyboard],
            'disable_notification' => true
        ]);
    }

    /**
     * ⚠️ صفحه هشدار قبل از لغو قطعی سفارش و استرداد وجه (برای ادمین)
     */
    protected function adminConfirmCancelOrder($invoiceId)
    {
        if ($this->user->is_admin != 1)
            return;

        $invoice = \App\Models\Invoice::find($invoiceId);
        if (!$invoice)
            return;

        // محاسبه مبلغی که قرار است استرداد شود
        $shippingCost = $invoice->user_info['shipping_cost'] ?? 0;
        $walletUsed = $invoice->wallet_amount_used ?? 0;
        $cashPayable = $invoice->total_amount - $invoice->discount_amount + $shippingCost - $walletUsed;

        $refundAmount = 0;
        // اگر مشتری پول را داده (از درگاه یا فیش تایید شده)، کل پول به کیف پولش برمی‌گردد
        if (in_array($invoice->status, ['paid', 'processing', 'shipped'])) {
            $refundAmount = $walletUsed + $cashPayable;
        } else {
            // اگر فاکتور پرداخت نشده یا معلق است، فقط پولی که از کیف پولش بلاک شده برمی‌گردد
            $refundAmount = $walletUsed;
        }

        $html = "⚠️ <b>هشدار مهم (عملیات غیرقابل بازگشت)</b><br><br>";
        $html .= "آیا از لغو سفارش <code>{$invoice->tracking_code}</code> اطمینان دارید؟<br><br>";
        $html .= "🔹 <b>با انجام این کار:</b><br>";
        $html .= "۱. وضعیت فاکتور به «لغو شده» تغییر می‌کند.<br>";
        $html .= "۲. اقلام سفارش مجدداً به موجودی انبار برمی‌گردند.<br>";

        if ($refundAmount > 0) {
            $html .= "۳. مبلغ <b>" . number_format($refundAmount) . " تومان</b> به صورت خودکار به کیف پول مشتری واریز می‌شود.<br>";
        } else {
            $html .= "۳. چون این فاکتور پولی پرداخت نکرده است، وجهی استرداد نمی‌شود.<br>";
        }

        $keyboard = [
            [['text' => '❌ بله، لغو و استرداد وجه', 'callback_data' => "admin_change_status_{$invoice->id}_canceled"]],
            [['text' => '🔙 خیر، انصراف', 'callback_data' => "admin_view_order_{$invoice->id}"]]
        ];

        if ($this->messageId)
            $this->deleteMessage($this->messageId);

        \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$this->bot->bot_token}/sendRichMessage", [
            'chat_id' => $this->chatId,
            'rich_message' => ['html' => $html],
            'reply_markup' => ['inline_keyboard' => $keyboard],
            'disable_notification' => true
        ]);
    }

    /**
     * 🔄 تغییر وضعیت سفارش توسط ادمین (همراه با مدیریت انبار و استرداد وجه)
     */
    protected function adminChangeOrderStatus($invoiceId, $newStatus)
    {
        if ($this->user->is_admin != 1)
            return;

        $invoice = \App\Models\Invoice::with('items')->find($invoiceId);
        if (!$invoice)
            return;

        // ۱. بررسی وضعیت‌های ممنوعه
        if ($invoice->status === 'pending') {
            $this->sendRequest('answerCallbackQuery', [
                'callback_query_id' => $this->update['callback_query']['id'],
                'text' => "⛔️ این سفارش هنوز پرداخت نشده است! شما نمی‌توانید وضعیت فاکتورهای پرداخت‌نشده را تغییر دهید.",
                'show_alert' => true
            ]);
            return;
        }

        if ($invoice->status === 'canceled') {
            $this->sendRequest('answerCallbackQuery', [
                'callback_query_id' => $this->update['callback_query']['id'],
                'text' => "⛔️ این سفارش قبلاً لغو شده و موجودی آن به انبار و کیف پول مشتری برگشته است.",
                'show_alert' => true
            ]);
            return;
        }

        if ($invoice->status === 'delivered') {
            $this->sendRequest('answerCallbackQuery', [
                'callback_query_id' => $this->update['callback_query']['id'],
                'text' => "✅ این سفارش تحویل مشتری داده شده است. پرونده بسته شده و قابل تغییر نیست.",
                'show_alert' => true
            ]);
            return;
        }

        if ($invoice->status === $newStatus)
            return;

        // =========================================================
        // 🔄 مرحله دوم: مدیریت انبار و استرداد وجه در صورت لغو سفارش
        // =========================================================
        if ($newStatus === 'canceled') {

            // ۱. بازگشت موجودی کالا به انبار
            foreach ($invoice->items as $item) {
                if ($item->variant_id) {
                    \App\Models\ProductVariant::where('id', $item->variant_id)->increment('stock', $item->quantity);
                } elseif ($item->product_id) {
                    \App\Models\Product::where('id', $item->product_id)->increment('stock', $item->quantity);
                }
            }

            // ۲. محاسبه دقیق مبلغ استرداد
            $shippingCost = $invoice->user_info['shipping_cost'] ?? 0;
            $walletUsed = $invoice->wallet_amount_used ?? 0;
            $cashPayable = $invoice->total_amount - $invoice->discount_amount + $shippingCost - $walletUsed;

            $refundAmount = 0;
            if (in_array($invoice->status, ['paid', 'processing', 'shipped'])) {
                $refundAmount = $walletUsed + $cashPayable; // کل پولی که داده (نقدی + کیف پول)
            } else {
                $refundAmount = $walletUsed; // فقط پولی که از کیف پولش قفل شده بود
            }

            // ۳. واریز پول به کیف پول و ثبت تراکنش
            if ($refundAmount > 0 && $invoice->user) {
                $invoice->user->modifyWallet(
                    $refundAmount, // 👈 مبلغ مثبت است (افزایش موجودی)
                    'refund',
                    "استرداد وجه بابت لغو سفارش {$invoice->tracking_code}",
                    $invoice->id
                );

                // ۴. اطلاع‌رسانی آنی به مشتری
                \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$this->bot->bot_token}/sendMessage", [
                    'chat_id' => $invoice->user->chat_id,
                    'text' => "💳 <b>استرداد وجه</b>\n\nمشتری عزیز، مبلغ <b>" . number_format($refundAmount) . " تومان</b> بابت لغو سفارش <code>{$invoice->tracking_code}</code> به کیف پول شما واریز شد.\nمی‌توانید در خریدهای بعدی از این موجودی استفاده کنید.",
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '👤 حساب کاربری (موجودی)', 'callback_data' => 'user_profile']]]])
                ]);
            }
        }

        // اعمال آپدیت وضعیت نهایی
        $invoice->update(['status' => $newStatus]);

        $this->sendRequest('answerCallbackQuery', [
            'callback_query_id' => $this->update['callback_query']['id'],
            'text' => "✅ وضعیت سفارش با موفقیت بروزرسانی شد.",
            'show_alert' => false
        ]);

        $this->adminShowOrderDetails($invoice->id);
    }
    // ================================================================
    // 👑 سیستم هوشمند مدیریت ادمین‌ها (RBAC)
    // ================================================================

    /**
     * تشخیص هوشمند مالک اصلی ربات بر اساس Chat ID
     */
    protected function isBotOwner()
    {
        // پیدا کردن اکانت اصلی مالک سایت از دیتابیس
        $siteOwner = \App\Models\User::find($this->bot->owner_id);

        // اگر چت آیدی کاربری که الان دکمه را زده با چت آیدی ثبت شده در سایت برابر بود، او مالک است!
        return ($siteOwner && $siteOwner->chat_id == $this->chatId);
    }

    protected function showAdminsList()
    {
        // گرفتن تمام کاربران این ربات که ادمین هستند
        $admins = \App\Models\User::where('bot_id', $this->bot->id)->where('is_admin', 1)->get();
        $isOwner = $this->isBotOwner();

        $text = "👥 <b>مدیریت ادمین‌های فروشگاه</b>\n\n";

        if ($isOwner) {
            $text .= "<i>👑 <b>شما مالک ربات هستید!</b> دسترسی کامل برای افزودن یا حذف ادمین‌ها را دارید.</i>\n\n";
        } else {
            $text .= "<i>⚠️ <b>سطح دسترسی شما: ادمین (محدود)</b>\nشما فقط مجاز به مشاهده این لیست هستید و نمی‌توانید ادمینی را حذف یا اضافه کنید.</i>\n\n";
        }

        $text .= "👇 لیست مدیران فعلی ربات:\n➖➖➖➖➖➖➖➖➖➖";

        $keyboard = [];

        foreach ($admins as $admin) {
            $adminName = trim($admin->first_name . ' ' . $admin->last_name) ?: 'بدون نام';
            $adminName .= $admin->username ? " (@{$admin->username})" : "";

            // پیدا کردن مالک برای زدن برچسب 👑 کنار اسمش
            $siteOwner = \App\Models\User::find($this->bot->owner_id);
            $thisIsOwner = ($siteOwner && $siteOwner->chat_id == $admin->chat_id);
            $role = $thisIsOwner ? '👑 مالک' : '👨‍💻 ادمین';

            if ($isOwner && !$thisIsOwner) {
                // اگر کاربر فعلی مالک است، دکمه «حذف» را برای بقیه (غیر از خودش) نمایش بده
                $keyboard[] = [
                    ['text' => "{$role}: {$adminName}", 'callback_data' => "ignore_cb"],
                    ['text' => '🗑 عزل', 'callback_data' => "admin_del_admin_{$admin->id}"]
                ];
            } else {
                // اگر کاربر فعلی ادمین معمولی است، فقط اسم‌ها را ببیند
                $keyboard[] = [
                    ['text' => "{$role}: {$adminName}", 'callback_data' => "ignore_cb"]
                ];
            }
        }

        // فقط مالک دکمه افزودن را می‌بیند
        if ($isOwner) {
            $keyboard[] = [['text' => '➕ افزودن ادمین جدید', 'callback_data' => 'admin_add_admin_prompt']];
        }

        $keyboard[] = [['text' => '🔙 بازگشت به داشبورد', 'callback_data' => 'admin_panel']];

        $this->editMessageText($text, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    protected function promptAddAdmin()
    {
        if (!$this->isBotOwner()) {
            $this->sendRequest('answerCallbackQuery', ['callback_query_id' => $this->update['callback_query']['id'], 'text' => "⛔️ این دسترسی مختص مالک ربات است.", 'show_alert' => true]);
            return;
        }

        // 🚀 بررسی محدودیت پلن (سقف مجاز تعداد ادمین‌ها)
        $maxAdmins = $this->bot->plan->limits['max_admins'] ?? 1; // اگر تنظیم نشده بود پیش‌فرض 1

        // اگر روی نامحدود (-1 یا unlimited) تنظیم نشده باشد، تعداد را می‌شماریم
        if ($maxAdmins !== 'unlimited' && $maxAdmins != -1) {
            $currentAdminsCount = User::where('bot_id', $this->bot->id)->where('is_admin', 1)->count();

            if ($currentAdminsCount >= $maxAdmins) {
                // نمایش یک پاپ‌آپ اخطار (Alert) روی شیشه بدون تغییر صفحه
                $this->sendRequest('answerCallbackQuery', [
                    'callback_query_id' => $this->update['callback_query']['id'],
                    'text' => "⛔️ محدودیت پلن!\n\nشما به سقف مجاز افزودن ادمین ({$maxAdmins} نفر) در پلن فعلی رسیده‌اید. برای افزودن ادمین جدید باید اشتراک خود را ارتقا دهید.",
                    'show_alert' => true
                ]);
                return; // توقف کامل فرآیند
            }
        }

        $text = "➕ <b>انتصاب ادمین جدید</b>\n\n";
        $text .= "⚠️ <b>شرط مهم:</b> شخص مورد نظر باید حداقل یک‌بار ربات شما را Start کرده باشد.\n\n";
        $text .= "👇 برای اضافه کردن ادمین، یکی از <b>سه روش</b> زیر را انجام دهید:\n";
        $text .= "۱. یک پیام از آن کاربر را به اینجا <b>Forward (ارسال مجدد)</b> کنید.\n";
        $text .= "۲. <b>یوزرنیم تلگرام</b> کاربر را بفرستید (مثال: <code>@username</code>).\n";
        $text .= "۳. <b>آیدی عددی (Chat ID)</b> کاربر را بفرستید.\n\n";
        $text .= "<i>(برای لغو عملیات روی دکمه زیر کلیک کنید)</i>";

        $keyboard = [[['text' => '🔙 لغو و بازگشت', 'callback_data' => 'admin_manage_admins']]];

        $this->setSession('state', 'admin_wait_add_admin');

        $promptMessageId = $this->update['callback_query']['message']['message_id'] ?? null;
        if ($promptMessageId) {
            $this->setSession('message_id', $promptMessageId);
        }

        $this->editMessageText($text, ['reply_markup' => json_encode(['inline_keyboard' => $keyboard])]);
    }

    protected function processAddAdmin()
    {
        $promptMessageId = $this->getSession('message_id');

        // پاک کردن پیام ادمین برای تمیزی چت ربات
        if ($this->messageId && !isset($this->update['callback_query'])) {
            $this->deleteMessage($this->messageId);
        }

        if (!$this->isBotOwner()) {
            $this->setSession('state', null);
            return;
        }

        $targetUser = null;

        // 💡 روش اول: جادوی UX با پشتیبانی از فوروارد کردن پیام کاربر
        if (isset($this->update['message']['forward_from'])) {
            $targetChatId = $this->update['message']['forward_from']['id'];
            $targetUser = User::where('bot_id', $this->bot->id)->where('chat_id', $targetChatId)->first();
        } else {
            $input = trim($this->text);

            // 💡 روش دوم: اگر آیدی عددی (Chat ID) بود
            if (is_numeric($input)) {
                $targetUser = User::where('bot_id', $this->bot->id)->where('chat_id', $input)->first();
            }
            // 💡 روش سوم: اگر یوزرنیم تلگرام (با یا بدون @) بود
            else {
                // با استفاده از ltrim کاراکتر @ را در صورت وجود حذف می‌کنیم تا با دیتابیس مچ شود
                $username = ltrim($input, '@');
                $targetUser = User::where('bot_id', $this->bot->id)->where('username', $username)->first();
            }
        }

        // بررسی اینکه آیا کاربر اصلاً در ربات عضو شده یا پیدا شده است؟
        if (!$targetUser) {
            $this->sendMessage("❌ <b>کاربر یافت نشد!</b>\nفرد مورد نظر هنوز در ربات شما عضو نشده یا یوزرنیم/آیدی اشتباه است. از او بخواهید ابتدا وارد ربات شده و /start را بزند.");
            return; // توقف فرآیند، ربات در همان state می‌ماند تا ادمین دوباره آیدی درستی بفرستد
        }

        if ($targetUser->is_admin) {
            $this->sendMessage("⚠️ <b>این کاربر در حال حاضر جزو ادمین‌ها می‌باشد!</b>");
        } else {
            // 🚀 لایه امنیتی نهایی: بررسی محدودیت پلن قبل از ذخیره در دیتابیس
            $maxAdmins = $this->bot->plan->limits['max_admins'] ?? 1;

            if ($maxAdmins !== 'unlimited' && $maxAdmins != -1) {
                $currentAdminsCount = \App\Models\User::where('bot_id', $this->bot->id)->where('is_admin', 1)->count();

                if ($currentAdminsCount >= $maxAdmins) {
                    $this->sendMessage("⛔️ <b>محدودیت پلن!</b>\n\nشما به سقف مجاز افزودن ادمین ({$maxAdmins} نفر) رسیده‌اید. لطفاً نسبت به ارتقای پلن خود اقدام کنید.");

                    // خارج کردن ربات از حالت انتظار آیدی
                    $this->setSession('state', null);
                    $this->setSession('message_id', null);
                    return;
                }
            }

            // اگر به محدودیت نرسیده بود، ادمین می‌شود:
            $targetUser->is_admin = 1;
            $targetUser->save();

            // 📢 ارسال پیام تبریک به ادمین جدید به صورت مستقیم
            Http::post("https://api.telegram.org/bot{$this->bot->bot_token}/sendMessage", [
                'chat_id' => $targetUser->chat_id,
                'text' => "🎉 <b>ارتقای سطح کاربری!</b>\n\nشما توسط مالک فروشگاه به عنوان <b>ادمین</b> منصوب شدید.\nبرای دسترسی به پنل مدیریت، لطفاً ربات را مجدداً <code>/start</code> کنید.",
                'parse_mode' => 'HTML'
            ]);

            $displayName = $targetUser->first_name ?? ($targetUser->username ? "@{$targetUser->username}" : "کاربر");
            $this->sendMessage("✅ دسترسی ادمین برای <b>{$displayName}</b> با موفقیت صادر شد.");
        }

        $this->setSession('state', null);
        $this->setSession('message_id', null);

        // بازگشت خودکار به صفحه لیست ادمین‌ها و رفرش شدن شیشه‌ای
        if ($promptMessageId) {
            $this->messageId = $promptMessageId;
            $this->update['callback_query'] = ['message' => ['message_id' => $promptMessageId]];
        }
        $this->showAdminsList();
    }

    protected function removeAdmin($adminId)
    {
        if (!$this->isBotOwner()) {
            $this->sendRequest('answerCallbackQuery', ['callback_query_id' => $this->update['callback_query']['id'], 'text' => "⛔️ فقط مالک اصلی حق عزل ادمین‌ها را دارد.", 'show_alert' => true]);
            return;
        }

        $adminUser = \App\Models\User::where('bot_id', $this->bot->id)->find($adminId);

        if (!$adminUser) {
            $this->sendRequest('answerCallbackQuery', ['callback_query_id' => $this->update['callback_query']['id'], 'text' => "❌ کاربر یافت نشد.", 'show_alert' => true]);
            return;
        }

        $adminUser->is_admin = 0;
        $adminUser->save();

        // 📢 اطلاع‌رسانی به ادمین عزل شده
        Http::post("https://api.telegram.org/bot{$this->bot->bot_token}/sendMessage", [
            'chat_id' => $adminUser->chat_id,
            'text' => "⚠️ <b>لغو دسترسی!</b>\n\nدسترسی مدیریت شما توسط مالک فروشگاه لغو گردید و حساب شما به حالت کاربر عادی تغییر یافت.",
            'parse_mode' => 'HTML'
        ]);

        $this->sendRequest('answerCallbackQuery', ['callback_query_id' => $this->update['callback_query']['id'], 'text' => "✅ کاربر از مدیریت خلع شد."]);
        $this->showAdminsList();
    }

    /**
     * درخواست ورود کد تخفیف از کاربر (با ادیت پیام فعلی)
     */
    protected function promptDiscountCode()
    {
        $this->setSession('state', 'cart_wait_discount_code');

        // استخراج و ذخیره آیدی پیام فاکتور (تا کاربر که کد رو فرستاد، همین پیام رو ادیت کنیم)
        $promptMessageId = $this->update['callback_query']['message']['message_id'] ?? null;
        if ($promptMessageId) {
            $this->setSession('message_id', $promptMessageId);
        }

        $text = "🎟 <b>اعمال کد تخفیف</b>\n\n";
        $text .= "لطفاً <b>کد تخفیف</b> خود را تایپ کرده و ارسال کنید:\n\n";
        $text .= "<i>(برای لغو این مرحله، روی دکمه زیر کلیک کنید)</i>";

        $keyboard = [
            [['text' => '🔙 انصراف و بازگشت به سبد خرید', 'callback_data' => 'view_cart']]
        ];

        // پیام فاکتور را به فرم دریافت کد تبدیل می‌کنیم
        $this->editMessageText($text, [
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
        ]);
    }

    /**
     * پردازش و اعتبارسنجی کد تخفیف وارد شده (بدون ارسال پیام جدید)
     */
    protected function processDiscountCode()
    {
        $code = trim($this->text);
        $promptMessageId = $this->getSession('message_id');

        // ۱. پاک کردن پیام متنی کاربر (کد ارسال شده) فوراً پس از دریافت برای تمیز ماندن چت
        if ($this->messageId && !isset($this->update['callback_query'])) {
            $this->deleteMessage($this->messageId);
        }

        $keyboard = [[['text' => '🔙 انصراف و بازگشت به سبد خرید', 'callback_data' => 'view_cart']]];

        // ۲. بررسی وجود و فعال بودن کد در دیتابیس
        $discount = \App\Models\DiscountCode::where('bot_id', $this->bot->id)
            ->where('code', $code)
            ->where('is_active', true)
            ->first();

        if (!$discount) {
            if ($promptMessageId) {
                $this->editMessageText("❌ <b>کد تخفیف «{$code}» نامعتبر است!</b>\n\nلطفاً کد صحیح را بررسی و دوباره ارسال کنید:", [
                    'message_id' => $promptMessageId,
                    'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
                ]);
            }
            return;
        }

        // ۳. بررسی تاریخ انقضا
        if ($discount->expires_at && $discount->expires_at->isPast()) {
            if ($promptMessageId) {
                $this->editMessageText("❌ <b>این کد تخفیف منقضی شده است!</b>\n\nدر صورت داشتن کد دیگر، آن را ارسال کنید:", [
                    'message_id' => $promptMessageId,
                    'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
                ]);
            }
            return;
        }

        // ۴. بررسی ظرفیت استفاده
        if ($discount->usage_limit && $discount->usage_count >= $discount->usage_limit) {
            if ($promptMessageId) {
                $this->editMessageText("❌ <b>ظرفیت استفاده از این کد به پایان رسیده است!</b>\n\nدر صورت داشتن کد دیگر، آن را ارسال کنید:", [
                    'message_id' => $promptMessageId,
                    'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
                ]);
            }
            return;
        }

        // ۵. محاسبه دقیق مبلغ سبد خرید برای مقایسه با حداقل مبلغ خرید مجاز کد
        $cartItems = \App\Models\Cart::where('bot_id', $this->bot->id)
            ->where('user_id', $this->user->id)
            ->with(['product', 'variant'])
            ->get();

        $cartTotal = 0;
        foreach ($cartItems as $cart) {
            $basePrice = $cart->product->price;
            $isProductDiscountValid = $cart->product->discount_price > 0 && (empty($cart->product->discount_expires_at) || \Carbon\Carbon::parse($cart->product->discount_expires_at)->isFuture());
            $itemDiscount = $isProductDiscountValid ? $cart->product->discount_price : 0;

            if ($cart->variant) {
                $basePrice = ($cart->variant->price > 0) ? $cart->variant->price : $basePrice;
                $varExpires = $cart->variant->discount_expires_at ?? $cart->product->discount_expires_at;
                $isVariantDiscountValid = $cart->variant->discount_price > 0 && (empty($varExpires) || \Carbon\Carbon::parse($varExpires)->isFuture());
                $itemDiscount = $isVariantDiscountValid ? $cart->variant->discount_price : $itemDiscount;
            }

            $finalPrice = ($itemDiscount > 0) ? $itemDiscount : $basePrice;
            $cartTotal += $finalPrice * $cart->quantity;
        }

        if ($discount->min_purchase_amount && $cartTotal < $discount->min_purchase_amount) {
            if ($promptMessageId) {
                $this->editMessageText("❌ <b>مبلغ سبد خرید کافی نیست!</b>\n\nبرای استفاده از این کد، حداقل مبلغ سبد خرید باید <b>" . number_format($discount->min_purchase_amount) . " تومان</b> باشد.\n\nکد دیگری دارید بفرستید:", [
                    'message_id' => $promptMessageId,
                    'reply_markup' => json_encode(['inline_keyboard' => $keyboard])
                ]);
            }
            return;
        }

        // ۶. اعمال موفقیت آمیز کد
        $this->setSession('applied_discount_code', $discount->code);

        // پاکسازی وضعیت ربات (خروج از حالت انتظار)
        $this->setSession('state', null);
        $this->setSession('message_id', null);

        // 🚀 تریک طلایی UX:
        // برای اینکه متد showCart پیام قبلی را به زیبایی (بدون ساخت پیام جدید و پرش صفحه) تبدیل به فاکتور نهایی کند،
        // متغیرهای کلاس را طوری تنظیم می‌کنیم که انگار کاربر روی دکمه شیشه‌ای کلیک کرده است!
        if ($promptMessageId) {
            $this->messageId = $promptMessageId;
            $this->update['callback_query'] = ['message' => ['message_id' => $promptMessageId]];
        }

        // رفرش سبد خرید (فاکتور دوباره روی همان پیام، اما این‌بار با اعمال تخفیف رندر می‌شود)
        $this->showCart();
    }
}
