<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

// 🚀 افزودن کلاس‌های مربوط به Infolist (پرونده مشتری)
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Tabs;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'مشتریان و ادمین‌ها';
    protected static ?string $modelLabel = 'کاربر';
    protected static ?string $pluralModelLabel = 'کاربران';
    protected static ?string $navigationGroup = 'کاربران و ارتباطات';
    protected static ?int $navigationSort = 1;

    // ⛔ فرم بسته است تا دیتای حساس دستکاری نشود
    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('chat_id')
                    ->label('آیدی عددی (Chat ID)')
                    ->searchable()
                    ->copyable()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('first_name')
                    ->label('نام و نام خانوادگی')
                    ->formatStateUsing(fn($record) => trim($record->first_name . ' ' . $record->last_name))
                    ->searchable(['first_name', 'last_name']),

                Tables\Columns\TextColumn::make('username')
                    ->label('یوزرنیم تلگرام')
                    ->formatStateUsing(fn($state) => $state ? "@{$state}" : 'ندارد')
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_spent')
                    ->label('ارزش مشتری (مجموع خرید)')
                    ->getStateUsing(function ($record) {
                        // محاسبه کل خریدهای موفق این کاربر
                        return $record->invoices()->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])->sum('total_amount');
                    })
                    ->formatStateUsing(fn($state) => number_format($state) . ' تومان')
                    ->badge()
                    ->color(fn($state) => match (true) {
                        $state >= 5000000 => 'warning', // 🌟 طلایی (بیشتر از ۵ میلیون)
                        $state >= 1000000 => 'info',    // 🥈 نقره‌ای (بیشتر از ۱ میلیون)
                        $state > 0 => 'success', // 🥉 برنزی (حداقل یک خرید)
                        default => 'gray',    // 👻 بدون خرید
                    })
                    ->icon(fn($state) => match (true) {
                        $state >= 5000000 => 'heroicon-s-star',
                        $state > 0 => 'heroicon-m-shopping-bag',
                        default => 'heroicon-m-user',
                    }),

                Tables\Columns\ToggleColumn::make('is_admin')
                    ->label('دسترسی ادمین')
                    ->disabled(fn($record) => $record->id === auth()->id())
                    // 🚀 جادوی اعمال در لحظه (Real-Time)
                    ->afterStateUpdated(function ($record, $state) {
                        // ۱. پاکسازی کش نشستِ همین کاربر تا در کلیک بعدی، ربات دسترسی جدید را بخواند
                        \Illuminate\Support\Facades\Cache::forget("shop_session:{$record->bot_id}:{$record->id}");

                        // ۲. ارسال نوتیفیکیشن آنی به تلگرام کاربر
                        if (!empty($record->chat_id) && !empty($record->bot->bot_token)) {
                            $text = $state
                                ? "🎉 <b>ارتقای سطح دسترسی!</b>\n\nشما توسط مالک فروشگاه به عنوان <b>«مدیر»</b> انتخاب شدید.\nاز این پس دکمه‌های مدیریتی در ربات برای شما فعال خواهند بود. 🚀"
                                : "⚠️ <b>تغییر سطح دسترسی</b>\n\nدسترسی مدیریت شما در این فروشگاه لغو شد و حساب شما به «مشتری عادی» تغییر یافت.";

                            $replyMarkup = json_encode([
                                'inline_keyboard' => [
                                    [['text' => '🔄 بارگذاری مجدد ربات', 'callback_data' => 'main_menu']]
                                ]
                            ]);

                            \Illuminate\Support\Facades\Http::timeout(10)->post("https://api.telegram.org/bot{$record->bot->bot_token}/sendMessage", [
                                'chat_id' => $record->chat_id,
                                'text' => $text,
                                'parse_mode' => 'HTML',
                                'reply_markup' => $replyMarkup
                            ]);
                        }
                    }),

                Tables\Columns\SelectColumn::make('status')
                    ->label('وضعیت کاربری')
                    ->options([
                        'active' => '🟢 فعال',
                        'pending' => '🟡 در انتظار',
                        'blocked' => '🟠 مسدود (موقت)',
                        'baned' => '🔴 اخراج (دائم)',
                    ])
                    ->disabled(fn($record) => $record->id === auth()->id())
                    // 🚀 اعمال در لحظه برای وضعیت مسدودی کاربر
                    ->afterStateUpdated(function ($record, $state) {

                        \Illuminate\Support\Facades\Cache::forget("shop_session:{$record->bot_id}:{$record->id}");

                        if (!empty($record->chat_id) && !empty($record->bot->bot_token)) {
                            $text = match ($state) {
                                'blocked' => "🚫 <b>حساب کاربری مسدود شد</b>\n\nحساب شما در این فروشگاه به صورت موقت مسدود شده است. برای پیگیری با پشتیبانی تماس بگیرید.",
                                'baned' => "⛔️ <b>اخراج دائم</b>\n\nحساب شما به دلیل نقض قوانین برای همیشه از این فروشگاه اخراج شد.",
                                'active' => "✅ <b>حساب کاربری فعال شد</b>\n\nمحدودیت‌های حساب شما برطرف شد و هم‌اکنون می‌توانید دوباره از فروشگاه استفاده کنید.",
                                default => null,
                            };

                            if ($text) {
                                \Illuminate\Support\Facades\Http::timeout(10)->post("https://api.telegram.org/bot{$record->bot->bot_token}/sendMessage", [
                                    'chat_id' => $record->chat_id,
                                    'text' => $text,
                                    'parse_mode' => 'HTML',
                                ]);
                            }
                        }
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ عضویت')
                    ->formatStateUsing(fn($state) => $state ? \Morilog\Jalali\Jalalian::fromDateTime($state)->format('Y/m/d') : '-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('is_admin')
                    ->query(fn($query) => $query->where('is_admin', 1))
                    ->label('فقط ادمین‌ها'),
            ])
            ->actions([
                // 🚀 گروه‌بندی تمام اکشن‌ها در یک منوی سه نقطه
                Tables\Actions\ActionGroup::make([

                    // ۱. دکمه باز کردن پرونده ۳۶۰ درجه مشتری
                    Tables\Actions\ViewAction::make()
                        ->label('پرونده مشتری')
                        ->icon('heroicon-o-folder-open')
                        ->color('info')
                        ->slideOver()
                        ->modalWidth('2xl'),

                    // ۲. یادداشت مدیر
                    Tables\Actions\Action::make('edit_note')
                        ->label('یادداشت مدیر')
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning')
                        ->form([
                            \Filament\Forms\Components\Textarea::make('admin_notes')
                                ->label('متن یادداشت')
                                ->helperText('این متن فقط برای مدیران قابل مشاهده است و کاربر آن را نمی‌بیند.')
                                ->default(fn($record) => $record->admin_notes)
                                ->rows(4),
                        ])
                        ->action(function ($record, array $data) {
                            $record->update(['admin_notes' => $data['admin_notes']]);
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('ذخیره شد')
                                ->body('یادداشت شما برای این مشتری با موفقیت بروزرسانی شد.')
                                ->send();
                        }),

                    // ۳. ارسال پیام به کاربر
                    Tables\Actions\Action::make('send_direct_message')
                        ->label('ارسال پیام به کاربر')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->form([
                            \Filament\Forms\Components\Textarea::make('message')
                                ->label('متن پیام')
                                ->placeholder('سلام، سفارش شما آماده ارسال است...')
                                ->required()
                                ->rows(4),
                        ])
                        ->action(function ($record, array $data) {
                            if (empty($record->chat_id) || empty($record->bot->bot_token)) {
                                \Filament\Notifications\Notification::make()->danger()->title('خطا')->body('این کاربر چت آیدی تلگرام ندارد!')->send();
                                return;
                            }

                            $response = \Illuminate\Support\Facades\Http::timeout(10)->post("https://api.telegram.org/bot{$record->bot->bot_token}/sendMessage", [
                                'chat_id' => $record->chat_id,
                                'text' => "🔔 <b>پیام از طرف مدیریت فروشگاه:</b>\n\n" . $data['message'],
                                'parse_mode' => 'HTML',
                            ]);

                            if ($response->successful()) {
                                \Filament\Notifications\Notification::make()->success()->title('ارسال شد')->body('پیام شما با موفقیت به تلگرام کاربر ارسال شد.')->send();
                            } else {
                                \Filament\Notifications\Notification::make()->danger()->title('خطا در ارسال')->body('ربات نتوانست پیام را ارسال کند (شاید کاربر ربات را بلاک کرده باشد).')->send();
                            }
                        }),
                ])
                // ->icon('heroicon-m-ellipsis-vertical') // در صورت تمایل می‌توانید آیکون سه نقطه را شخصی‌سازی کنید
            ]);
    }

    // =========================================================================
    // 🗂 سیستم قدرتمند Infolist برای نمایش پرونده مشتری در تب‌های مختلف
    // =========================================================================
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Tabs::make('Tabs')
                    ->columnSpanFull()
                    ->tabs([
                        // 👤 تب اول: اطلاعات فردی و آدرس‌ها
                        Tabs\Tab::make('اطلاعات پایه و آدرس')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('first_name')->label('نام'),
                                    TextEntry::make('last_name')->label('نام خانوادگی')->default('-'),
                                    TextEntry::make('username')->label('یوزرنیم')->formatStateUsing(fn($state) => $state ? "@{$state}" : '-'),
                                    TextEntry::make('phone')->label('تلفن ثبت شده')->copyable()->default('ندارد'),
                                    TextEntry::make('created_at')->label('تاریخ عضویت')->formatStateUsing(fn($state) => $state ? \Morilog\Jalali\Jalalian::fromDateTime($state)->format('Y/m/d H:i') : '-'),
                                ]),

                                TextEntry::make('admin_notes')
                                    ->label('📝 یادداشت محرمانه مدیریت')
                                    ->default('یادداشتی ثبت نشده است.')
                                    ->color('warning')
                                    ->columnSpanFull(),

                                Section::make('آدرس‌های پستی مشتری')
                                    ->schema([
                                        RepeatableEntry::make('addresses')
                                            ->label('')
                                            ->schema([
                                                TextEntry::make('address')->label('آدرس کامل'),
                                                TextEntry::make('phone')->label('شماره گیرنده'),
                                            ])->columns(2)
                                    ])
                            ]),

                        // 🛒 تب دوم: سبد خرید فعلی
                        Tabs\Tab::make('سبد خرید فعلی')
                            ->icon('heroicon-o-shopping-cart')
                            ->badge(fn($record) => $record->cartItems()->count())
                            ->schema([
                                RepeatableEntry::make('cartItems')
                                    ->label('اقلام موجود در سبد خرید (پرداخت نشده)')
                                    ->schema([
                                        TextEntry::make('product.name')->label('محصول')->weight('bold')->color('primary'),
                                        TextEntry::make('variant.variant_name')->label('نوع/مدل')->default('ساده (بدون متغیر)'),
                                        TextEntry::make('quantity')->label('تعداد')->badge()->color('info'),
                                    ])->columns(3)
                            ]),

                        // 🛍 تب سوم: تاریخچه خریدها (ترکیب شده و هوشمند)
                     // 🛍 تب سوم: تاریخچه خریدها (ترکیب شده و هوشمند)
                        Tabs\Tab::make('تاریخچه خریدها')
                            ->icon('heroicon-o-shopping-bag')
                            ->badge(fn($record) => $record->invoices()->where('status', '!=', 'pending')->count()) // نمایش تعداد روی تب
                            ->schema([
                               RepeatableEntry::make('invoices')
                                    ->label('فاکتورها و سفارشات قبلی مشتری')
                                    ->schema([
                                        TextEntry::make('id')
                                            ->label('شماره فاکتور')
                                            ->formatStateUsing(fn ($state) => '#' . $state),

                                        TextEntry::make('tracking_code')
                                            ->label('کد پیگیری')
                                            ->copyable()
                                            ->color('primary')
                                            ->default('-'),

                                        TextEntry::make('total_amount')
                                            ->label('مبلغ کل')
                                            ->formatStateUsing(fn ($state) => number_format($state) . ' تومان')
                                            ->badge()
                                            ->color('success'),

                                        TextEntry::make('status')
                                            ->label('وضعیت')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'paid', 'delivered' => 'success',
                                                'processing' => 'warning',
                                                'shipped' => 'info',
                                                'canceled', 'failed' => 'danger',
                                                default => 'gray',
                                            })
                                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                                'paid' => 'پرداخت شده',
                                                'processing' => 'در حال پردازش',
                                                'shipped' => 'ارسال شده',
                                                'delivered' => 'تحویل شده',
                                                'canceled' => 'لغو شده',
                                                'failed' => 'ناموفق',
                                                'pending' => 'در انتظار',
                                                default => $state,
                                            }),

                                        TextEntry::make('created_at')
                                            ->label('تاریخ ثبت')
                                            ->formatStateUsing(fn ($state) => $state ? \Morilog\Jalali\Jalalian::fromDateTime($state)->format('Y/m/d') : '-'),
                                    ])
                                    ->columns(5),
                            ]),
                        // ❤️ تب چهارم: علاقه‌مندی‌ها
                        Tabs\Tab::make('علاقه‌مندی‌ها')
                            ->icon('heroicon-o-heart')
                            ->schema([
                                Section::make('لیست کالاهای نشان‌شده توسط مشتری')
                                    ->schema([
                                        TextEntry::make('favorites_list')
                                            ->label('')
                                            ->state(function ($record) {
                                                $favs = \App\Models\Favorite::where('bot_id', session('current_shop_id'))
                                                    ->where('user_id', $record->id)
                                                    ->with('product')
                                                    ->get();

                                                if ($favs->isEmpty())
                                                    return 'هیچ کالایی در لیست علاقه‌مندی این مشتری وجود ندارد.';

                                                return $favs->pluck('product.name')->implode('  ،  ');
                                            })
                                            ->weight('bold')
                                    ])
                            ]),
                    ])
            ]);
    }

    // 🔒 ایزوله کردن نمایش داده‌ها (فقط کاربران همین فروشگاه)
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('bot_id', session('current_shop_id'));
    }

    // ⛔ جلوگیری از ساخته شدن دستی کاربر
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
        ];
    }
}
