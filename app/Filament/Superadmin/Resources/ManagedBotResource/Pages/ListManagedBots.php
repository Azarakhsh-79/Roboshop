<?php

namespace App\Filament\Superadmin\Resources\ManagedBotResource\Pages;

use App\Filament\Superadmin\Resources\ManagedBotResource;
use App\Models\ManagedBot;
use App\Models\Setting;
use App\Models\User;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ListManagedBots extends ListRecords
{
    protected static string $resource = ManagedBotResource::class;

    protected function getHeaderActions(): array
    {
        return [

            // ============================================================
            // 🔄 همگام‌سازی تمام ربات‌ها
            // ============================================================
            Actions\Action::make('sync_all_bots')
                ->label('🔄 همگام‌سازی تمام ربات‌ها')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('به‌روزرسانی تنظیمات و کامندهای تمام ربات‌ها')
                ->modalDescription(
                    'آیا مطمئن هستید؟ این عملیات تنظیمات جدید را به تمام ربات‌ها اضافه می‌کند و منوی تلگرام آن‌ها را به‌روزرسانی می‌کند. تنظیمات قبلی مدیران دست‌نخورده باقی می‌ماند.'
                )
                ->modalSubmitActionLabel('بله، همگام‌سازی کن')
                ->action(function () {

                    // فقط ربات‌های فعال و در حال بررسی
                    $bots = ManagedBot::whereIn('status', ['active', 'pending'])
                        ->where('bot_id_string', '!=', 'admin_bot')
                        ->get();

                    // تنظیمات پیش‌فرض سیستم
                    $defaultSettings = [
                        [
                            'key' => 'store_name',
                            'description' => 'نام فروشگاه',
                            'value' => '',
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
                        [
                            'key' => 'show_discounted_products',
                            'description' => 'نمایش دکمه محصولات تخفیف‌دار در منو',
                            'value' => '1',
                            'type' => 'boolean',
                            'options' => null,
                        ],
                        [
                            'key' => 'support_text',
                            'description' => 'متن بخش پشتیبانی',
                            'value' => '🎧 برای پیگیری سفارشات یا طرح سوالات خود با ما در ارتباط باشید:',
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
                            'key' => 'force_join',
                            'description' => 'عضویت اجباری در کانال',
                            'value' => '0',
                            'type' => 'boolean',
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

                    // کامندهای ربات
                    $botCommands = [
                        [
                            'command' => 'start',
                            'description' => '🏠 منوی اصلی فروشگاه',
                        ],
                        [
                            'command' => 'search',
                            'description' => '🔍 جستجوی محصولات',
                        ],
                        [
                            'command' => 'categories',
                            'description' => '📂 دسته‌بندی‌ها',
                        ],
                        [
                            'command' => 'cart',
                            'description' => '🛒 سبد خرید',
                        ],
                        [
                            'command' => 'my_orders',
                            'description' => '📦 پیگیری سفارشات',
                        ],
                        [
                            'command' => 'favorites',
                            'description' => '❤️ علاقه‌مندی‌ها',
                        ],
                        [
                            'command' => 'support',
                            'description' => '🎧 ارتباط با پشتیبانی',
                        ],
                    ];

                    $successCount = 0;
                    $failedCount = 0;

                    foreach ($bots as $bot) {

                        // ====================================================
                        // 1. ایجاد تنظیمات جدید
                        // ====================================================

                        foreach ($defaultSettings as $setting) {

                            $value = $setting['value'];

                            if ($setting['key'] === 'store_name') {
                                $value = $bot->bot_name ?? 'فروشگاه';
                            }

                            Setting::firstOrCreate(
                                [
                                    'bot_id' => $bot->id,
                                    'key' => $setting['key'],
                                ],
                                [
                                    'description' => $setting['description'],
                                    'value' => $value,
                                    'type' => $setting['type'],
                                    'options' => $setting['options'],
                                ]
                            );
                        }

                        // ====================================================
                        // 2. آپدیت منوی Telegram
                        // ====================================================

                        try {

                            $response = Http::timeout(5)
                                ->post(
                                    "https://api.telegram.org/bot{$bot->bot_token}/setMyCommands",
                                    [
                                        'commands' => $botCommands,
                                    ]
                                );

                            if ($response->successful()) {
                                $successCount++;
                            } else {
                                $failedCount++;
                            }

                        } catch (\Throwable $e) {

                            $failedCount++;
                        }
                    }

                    // ========================================================
                    // نتیجه عملیات
                    // ========================================================

                    Notification::make()
                        ->title('عملیات همگام‌سازی انجام شد')
                        ->body(
                            "تنظیمات {$bots->count()} ربات بررسی شد. " .
                            "{$successCount} ربات با موفقیت همگام شد و " .
                            "{$failedCount} ربات با خطا مواجه شد."
                        )
                        ->success()
                        ->send();
                }),

            // ============================================================
            // 🧹 پاکسازی کش تمام ربات‌ها
            // ============================================================
            Actions\Action::make('clear_all_bots_cache')
                ->label('🧹 پاکسازی کش تمام ربات‌ها')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('پاکسازی کش و نشست‌های تمام ربات‌ها')
                ->modalDescription(
                    'آیا از پاکسازی کش اطمینان دارید؟ با این کار تنظیمات کش‌شده تمام ربات‌ها پاک می‌شود و سشن‌های موقت کاربران نیز ریست خواهد شد.'
                )
                ->modalSubmitActionLabel('بله، پاکسازی کن')
                ->action(function () {

                    $bots = ManagedBot::all();

                    $successCount = 0;
                    $sessionCount = 0;

                    foreach ($bots as $bot) {

                        // ====================================================
                        // 1. پاک کردن کش تنظیمات ربات
                        // ====================================================

                        if ($bot->webhook_secret) {

                            Cache::forget(
                                "bot_config:{$bot->webhook_secret}"
                            );
                        }

                        // ====================================================
                        // 2. پیدا کردن کاربران ربات
                        // ====================================================

                        $userIds = User::where('bot_id', $bot->id)
                            ->pluck('id');

                        // ====================================================
                        // 3. پاک کردن Session کاربران
                        // ====================================================

                        foreach ($userIds as $userId) {

                            Cache::forget(
                                "shop_session:{$bot->id}:{$userId}"
                            );

                            $sessionCount++;
                        }

                        $successCount++;
                    }

                    Notification::make()
                        ->title('عملیات موفق')
                        ->body(
                            "کش {$successCount} ربات و {$sessionCount} نشست کاربری با موفقیت پاکسازی شد."
                        )
                        ->success()
                        ->send();
                }),

            // ============================================================
            // ➕ ایجاد ربات جدید
            // ============================================================
            Actions\CreateAction::make(),
        ];
    }
}
