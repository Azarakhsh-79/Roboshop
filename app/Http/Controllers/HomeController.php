<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Models\ManagedBot;
use App\Models\Plan;
use App\Models\Article;
use App\Models\OwnerVerification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\Setting;


class HomeController extends Controller
{
    // نمایش صفحه اصلی سایت
    public function index()
    {

        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();

        $blogPosts = Article::latest()->take(3)->get();

        return view('pages.home', compact('plans', 'blogPosts'));
    }
    // نمایش صفحه ساخت ربات (متد جدید)
    public function showCreateBotForm()
    {
        $user = auth()->user();

        // اگر توکن نداشت، یک توکن تصادفی برایش می‌سازیم
        if (empty($user->entry_token)) {
            $user->update([
                'entry_token' => Str::random(40)
            ]);
        }
        return view('bot.create');
    }

    // پردازش فرم ساخت ربات (اصلاح شده)
    public function registerBot(Request $request)
    {
        $request->validate([
            'bot_token' => 'required|string'
        ], [
            'bot_token.required' => 'لطفاً توکن ربات را وارد کنید.'
        ]);

        try {
            $token = $request->bot_token;

            // ۱. ارتباط با تلگرام و بررسی صحت توکن
            $response = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getMe");

            if (!$response->successful() || !$response->json('ok')) {
                return back()->withInput()->with('reg_error', 'توکن وارد شده نامعتبر است. لطفاً توکن صحیح را وارد کنید.');
            }

            $botData = $response->json('result');
            $botUsername = $botData['username'];
            $botIdString = (string) $botData['id'];

            // ۲. بررسی تکراری نبودن ربات
            if (ManagedBot::where('bot_id_string', $botIdString)->exists()) {
                return back()->withInput()->with('reg_error', 'این ربات قبلاً در سیستم ثبت شده است.');
            }

            // ۳. ثبت ربات در دیتابیس با وضعیت pending (در حال انتظار)
            $bot = ManagedBot::create([
                'owner_id' => Auth::id(),
                'bot_id_string' => $botIdString,
                'bot_token' => $token,
                'bot_name' => $botData['first_name'],
                'bot_username' => $botUsername,
                'webhook_secret' => Str::random(32),
                'status' => 'pending',
                'subscription_plan' => 'free',
                'subscription_expires_at' => now()->addMonth(),
            ]);

            // ۳.۵ 🚀 تزریق تنظیمات پیش‌فرض برای ربات جدید
            $defaultSettings = [
                [
                    'key' => 'store_name',
                    'description' => 'نام فروشگاه',
                    'value' => $botData['first_name'], // به طور پیش‌فرض نام خود ربات را می‌گذاریم
                    'type' => 'string',
                    'options' => null,
                ],
                [
                    'key' => 'is_active',
                    'description' => 'وضعیت فعالیت فروشگاه',
                    'value' => '1',
                    'type' => 'boolean',
                    'options' => null,
                ],
                [
                    'key' => 'welcome_message',
                    'description' => 'پیام خوش‌آمدگویی ربات',
                    'value' => "🎉 <b>به فروشگاه شما خوش آمدیم!</b>"
                        . "سلام {name} عزیز 👋"
                        . "از اینکه پلتفرم ما را برای راه‌اندازی فروشگاه خود انتخاب کردید، صمیمانه از شما سپاسگزاریم. ❤️"
                        . "فروشگاه <b>{bot_name}</b> با موفقیت راه‌اندازی شد و از این لحظه می‌توانید مدیریت فروشگاه خود را شروع کنید. 🚀"
                        . "⚙️ <b>اولین قدم شما:</b>"
                        . "ابتدا وارد بخش <b>تنظیمات فروشگاه</b> شوید و اطلاعات و تنظیمات فروشگاه خود را تکمیل کنید؛ از جمله نام فروشگاه، پیام خوش‌آمدگویی، اطلاعات پشتیبانی و سایر تنظیمات."
                        . "پس از تکمیل تنظیمات، می‌توانید محصولات خود را اضافه کنید و فروشگاهتان را برای مشتریان آماده کنید. 🛍️"
                        . "✨ <b>امیدواریم تجربه‌ای ساده و موفق در فروش آنلاین داشته باشید.</b>",

                    'type' => 'text',
                    'options' => null,
                ],
                [
                    'key' => 'support_phone',
                    'description' => 'شماره تماس پشتیبانی',
                    'value' => '',
                    'type' => 'string',
                    'options' => null,
                ],
                [
                    'key' => 'shipping_cost',
                    'description' => 'هزینه ارسال پیش‌فرض (تومان)',
                    'value' => '0',
                    'type' => 'number',
                    'options' => null,
                ],
                [
                    'key' => 'channel_id',
                    'description' => 'آیدی کانال تلگرام',
                    'value' => '',
                    'type' => 'string',
                    'options' => null,
                ],
                // 👇 تنظیماتی که اضافه شدند:
                [
                    'key' => 'show_discounted_products',
                    'description' => 'نمایش دکمه محصولات تخفیف‌دار در منو',
                    'value' => '1', // به صورت پیش‌فرض روشن باشد تا ظاهر منو جذاب‌تر شود
                    'type' => 'boolean',
                    'options' => null,
                ],
                [
                    'key' => 'support_text',
                    'description' => 'متن بخش پشتیبانی',
                    'value' => "🎧 برای پیگیری سفارشات یا طرح سوالات خود با ما در ارتباط باشید:",
                    'type' => 'text',
                    'options' => null,
                ],
                [
                    'key' => 'support_telegram_id',
                    'description' => 'آیدی تلگرام پشتیبانی (بدون @)',
                    'value' => '',
                    'type' => 'string',
                    'options' => null,
                ],
                [
                    'key' => 'bank_card_number',
                    'description' => 'شماره کارت بانکی (کارت به کارت)',
                    'value' => '',
                    'type' => 'string',
                    'options' => null,
                ],
                [
                    'key' => 'bank_card_name',
                    'description' => 'نام صاحب حساب',
                    'value' => '',
                    'type' => 'string',
                    'options' => null,
                ]
            ];



            // ذخیره تنظیمات در دیتابیس (با استفاده از foreach تا کستینگ آرایه options به درستی روی JSON اعمال شود)
            foreach ($defaultSettings as $setting) {
                Setting::create(array_merge($setting, ['bot_id' => $bot->id]));
            }

            // ۴. تنظیم وب‌هوک تلگرام
            $webhookUrl = url("/api/bot/shopbot/{$bot->webhook_secret}");

            // استفاده از متد POST برای ارسال امن توکن مخفی به تلگرام
            Http::post("https://api.telegram.org/bot{$token}/setWebhook", [
                'url' => $webhookUrl,
                'secret_token' => $bot->webhook_secret,
                'allowed_updates' => ['message', 'callback_query','inline_query' ]
            ]);

            // ۵. ساخت توکن یک‌بار‌مصرف برای ادمین
            $verifyToken = Str::random(20);

            OwnerVerification::create([
                'bot_id' => $bot->id,
                'token' => $verifyToken,
                'verification_status' => 'pending_usable'
            ]);

            // ۶. ایجاد لینک استارت تلگرام
            $userId = auth()->id(); // گرفتن آیدی کاربر لاگین شده
            $telegramLink = "https://t.me/{$botUsername}?start=verify_{$verifyToken}_{$userId}";

            // هدایت کاربر به دروازه پنل با لینک تلگرام
            return redirect()->route('panel.gateway')->with([
                'telegram_link' => $telegramLink,
                'reg_success' => 'ربات با موفقیت تایید شد!'
            ]);

        } catch (\Exception $e) {
            return back()->withInput()->with('reg_error', 'خطا در ارتباط با سرور: ' . $e->getMessage());
        }
    }

    // پردازش فرم بررسی وضعیت تمدید
    public function checkRenewal(Request $request)
    {
        $request->validate([
            'bot_identifier' => 'required|string'
        ], ['bot_identifier.required' => 'لطفاً شناسه ربات را وارد کنید.']);

        $botUsername = ltrim($request->bot_identifier, '@');

        // جایگزین کدهای PDO: استفاده از مدل لاراول
        $bot = ManagedBot::where('bot_username', $botUsername)->first();

        if ($bot) {
            return back()->with('renew_success', '✅ ربات پیدا شد! (درگاه پرداخت بزودی متصل می‌شود)');
        }

        return back()->with('renew_error', '❌ رباتی با این شناسه یافت نشد.');
    }
}
