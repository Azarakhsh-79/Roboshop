<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\ManagedBot;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\Invoice;
use Carbon\Carbon;

class SendDailyStoreReports extends Command
{
    protected $signature = 'shop:daily-intelligence';
    protected $description = 'دستیار هوشمند مدیریتی روبوشاپ (ارسال تحلیل، هشدارها و راهنمای تکمیل ویترین)';

    // ==========================================
    // ⚙️ تنظیمات دیباگ (Debug Mode)
    // ==========================================
    protected $debugMode = false;
    protected $demoBotUsername = 'Demo_RoboShop_bot';

    public function handle()
    {
        if ($this->debugMode) {
            Log::channel('cron')->info('========== Daily Intelligence DEBUG MODE START ==========');
            $this->info('Running in DEBUG MODE. Check cron.log for details.');
        }

        $reportDate = Carbon::yesterday();
        $previousDate = Carbon::yesterday()->subDay();

        $query = ManagedBot::where('status', 'active');

        if ($this->debugMode) {
            $query->where('bot_username', $this->demoBotUsername);
            Log::channel('cron')->info("Debug mode is ON. Only querying demo bot: {$this->demoBotUsername}");
        }

        $query->chunkById(50, function ($bots) use ($reportDate, $previousDate) {
            foreach ($bots as $bot) {
                if ($this->debugMode) {
                    Log::channel('cron')->info("Processing Bot ID: {$bot->id} | Name: {$bot->bot_name}");
                }
                $this->processBotIntelligence($bot, $reportDate, $previousDate);
            }
        });

        if ($this->debugMode) {
            Log::channel('cron')->info('========== Daily Intelligence DEBUG MODE FINISHED ==========');
        } else {
            $this->info('Daily Intelligence reports sent successfully to all active bots!');
        }
    }

    private function processBotIntelligence($bot, $reportDate, $previousDate)
    {
        try {
            $admins = User::where('bot_id', $bot->id)->where('is_admin', 1)->get();
            if ($admins->isEmpty()) {
                if ($this->debugMode) {
                    Log::channel('cron')->warning("Skipping Bot ID {$bot->id}: No admins found.");
                }
                return;
            }

            // ۱. شمارش دسته‌بندی‌ها و محصولات فعال
            $categoriesCount = Category::where('bot_id', $bot->id)
                ->where('is_active', true)
                ->count();

            $productsCount = Product::where('bot_id', $bot->id)
                ->where('is_active', true)
                ->count();

            // ۲. پیدا کردن دسته‌بندی‌های نهایی (بدون زیردسته فعال) که هیچ محصول فعالی ندارند
            $emptyCategories = Category::where('bot_id', $bot->id)
                ->where('is_active', true)
                ->whereDoesntHave('children', function ($q) {
                    $q->where('is_active', true);
                })
                ->whereDoesntHave('products', function ($q) {
                    $q->where('is_active', true);
                })
                ->get();

            $emptyCategoriesCount = $emptyCategories->count();

            if ($this->debugMode) {
                Log::channel('cron')->info("Bot ID {$bot->id} state -> Active Categories: {$categoriesCount}, Active Products: {$productsCount}, Empty Leaf Categories: {$emptyCategoriesCount}");
            }

            $messageText = "";
            $keyboard = ['inline_keyboard' => []];

            // =========================================================
            // 🟢 مرحله ۱ راه‌اندازی: بدون دسته‌بندی (پیشرفت ۱۰٪)
            // =========================================================
            if ($categoriesCount === 0) {
                if ($this->debugMode) Log::channel('cron')->info("Bot ID {$bot->id} -> Stage 1: Zero Categories.");

                $messageText = $this->getZeroCategoriesText($bot);
                $keyboard['inline_keyboard'][] = [['text' => '📁 ساخت اولین دسته‌بندی', 'callback_data' => 'admin_add_main_category']];
                $keyboard['inline_keyboard'][] = [['text' => '⚙️ ورود به داشبورد ربات', 'callback_data' => 'admin_panel']];
            }
            // =========================================================
            // 🟡 مرحله ۲ راه‌اندازی: کمتر از ۳ دسته‌بندی دارد (پیشرفت ۴۰٪)
            // =========================================================
            elseif ($categoriesCount < 3) {
                if ($this->debugMode) Log::channel('cron')->info("Bot ID {$bot->id} -> Stage 2: Less than 3 Categories ({$categoriesCount}).");

                $existingCategories = Category::where('bot_id', $bot->id)
                    ->where('is_active', true)
                    ->withCount(['products' => function ($q) {
                        $q->where('is_active', true);
                    }])
                    ->get();

                $messageText = $this->getFewCategoriesText($bot, $existingCategories, $productsCount);

                $keyboard['inline_keyboard'][] = [['text' => '➕ ساخت دسته‌بندی جدید', 'callback_data' => 'admin_add_main_category']];
                if ($productsCount === 0) {
                    $keyboard['inline_keyboard'][] = [['text' => '🛍 افزودن اولین محصول', 'callback_data' => 'admin_prod_add']];
                }
                $keyboard['inline_keyboard'][] = [['text' => '🗂 مدیریت دسته‌بندی‌ها', 'callback_data' => 'admin_manage_categories']];
                $keyboard['inline_keyboard'][] = [['text' => '⚙️ ورود به داشبورد ربات', 'callback_data' => 'admin_panel']];
            }
            // =========================================================
            // 🟠 مرحله ۳ راه‌اندازی: دسته‌بندی کافی دارد اما محصولی ندارد (پیشرفت ۶۰٪)
            // =========================================================
            elseif ($productsCount === 0) {
                if ($this->debugMode) Log::channel('cron')->info("Bot ID {$bot->id} -> Stage 3: Has >=3 Categories, but 0 Products.");

                $messageText = $this->getZeroProductsText($bot, $categoriesCount);
                $keyboard['inline_keyboard'][] = [['text' => '➕ افزودن اولین محصول', 'callback_data' => 'admin_prod_add']];
                $keyboard['inline_keyboard'][] = [['text' => '🗂 مشاهده دسته‌بندی‌ها', 'callback_data' => 'admin_manage_categories']];
                $keyboard['inline_keyboard'][] = [['text' => '⚙️ ورود به داشبورد ربات', 'callback_data' => 'admin_panel']];
            }
            // =========================================================
            // 🔵 مرحله ۴ راه‌اندازی: بعضی دسته‌بندی‌ها محصول ندارند (پیشرفت ۸۰٪)
            // =========================================================
            elseif ($emptyCategoriesCount > 0) {
                if ($this->debugMode) Log::channel('cron')->info("Bot ID {$bot->id} -> Stage 4: Has {$emptyCategoriesCount} Empty Categories.");

                // بررسی اینکه آیا دیروز فروشی هم داشته است یا خیر (تا اگر فروش داشته، در صدر پیام به او تبریک بگوییم)
                $reportStart = $reportDate->copy()->startOfDay();
                $reportEnd   = $reportDate->copy()->endOfDay();
                $yesterdayStats = Invoice::where('bot_id', $bot->id)
                    ->whereBetween('created_at', [$reportStart, $reportEnd])
                    ->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])
                    ->select(
                        DB::raw('COUNT(id) as total_orders'),
                        DB::raw('SUM(COALESCE(total_amount, 0) - COALESCE(discount_amount, 0)) as total_revenue')
                    )->first();

                $messageText = $this->getEmptyCategoriesText(
                    $bot,
                    $categoriesCount,
                    $productsCount,
                    $emptyCategories,
                    (int) $yesterdayStats->total_orders,
                    (int) $yesterdayStats->total_revenue
                );

                // ساخت دکمه‌های مستقیم برای ۳ دسته‌بندی خالی اول (هدایت مستقیم به صفحه مدیریت همان دسته)
                foreach ($emptyCategories->take(3) as $emptyCat) {
                    $keyboard['inline_keyboard'][] = [
                        ['text' => "📂 تکمیل یا مدیریت «{$emptyCat->name}»", 'callback_data' => "admin_cat_show_{$emptyCat->id}"]
                    ];
                }

                $keyboard['inline_keyboard'][] = [
                    ['text' => '➕ افزودن محصول جدید', 'callback_data' => 'admin_prod_add'],
                    ['text' => '🗂 همه دسته‌ها', 'callback_data' => 'admin_manage_categories']
                ];
                $keyboard['inline_keyboard'][] = [['text' => '⚙️ داشبورد مدیریت ربات', 'callback_data' => 'admin_panel']];
            }
            // =========================================================
            // 🚀 مرحله ۵: ویترین ۱۰۰٪ کامل است -> ارسال گزارش هوشمند روزانه
            // =========================================================
            else {
                if ($this->debugMode) Log::channel('cron')->info("Bot ID {$bot->id} -> Stage 5: Store 100% Ready. Triggering Daily Intelligence Report.");

                $reportStart = $reportDate->copy()->startOfDay();
                $reportEnd   = $reportDate->copy()->endOfDay();
                $prevStart   = $previousDate->copy()->startOfDay();
                $prevEnd     = $previousDate->copy()->endOfDay();

                $revenueQueryRaw = 'SUM(COALESCE(total_amount, 0) - COALESCE(discount_amount, 0)) as total_revenue';

                $yesterdayStats = Invoice::where('bot_id', $bot->id)
                    ->whereBetween('created_at', [$reportStart, $reportEnd])
                    ->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])
                    ->select(
                        DB::raw('COUNT(id) as total_orders'),
                        DB::raw($revenueQueryRaw)
                    )->first();

                $previousDayStats = Invoice::where('bot_id', $bot->id)
                    ->whereBetween('created_at', [$prevStart, $prevEnd])
                    ->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])
                    ->select(DB::raw($revenueQueryRaw))
                    ->first();

                $yesterdayRevenue = (int) $yesterdayStats->total_revenue;
                $previousRevenue  = (int) $previousDayStats->total_revenue;
                $ordersCount      = (int) $yesterdayStats->total_orders;

                $pendingOrdersCount = Invoice::where('bot_id', $bot->id)
                    ->where('status', 'awaiting_approval')
                    ->count();

                $newUsersCount = User::where('bot_id', $bot->id)
                    ->whereBetween('created_at', [$reportStart, $reportEnd])
                    ->count();

                $outOfStockCount = Product::where('bot_id', $bot->id)->where('is_active', true)->where('stock', '<=', 0)->count();
                $lowStockCount   = Product::where('bot_id', $bot->id)->where('is_active', true)->where('stock', '>', 0)->where('stock', '<=', 5)->count();

                if ($this->debugMode) {
                    Log::channel('cron')->info("Bot ID {$bot->id} Stats -> Revenue: {$yesterdayRevenue}, Orders: {$ordersCount}, Pending: {$pendingOrdersCount}, OutOfStock: {$outOfStockCount}, LowStock: {$lowStockCount}");
                }

                $storeNameSafe = htmlspecialchars($bot->bot_name ?? 'فروشگاه شما', ENT_QUOTES, 'UTF-8');
                $dateStr = \Morilog\Jalali\Jalalian::fromCarbon($reportDate)->format('%A d %B');

                $messageText = "🤖 <b>گزارش هوشمند روبوشاپ</b>\n\n";
                $messageText .= "🏪 شعبه: <b>{$storeNameSafe}</b>\n";
                $messageText .= "🗓 عملکرد: {$dateStr}\n";
                $messageText .= "━━━━━━━━━━━━━━\n\n";

                $messageText .= "💰 <b>فروش</b>\n";
                $messageText .= "<b>" . number_format($yesterdayRevenue) . " تومان</b>\n";

                if ($previousRevenue > 0) {
                    $diffPercent = round((($yesterdayRevenue - $previousRevenue) / $previousRevenue) * 100);
                    $trendIcon = $diffPercent >= 0 ? '📈' : '📉';
                    $trendSign = $diffPercent >= 0 ? '+' : '';
                    $messageText .= "{$trendIcon} {$trendSign}{$diffPercent}٪ نسبت به روز قبل\n\n";
                } elseif ($yesterdayRevenue > 0) {
                    $messageText .= "🚀 شروع قدرتمند نسبت به روز قبل\n\n";
                } else {
                    $messageText .= "\n";
                }

                $messageText .= "🛒 <b>سفارشات</b>\n";
                $messageText .= "▫️ {$ordersCount} سفارش موفق ثبت شد\n";
                if ($pendingOrdersCount > 0) {
                    $messageText .= "▫️ <b>{$pendingOrdersCount} فیش در انتظار بررسی 🔴</b>\n";
                }
                if ($ordersCount > 0) {
                    $avgOrder = $yesterdayRevenue / $ordersCount;
                    $messageText .= "▫️ میانگین مبلغ هر سفارش: " . number_format($avgOrder) . " ت\n";
                }
                $messageText .= "\n";

                $messageText .= "👥 <b>مشتریان</b>\n";
                $messageText .= "▫️ {$newUsersCount} مشتری جدید\n\n";

                $messageText .= "📦 <b>موجودی</b>\n";
                $messageText .= "▫️ {$productsCount} محصول فعال در {$categoriesCount} دسته‌بندی\n";
                if ($outOfStockCount > 0) {
                    $messageText .= "🔴 {$outOfStockCount} محصول ناموجود\n";
                }
                if ($lowStockCount > 0) {
                    $messageText .= "⚠️ {$lowStockCount} محصول کم‌موجودی\n";
                }

                $messageText .= "\n━━━━━━━━━━━━━━\n\n";
                $messageText .= "🧠 <b>تحلیل RoboShop:</b>\n\n";

                if ($ordersCount === 0) {
                    $messageText .= "💡 <i>دیروز سفارشی ثبت نشد. ویترین شما کاملاً آماده است؛ پیشنهاد می‌کنیم لینک فروشگاه را در استوری اینستاگرام یا کانال خود منتشر کنید. 🚀</i>\n";
                } else {
                    if ($yesterdayRevenue > $previousRevenue && $previousRevenue > 0) {
                        $messageText .= "🔥 <b>فروش خوبی داشتی!</b> فروش شما نسبت به روز قبل رشد داشته است.\n";
                    } elseif ($previousRevenue == 0 && $yesterdayRevenue > 0) {
                        $messageText .= "🔥 <b>عالی بود!</b> فروش‌های خوبی در سیستم ثبت شد.\n";
                    } else {
                        $messageText .= "✅ فروشگاه شما فعال است و سفارشات به صورت خودکار پردازش شدند.\n";
                    }
                }

                if ($outOfStockCount > 0 || $lowStockCount > 0) {
                    $messageText .= "\n💡 <b>پیشنهاد:</b>\nبرخی از محصولات شما رو به اتمام یا ناموجود است. پیشنهاد می‌کنیم موجودی آن‌ها را در انبار شارژ کنید.\n";
                }

                $messageText .= "\n━━━━━━━━━━━━━━\n";
                $messageText .= "👇 <i>اقدامات پیشنهادی:</i>";

                $stockIssuesCount = $outOfStockCount + $lowStockCount;

                if ($pendingOrdersCount > 0) {
                    $keyboard['inline_keyboard'][] = [['text' => "🔴 بررسی {$pendingOrdersCount} سفارش معلق", 'callback_data' => 'admin_manage_orders']];
                }

                if ($stockIssuesCount > 0) {
                    $keyboard['inline_keyboard'][] = [['text' => "⚠️ مدیریت {$stockIssuesCount} کالای کم‌موجودی/ناموجود", 'callback_data' => 'admin_manage_products']];
                }

                if ($pendingOrdersCount == 0 && $stockIssuesCount == 0) {
                    $keyboard['inline_keyboard'][] = [
                        ['text' => '📦 مدیریت سفارشات', 'callback_data' => 'admin_manage_orders'],
                        ['text' => '🗂 مدیریت محصولات', 'callback_data' => 'admin_manage_products']
                    ];
                }

                $keyboard['inline_keyboard'][] = [['text' => '⚙️ داشبورد مدیریت ربات', 'callback_data' => 'admin_panel']];
            }

            // ارسال پیام به تمام ادمین‌های ربات
            foreach ($admins as $admin) {
                $adminNameSafe = htmlspecialchars($admin->first_name ?? 'مدیر', ENT_QUOTES, 'UTF-8');
                $finalMessage = "سلام <b>{$adminNameSafe}</b> عزیز 👋\n\n" . $messageText;

                if ($this->debugMode) {
                    Log::channel('cron')->info("Bot ID {$bot->id} -> Sending message to Admin Chat ID: {$admin->chat_id}");
                }
                $this->sendTelegramMessage($bot->bot_token, $admin->chat_id, $finalMessage, $keyboard, $bot->id);
            }

        } catch (\Exception $e) {
            Log::channel('cron')->error("Intelligence Report Error for Bot ID {$bot->id}: " . $e->getMessage());
        }
    }

    // =========================================================================
    // 📝 متدهای تولید متن هوشمند برای ۴ مرحله راه‌اندازی (Onboarding)
    // =========================================================================

    /**
     * مرحله ۱: صفر دسته‌بندی (۱۰٪)
     */
    private function getZeroCategoriesText($bot): string
    {
        $storeNameSafe = htmlspecialchars($bot->bot_name ?? 'فروشگاه شما', ENT_QUOTES, 'UTF-8');

        $text = "🚀 <b>راهنمای راه‌اندازی شعبه مجازی «{$storeNameSafe}»</b>\n";
        $text .= "📊 پیشرفت راه‌اندازی: <code>[🟩⬜⬜⬜⬜] ۱۰٪</code>\n";
        $text .= "━━━━━━━━━━━━━━\n\n";

        $text .= "فروشگاه شما با موفقیت ساخته شده، اما در حال حاضر <b>مثل یک مغازه خالیِ بدون قفسه است!</b> 🛒\n";
        $text .= "وقتی مشتری وارد ربات شما می‌شود، هیچ دکمه‌ای برای تماشای محصولات نمی‌بیند.\n\n";

        $text .= "✅ <b>چک‌لیست راه‌اندازی فروشگاه:</b>\n";
        $text .= "☑️ ساخت ربات و اتصال اولیه\n";
        $text .= "⬜️ ایجاد حداقل ۳ دسته‌بندی اصلی <i>(گام فعلی شما 👈)</i>\n";
        $text .= "⬜️ افزودن محصولات به دسته‌بندی‌ها\n";
        $text .= "⬜️ شروع فروش ۲۴ ساعته\n\n";

        $text .= "💡 <b>چطور شروع کنم؟</b>\n";
        $text .= "همین الان روی دکمه زیر بزنید و اولین دسته‌بندی‌های فروشگاهتان را با یک ایموجی جذاب بسازید (مثلاً: 👕 تیشرت، 👖 شلوار، 👟 کفش).\n\n";
        $text .= "👇 <i>برای چیدن اولین قفسه فروشگاه کلیک کنید:</i>";

        return $text;
    }

    /**
     * مرحله ۲: کمتر از ۳ دسته‌بندی دارد (۴۰٪)
     */
    private function getFewCategoriesText($bot, $existingCategories, int $productsCount): string
    {
        $storeNameSafe = htmlspecialchars($bot->bot_name ?? 'فروشگاه شما', ENT_QUOTES, 'UTF-8');
        $catCount = $existingCategories->count();
        $remaining = 3 - $catCount;

        $text = "🛠️ <b>گزارش تکمیل ویترین «{$storeNameSafe}»</b>\n";
        $text .= "📊 پیشرفت راه‌اندازی: <code>[🟩🟩⬜⬜⬜] ۴۰٪</code>\n";
        $text .= "━━━━━━━━━━━━━━\n\n";

        $text .= "👏 <b>شروع خوبی داشتید!</b> شما تا الان <b>{$catCount} دسته‌بندی</b> در فروشگاه ثبت کرده‌اید:\n";
        foreach ($existingCategories as $cat) {
            $catNameSafe = htmlspecialchars($cat->name, ENT_QUOTES, 'UTF-8');
            $pCount = $cat->products_count;
            $statusBadge = $pCount > 0 ? "({$pCount} محصول ✅)" : "(بدون محصول ⚠️)";
            $text .= "▫️ 📁 <b>{$catNameSafe}</b> {$statusBadge}\n";
        }
        $text .= "\n";

        $text .= "🧠 <b>چرا داشتن حداقل ۳ دسته‌بندی مهم است؟</b>\n";
        $text .= "در منوی اصلی ربات، دسته‌بندی‌ها به صورت دکمه‌های دو ستونه به مشتری نمایش داده می‌شوند. وقتی فروشگاه فقط ۱ یا ۲ دسته‌بندی دارد، ویترین آن خلوت به نظر می‌رسد و اعتماد مشتری برای خرید کاهش می‌یابد.\n\n";

        $text .= "💡 <b>پیشنهاد روبوشاپ:</b>\n";
        $text .= "تنها <b>{$remaining} دسته‌بندی دیگر</b> بسازید تا ساختار منوی فروشگاه شما کاملاً استاندارد و حرفه‌ای شود (مثلاً دسته‌بندی بر اساس نوع کالا، برند یا اکسسوری).\n\n";
        $text .= "👇 <i>برای افزودن دسته‌بندی جدید روی دکمه زیر کلیک کنید:</i>";

        return $text;
    }

    /**
     * مرحله ۳: حداقل ۳ دسته دارد ولی هیچ محصولی ندارد (۶۰٪)
     */
    private function getZeroProductsText($bot, int $categoriesCount): string
    {
        $storeNameSafe = htmlspecialchars($bot->bot_name ?? 'فروشگاه شما', ENT_QUOTES, 'UTF-8');

        $text = "🎉 <b>قفسه‌های فروشگاه «{$storeNameSafe}» آماده است!</b>\n";
        $text .= "📊 پیشرفت راه‌اندازی: <code>[🟩🟩🟩⬜⬜] ۶۰٪</code>\n";
        $text .= "━━━━━━━━━━━━━━\n\n";

        $text .= "عالی بود! شما <b>{$categoriesCount} دسته‌بندی</b> ایجاد کرده‌اید و منوی فروشگاه شما ظاهر بسیار خوبی گرفته است.\n\n";
        $text .= "⚠️ <b>اما یک مشکل وجود دارد:</b>\n";
        $text .= "در حال حاضر تمام این دسته‌بندی‌ها خالی هستند و <b>هیچ محصولی (۰ کالا)</b> برای خرید وجود ندارد!\n\n";

        $text .= "💡 <b>قدم بعدی شما:</b>\n";
        $text .= "وقت آن است که اولین محصولات خود را (شامل عکس، نام و قیمت) وارد دسته‌بندی‌ها کنید تا ربات بتواند به صورت اتوماتیک از مشتریان سفارش بگیرد و فاکتور صادر کند. 💸\n\n";
        $text .= "👇 <i>همین حالا اولین محصول را در کمتر از ۲ دقیقه ثبت کنید:</i>";

        return $text;
    }

    /**
     * مرحله ۴: برخی دسته‌بندی‌های نهایی هیچ محصولی ندارند (۸۰٪)
     */
    private function getEmptyCategoriesText($bot, int $categoriesCount, int $productsCount, $emptyCategories, int $ordersCount, int $yesterdayRevenue): string
    {
        $storeNameSafe = htmlspecialchars($bot->bot_name ?? 'فروشگاه شما', ENT_QUOTES, 'UTF-8');
        $emptyCount = $emptyCategories->count();

        $text = "🔍 <b>بررسی هوشمند ویترین «{$storeNameSafe}»</b>\n";
        $text .= "📊 تکمیل ویترین: <code>[🟩🟩🟩🟩⬜] ۸۰٪</code>\n";
        $text .= "━━━━━━━━━━━━━━\n\n";

        // اگر فروشگاه دیروز فروشی هم داشته، آن را در بالا گزارش می‌دهیم تا از دست نرود
        if ($ordersCount > 0) {
            $text .= "💰 <b>عملکرد دیروز:</b> " . number_format($yesterdayRevenue) . " تومان ({$ordersCount} سفارش موفق)\n";
            $text .= "━━━━━━━━━━━━━━\n\n";
        }

        $text .= "📦 شما در حال حاضر <b>{$productsCount} محصول فعال</b> و <b>{$categoriesCount} دسته‌بندی</b> دارید؛ اما بررسی هوشمند روبوشاپ نشان می‌دهد که <b>{$emptyCount} دسته‌بندی شما کاملاً خالی است!</b>\n\n";

        $text .= "⚠️ <b>دسته‌بندی‌های بدون محصول:</b>\n";
        foreach ($emptyCategories->take(6) as $emptyCat) {
            $catNameSafe = htmlspecialchars($emptyCat->name, ENT_QUOTES, 'UTF-8');
            $text .= "▫️ 🔴 <b>{$catNameSafe}</b> <i>(۰ محصول)</i>\n";
        }
        if ($emptyCount > 6) {
            $more = $emptyCount - 6;
            $text .= "▫️ <i>و {$more} دسته‌بندی خالی دیگر...</i>\n";
        }
        $text .= "\n";

        $text .= "🧠 <b>چرا باید این مورد را برطرف کنید؟</b>\n";
        $text .= "این دسته‌ها هم‌اکنون در منوی مشتریان نمایش داده می‌شوند. وقتی مشتری با هیجان روی آن‌ها کلیک می‌کند، با پیام <i>«محصولی در این بخش قرار نگرفته است»</i> روبرو می‌شود که حس بدی به مشتری می‌دهد.\n\n";

        $text .= "💡 <b>راه‌حل سریع (یکی را انتخاب کنید):</b>\n";
        $text .= "۱️⃣ روی دکمه هر دسته در زیر کلیک کنید و <b>اولین محصول</b> آن را اضافه کنید.\n";
        $text .= "۲️⃣ اگر فعلاً کالایی برای آن دسته ندارید، وارد دسته شوید و دکمه <b>«🔴 غیرفعال کردن»</b> را بزنید تا موقتاً از منوی مشتریان مخفی شود.\n\n";
        $text .= "👇 <i>برای مدیریت مستقیم دسته‌های خالی کلیک کنید:</i>";

        return $text;
    }

    private function sendTelegramMessage($token, $chatId, $text, $keyboard, $botId)
    {
        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if (!empty($keyboard['inline_keyboard'])) {
            $payload['reply_markup'] = json_encode($keyboard);
        }

        $response = Http::timeout(10)
            ->retry(3, 1000)
            ->post($url, $payload);

        if (!$response->successful()) {
            Log::channel('cron')->warning("Telegram daily intelligence failed for Chat ID {$chatId}", [
                'bot_id' => $botId,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
        } elseif ($this->debugMode) {
            Log::channel('cron')->info("Bot ID {$botId} -> Successfully sent Telegram message to {$chatId}");
        }
    }
}
