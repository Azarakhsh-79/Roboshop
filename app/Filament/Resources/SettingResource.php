<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SettingResource\Pages;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog-8-tooth';
    protected static ?string $modelLabel = 'تنظیمات ربات';
    protected static ?string $pluralModelLabel = 'تنظیمات پایه';
    protected static ?string $navigationGroup = 'تنظیمات سیستم';
    protected static ?int $navigationSort = 1;

    // ⛔ دکمه ساخت تنظیمات جدید را غیرفعال می‌کنیم
    public static function canCreate(): bool
    {
        return false;
    }

    // ⛔ دکمه حذف را غیرفعال می‌کنیم تا تنظیمات حیاتی ربات پاک نشوند
    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->label('عنوان تنظیمات')
                    ->weight('bold')
                    ->searchable(),

                // نمایش هوشمند مقدار بر اساس نوع آن
                Tables\Columns\TextColumn::make('value')
                    ->label('مقدار فعلی')
                    ->badge()
                    ->color(fn($record) => match ($record->type) {
                        'boolean' => ($record->value === '1' || $record->value === 'true') ? 'success' : 'danger',
                        default => 'primary',
                    })
                    ->formatStateUsing(function (Setting $record, $state) {
                        // اگر از نوع خاموش/روشن بود
                        if ($record->type === 'boolean') {
                            return ($state === '1' || $state === 'true') ? '✅ فعال' : '❌ غیرفعال';
                        }
                        // اگر از نوع انتخابی بود، برچسبِ آن گزینه را از JSON بخوان و نشان بده
                        if ($record->type === 'select' && is_array($record->options)) {
                            return $record->options[$state] ?? $state;
                        }
                        // اضافه شدن فرمت پول برای اعداد (مثل هزینه ارسال)
                        if ($record->type === 'number' && is_numeric($state)) {
                            return number_format((int) $state);
                        }

                        return $state ?: '---';
                    })
                    ->limit(40),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('آخرین تغییر')
                    ->formatStateUsing(fn($state) => $state ? \Morilog\Jalali\Jalalian::fromDateTime($state)->format('Y/m/d ساعت H:i') : '-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            ->filters([
                // فیلتر خاصی نیاز نیست
            ])
            ->actions([
                // 🚀 جادوی ۲: دکمه زنده و سریع فقط برای تنظیمات خاموش/روشن
                Tables\Actions\Action::make('toggle_status')
                    ->label(fn(Setting $record) => ($record->value === '1' || $record->value === 'true') ? 'غیرفعال کن' : 'فعال کن')
                    ->icon(fn(Setting $record) => ($record->value === '1' || $record->value === 'true') ? 'heroicon-m-power' : 'heroicon-m-check-badge')
                    ->color(fn(Setting $record) => ($record->value === '1' || $record->value === 'true') ? 'danger' : 'success')
                    ->visible(fn(Setting $record) => $record->type === 'boolean')
                    ->action(function (Setting $record) {
                        $isCurrentlyOn = ($record->value === '1' || $record->value === 'true');

                        // 🛡 بررسی امنیتی برای دکمه عضویت اجباری هنگام روشن کردن
                        if (!$isCurrentlyOn && $record->key === 'force_join') {
                            $bot = $record->bot;
                            $channelId = $bot->settings()->where('key', 'channel_id')->value('value');

                            if (empty($channelId)) {
                                Notification::make()->danger()->title('خطا')->body('ابتدا باید آیدی کانال تلگرام را تنظیم کنید.')->send();
                                return;
                            }

                            $chatId = str_starts_with($channelId, '@') ? $channelId : '@' . $channelId;

                            $response = \Illuminate\Support\Facades\Http::timeout(10)->get("https://api.telegram.org/bot{$bot->bot_token}/getChatMember", [
                                'chat_id' => $chatId,
                                'user_id' => $bot->bot_id_string,
                            ]);

                            if (!$response->successful()) {
                                Notification::make()->danger()->title('خطا')->body('ربات در کانال تنظیم شده ادمین نیست یا کانال اشتباه است!')->send();
                                return;
                            }

                            $status = $response->json('result.status');
                            if (!in_array($status, ['administrator', 'creator'])) {
                                Notification::make()->danger()->title('خطا')->body('ربات در کانال حضور دارد اما ادمین نیست!')->send();
                                return;
                            }
                        }

                        // تغییر وضعیت در دیتابیس
                        $record->update([
                            'value' => $isCurrentlyOn ? '0' : '1'
                        ]);

                        // 🧹 پاکسازی آنیِ کش تنظیمات ربات
                        if ($record->bot && $record->bot->webhook_secret) {
                            \Illuminate\Support\Facades\Cache::forget("bot_config:{$record->bot->webhook_secret}");
                        }

                        Notification::make()->success()->title('تغییر وضعیت')->body('تنظیمات با موفقیت تغییر کرد و کش ربات به‌روزرسانی شد.')->send();
                    }),

                // 🚀 جادوی ۳: دکمه ویرایش (پنجره بازشونده) برای بقیه تنظیمات
                Tables\Actions\EditAction::make()
                    ->visible(fn(Setting $record) => $record->type !== 'boolean')
                    ->modalHeading(fn(Setting $record) => 'ویرایش: ' . $record->description)
                    ->modalWidth('md')
                    // 🧹 هوک قدرتمند فیلامنت: اجرا شدن دقیقاً بعد از فشردن دکمه "ذخیره" در پاپ‌آپ
                    ->after(function (Setting $record) {
                        if ($record->bot && $record->bot->webhook_secret) {
                            \Illuminate\Support\Facades\Cache::forget("bot_config:{$record->bot->webhook_secret}");
                        }
                    })
                    ->form(function (Setting $record) {
                        return match ($record->type) {
                            'select' => [
                                Forms\Components\Select::make('value')
                                    ->label($record->description)
                                    ->options($record->options ?? [])
                                    ->searchable()
                                    ->required(),
                            ],
                            'number' => [
                                Forms\Components\TextInput::make('value')
                                    ->label($record->description)
                                    ->numeric()
                                    ->required(),
                            ],
                            'text' => [
                                Forms\Components\Textarea::make('value')
                                    ->label($record->description)
                                    ->rows(4)
                                    ->required(),
                            ],
                            default => [
                                Forms\Components\TextInput::make('value')
                                    ->label($record->description)
                                    ->maxLength(255)
                                    ->required()
                                    ->rule(function (Setting $record) {
                                            return function (string $attribute, $value, \Closure $fail) use ($record) {
                                                if ($record->key === 'channel_id' && !empty($value)) {
                                                    $bot = $record->bot;
                                                    $chatId = $value;
                                                    if (str_starts_with($chatId, 'https://t.me/')) {
                                                        $chatId = '@' . str_replace('https://t.me/', '', $chatId);
                                                    } elseif (!is_numeric($chatId) && !str_starts_with($chatId, '@')) {
                                                        $chatId = '@' . $chatId;
                                                    }

                                                    $response = \Illuminate\Support\Facades\Http::timeout(10)->get("https://api.telegram.org/bot{$bot->bot_token}/getChatMember", [
                                                    'chat_id' => $chatId,
                                                    'user_id' => $bot->bot_id_string,
                                                    ]);

                                                    if (!$response->successful()) {
                                                        $fail('❌ کانال یافت نشد یا ربات به آن دسترسی ندارد!');
                                                        return;
                                                    }

                                                    $status = $response->json('result.status');
                                                    if (!in_array($status, ['administrator', 'creator'])) {
                                                        $fail('⚠️ ربات در کانال حضور دارد اما ادمین نیست!');
                                                    }
                                                }
                                            };
                                        })
                                    ->dehydrateStateUsing(function ($state) use ($record) {
                                            if ($record->key === 'channel_id' && !empty($state)) {
                                                if (str_starts_with($state, 'https://t.me/')) {
                                                    return '@' . str_replace('https://t.me/', '', $state);
                                                } elseif (!is_numeric($state) && !str_starts_with($state, '@')) {
                                                    return '@' . $state;
                                                }
                                            }
                                            return $state;
                                        }),
                            ],
                        };
                    }),
            ]);
    }

    // 🔒 ایزوله‌سازی برای نمایش فقط تنظیماتِ همین فروشگاه
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('bot_id', session('current_shop_id'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSettings::route('/'),
        ];
    }
}
