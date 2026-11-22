<?php

namespace App\Filament\Superadmin\Resources;

use App\Filament\Superadmin\Resources\ManagedBotResource\Pages;
use App\Models\ManagedBot;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\ActionGroup;

class ManagedBotResource extends Resource
{
    protected static ?string $model = ManagedBot::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';
    protected static ?string $navigationLabel = 'مدیریت فروشگاه‌ها';
    protected static ?string $modelLabel = 'فروشگاه (ربات)';
    protected static ?string $pluralModelLabel = 'فروشگاه‌ها';
    protected static ?int $navigationSort = 1;

    // فقط ربات‌های فروشگاهی را نشان بده (ربات سوپرادمین را مخفی کن)
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('bot_id_string', '!=', 'admin_bot');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('اطلاعات پایه فروشگاه')
                    ->schema([
                        Forms\Components\TextInput::make('bot_name')
                            ->label('نام فروشگاه')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('bot_username')
                            ->label('یوزرنیم ربات')
                            ->prefix('@')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('owner_phone')
                            ->label('تلفن مالک')
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\Select::make('status')
                            ->label('وضعیت')
                            ->options([
                                'active' => 'فعال',
                                'pending' => 'در انتظار استارت',
                                'blocked' => 'مسدود شده',
                            ])
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('اطلاعات اشتراک')
                    ->schema([
                        Forms\Components\TextInput::make('subscription_plan')
                            ->label('نام پلن فعلی')
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('subscription_expires_at')
                            ->label('تاریخ انقضای اشتراک')
                            ->displayFormat('Y/m/d')
                            ->native(false),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('bot_name')
                    ->label('نام فروشگاه')
                    ->searchable()
                    ->sortable()
                    ->description(fn(ManagedBot $record): string => '@' . $record->bot_username),

                Tables\Columns\TextColumn::make('owner_phone')
                    ->label('مالک')
                    ->searchable()
                    ->icon('heroicon-m-phone'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('وضعیت')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'pending',
                        'danger' => 'blocked',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'active' => 'فعال',
                        'pending' => 'در انتظار',
                        'blocked' => 'مسدود',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('subscription_expires_at')
                    ->label('انقضا')
                    ->formatStateUsing(fn($state) => $state ? \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($state))->format('Y/m/d') : '-')
                    ->sortable()
                    ->color(fn($record) => $record->subscription_expires_at?->isPast() ? 'danger' : 'success'),

                // نمایش تعداد محصولات و سفارشات به عنوان شاخص فعالیت
                Tables\Columns\TextColumn::make('total_products')
                    ->label('محصولات')
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('total_orders')
                    ->label('سفارشات')
                    ->sortable()
                    ->badge()
                    ->color('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('فیلتر وضعیت')
                    ->options([
                        'active' => 'فعال‌ها',
                        'blocked' => 'مسدود شده‌ها',
                        'pending' => 'در انتظار',
                    ]),
                Tables\Filters\Filter::make('expired_subscriptions')
                    ->label('اشتراک‌های منقضی شده')
                    ->query(fn(Builder $query): Builder => $query->where('subscription_expires_at', '<', now())),
            ])
            ->actions([
                // دکمه ورود سریع به پنل فروشگاه (Impersonation)
                Tables\Actions\Action::make('login_as')
                    ->label('ورود به پنل')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('success')
                    ->url(fn(ManagedBot $record): string => route('panel.magic_login', ['bot_id' => $record->id]))
                    ->openUrlInNewTab(),

                // دکمه شارژ سریع اشتراک (تمدید دستی)
                Tables\Actions\Action::make('renew_subscription')
                    ->label('تمدید دستی')
                    ->icon('heroicon-o-calendar-days')
                    ->color('warning')
                    ->form([
                        Forms\Components\Select::make('days')
                            ->label('تعداد روز تمدید')
                            ->options([
                                '30' => 'یک ماهه (۳۰ روز)',
                                '90' => 'سه ماهه (۹۰ روز)',
                                '180' => 'شش ماهه (۱۸۰ روز)',
                                '365' => 'یک‌ساله (۳۶۵ روز)',
                            ])
                            ->required(),
                    ])
                    ->action(function (ManagedBot $record, array $data): void {
                        $currentDate = $record->subscription_expires_at && $record->subscription_expires_at->isFuture()
                            ? $record->subscription_expires_at
                            : now();

                        $record->update([
                            'subscription_expires_at' => $currentDate->addDays((int) $data['days']),
                            'status' => 'active' // اگر مسدود بوده باز شود
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('اشتراک با موفقیت تمدید شد')
                            ->success()
                            ->send();
                    }),

                ActionGroup::make([

                    Action::make('info_webhook')
                        ->label('وضعیت وب‌هوک (Info)')
                        ->icon('heroicon-o-information-circle')
                        ->color('info')
                        ->modalHeading('اطلاعات زنده وب‌هوک از سرور تلگرام')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('بستن')
                        ->fillForm(function ($record) {
                            $response = Http::timeout(10)->get("https://api.telegram.org/bot{$record->bot_token}/getWebhookInfo");
                            $info = $response->json('result') ?? [];

                            return [
                                'url' => $info['url'] ?? 'ست نشده',
                                'pending_update_count' => $info['pending_update_count'] ?? 0,
                                'last_error_message' => $info['last_error_message'] ?? 'بدون خطا',
                                'raw_response' => json_encode($response->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                            ];
                        })
                        ->form([
                            TextInput::make('url')
                                ->label('آدرس فعلی تنظیم شده (URL)')
                                ->disabled()
                                ->columnSpanFull(),
                            TextInput::make('pending_update_count')
                                ->label('آپدیت‌های در صف (نشان‌دهنده کندی سرور)')
                                ->disabled(),
                            Textarea::make('last_error_message')
                                ->label('آخرین خطای رخ داده (Last Error)')
                                ->disabled()
                                ->columnSpanFull(),
                            Textarea::make('raw_response')
                                ->label('پاسخ خام تلگرام (Raw JSON)')
                                ->disabled()
                                ->rows(5)
                                ->columnSpanFull(),
                        ]),

                    Action::make('set_webhook')
                        ->label('تنظیم وب‌هوک (Set)')
                        ->icon('heroicon-o-link')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('تنظیم مجدد وب‌هوک')
                        ->modalDescription('آیا مطمئن هستید که می‌خواهید وب‌هوک این ربات را روی این سرور تنظیم کنید؟')
                        ->action(function ($record) {
                            $webhookUrl = url("/api/bot/shopbot/{$record->webhook_secret}");

                            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$record->bot_token}/setWebhook", [
                                'url' => $webhookUrl,
                                'secret_token' => $record->webhook_secret,
                                'allowed_updates' => ['message', 'callback_query', 'inline_query'],
                            ]);

                            if ($response->successful() && $response->json('ok')) {
                                Notification::make()
                                    ->title('وب‌هوک با موفقیت تنظیم شد')
                                    ->body($response->json('description') ?? '')
                                    ->success()
                                    ->send();
                            } else {
                                $error = $response->json('description') ?? 'خطای نامشخص از سمت تلگرام';
                                Notification::make()
                                    ->title('خطا در تنظیم وب‌هوک')
                                    ->body($error)
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('delete_webhook')
                        ->label('حذف وب‌هوک (Delete)')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('قطع اتصال وب‌هوک')
                        ->modalDescription('با این کار ربات دیگر آپدیتی از تلگرام دریافت نخواهد کرد. آیا مطمئن هستید؟')
                        ->action(function ($record) {
                            $response = Http::timeout(10)->get("https://api.telegram.org/bot{$record->bot_token}/deleteWebhook");

                            if ($response->successful() && $response->json('ok')) {
                                Notification::make()
                                    ->title('وب‌هوک با موفقیت حذف شد')
                                    ->body('ارتباط ربات با سرور قطع گردید.')
                                    ->success()
                                    ->send();
                            } else {
                                $error = $response->json('description') ?? 'خطای نامشخص از سمت تلگرام';
                                Notification::make()
                                    ->title('خطا در حذف وب‌هوک')
                                    ->body($error)
                                    ->danger()
                                    ->send();
                            }
                        }),

                ])
                ->label('مدیریت تلگرام')
                ->icon('heroicon-m-cog-8-tooth')
                ->color('info'),

                // منوی کشویی برای ویرایش و حذف
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    // مسدودسازی دسته‌جمعی
                    Tables\Actions\BulkAction::make('block_bots')
                        ->label('مسدود کردن فروشگاه‌ها')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn(\Illuminate\Database\Eloquent\Collection $records) => $records->each->update(['status' => 'blocked'])),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // در آینده ریلیشن‌ها (مثل فاکتورها یا پلن‌ها) را اینجا می‌گذاریم
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListManagedBots::route('/'),
            'create' => Pages\CreateManagedBot::route('/create'),
            'edit' => Pages\EditManagedBot::route('/{record}/edit'),
        ];
    }
}
