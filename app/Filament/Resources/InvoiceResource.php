<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder; // 👈 حتماً این خط اضافه شود

use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Grid;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationLabel = 'سفارشات';
    protected static ?string $modelLabel = 'سفارش';
    protected static ?string $pluralModelLabel = 'سفارشات';
    protected static ?string $navigationGroup = 'فروشگاه';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('status')
                    ->label('وضعیت سفارش')
                    ->options([
                        'pending' => 'در انتظار پرداخت (سیستم)',
                        'paid' => 'پرداخت شده (سیستم)',
                        'failed' => 'ناموفق (سیستم)',
                        'awaiting_approval' => 'در انتظار تایید (سیستم)',
                        'processing' => 'مشاهده شد / در حال پردازش',
                        'shipped' => 'ارسال شد',
                        'delivered' => 'تحویل داده شد',
                        'canceled' => 'لغو شده',
                    ])
                    ->disableOptionWhen(fn(string $value): bool => in_array($value, ['pending', 'paid', 'failed', 'awaiting_approval']))
                    ->required(),
                Forms\Components\TextInput::make('tracking_code')
                    ->label('کد پیگیری')
                    ->disabled(),
                Forms\Components\TextInput::make('amount')
                    ->label('مبلغ کل (تومان)')
                    ->numeric()
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tracking_code')->label('کد پیگیری')->searchable(),
                Tables\Columns\TextColumn::make('user.first_name')->label('نام مشتری')->searchable(),
                Tables\Columns\TextColumn::make('amount')->label('مبلغ')->numeric(),
                Tables\Columns\SelectColumn::make('status')
                    ->label('وضعیت (تغییر سریع)')
                    ->options([
                        'pending' => 'در انتظار پرداخت (سیستم)',
                        'paid' => 'پرداخت شده (سیستم)',
                        'failed' => 'ناموفق (سیستم)',
                        'awaiting_approval' => 'در انتظار تایید (سیستم)',
                        'processing' => 'مشاهده شد / در حال پردازش',
                        'shipped' => 'ارسال شد',
                        'delivered' => 'تحویل داده شد',
                        'canceled' => 'لغو شده',
                    ])
                    // ⛔️ قفل کردن گزینه‌های سیستمی تا ادمین نتواند آن‌ها را انتخاب کند
                    ->disableOptionWhen(fn(string $value): bool => in_array($value, ['pending', 'paid', 'failed', 'awaiting_approval'])),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ ثبت')
                    // نمایش تاریخ به صورت شمسی (برای زیبایی بیشتر)
                    ->formatStateUsing(fn($state) => $state ? \Morilog\Jalali\Jalalian::fromDateTime($state)->format('Y/m/d H:i') : '-')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('فیلتر وضعیت')
                    ->options([
                        'pending' => 'در انتظار پرداخت',
                        'paid' => 'پرداخت شده',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([

                    // 🚀 تغییر وضعیت گروهی به ارسال شده
                    Tables\Actions\BulkAction::make('markAsShipped')
                        ->label('تغییر به "ارسال شد"')
                        ->icon('heroicon-o-truck')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $records->each(function ($record) {
                                // چون در مدل Invoice متد booted نوشتیم، با این آپدیت پیام تلگرام هم برای همه اتوماتیک میره!
                                $record->update(['status' => 'shipped']);
                            });
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    // 🔒 1. فیلتر امنیتی کوئری: فقط سفارشاتِ همین فروشگاه نمایش داده شود!
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('bot_id', session('current_shop_id'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'view' => Pages\ViewInvoice::route('/{record}'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
    // ⛔️ غیرفعال کردن قابلیت ایجاد دستی سفارش
    public static function canCreate(): bool
    {
        return false;
    }

    // 🔔 2. فیلتر امنیتی سایدبار: نمایش عدد سفارشات جدید فقط برای همین فروشگاه
    public static function getNavigationBadge(): ?string
    {
        $newOrdersCount = static::getModel()::where('bot_id', session('current_shop_id'))
            ->where('status', 'paid')
            ->count();

        return $newOrdersCount > 0 ? (string) $newOrdersCount : null;
    }

    // ⛔️ غیرفعال کردن دکمه حذف برای یک سفارش خاص
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    // ⛔️ غیرفعال کردن دکمه حذف گروهی (انتخاب چندتایی)
    public static function canDeleteAny(): bool
    {
        return false;
    }

    // 🔴 رنگ قرمز برای جلب توجه ادمین
    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Grid::make(3)
                    ->schema([
                        // 📦 بخش اول: اطلاعات اصلی سفارش
                        Section::make('اطلاعات سفارش')
                            ->columnSpan(2)
                            ->columns(2)
                            ->schema([
                                TextEntry::make('tracking_code')
                                    ->label('کد پیگیری')
                                    ->copyable()
                                    ->weight('bold')
                                    ->color('primary'),
                                TextEntry::make('status')
                                    ->label('وضعیت فعلی')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'paid' => 'success',
                                        'pending' => 'warning',
                                        'canceled', 'failed' => 'danger',
                                        'delivered' => 'info',
                                        default => 'gray',
                                    }),
                                TextEntry::make('total_amount')->label('مبلغ کل')->numeric()->suffix(' تومان'),
                                TextEntry::make('discount_amount')->label('تخفیف اعمال شده')->numeric()->suffix(' تومان'),

                                TextEntry::make('created_at')
                                    ->label('تاریخ ثبت سفارش')
                                    ->formatStateUsing(fn($state) => $state ? \Morilog\Jalali\Jalalian::fromDateTime($state)->format('Y/m/d ساعت H:i') : '-'),
                            ]),

                        // 👤 بخش دوم: اطلاعات مشتری و آدرس
                        Section::make('اطلاعات مشتری و ارسال')
                            ->columnSpan(1)
                            ->schema([
                                TextEntry::make('user.first_name')->label('نام مشتری اصلی'),
                                TextEntry::make('user.username')->label('یوزرنیم تلگرام')
                                    ->formatStateUsing(fn($state) => $state ? "@{$state}" : '-'),

                                // 📞 خواندن شماره تماس گیرنده از جدول آدرس‌ها
                                TextEntry::make('receiver_phone')
                                    ->label('شماره تماس گیرنده')
                                    ->state(function ($record) {
                                        // استفاده از همون فیلد مجازی که در مدل ساختید
                                        if ($record->user_address_id) {
                                            $address = \App\Models\UserAddress::find($record->user_address_id);
                                            // اگر آدرس پیدا شد شماره رو برگردون
                                            if ($address && $address->phone) {
                                                return $address->phone;
                                            }
                                        }
                                        // اگر آدرس نداشت، شماره ثبت شده روی خود یوزر رو نشون بده
                                        return $record->user->phone ?? 'ثبت نشده';
                                    })
                                    ->copyable(), // قابلیت کپی با یک کلیک برای ادمین

                                // 📍 خواندن متن کامل آدرس از جدول آدرس‌ها
                                TextEntry::make('postal_address')
                                    ->label('آدرس پستی گیرنده')
                                    ->state(function ($record) {
                                        if ($record->user_address_id) {
                                            $address = \App\Models\UserAddress::find($record->user_address_id);
                                            if ($address) {
                                                return $address->address;
                                            }
                                        }
                                        return 'ندارد';
                                    }),
                            ]),
                    ]),

                // 🛒 بخش سوم: لیست محصولات خریداری شده در این سفارش
                Section::make('اقلام سفارش (محصولات)')
                    ->schema([
                        // 👈 تغییر نام به items (دقیقاً نام متد در مدل Invoice)
                        RepeatableEntry::make('items')
                            ->label('')
                            ->columns(4)
                            ->schema([
                                // 💡 استفاده هوشمندانه از name_at_purchase در صورتی که محصول حذف شده باشد
                                TextEntry::make('product.name')
                                    ->label('نام محصول')
                                    ->state(fn($record) => $record->product->name ?? $record->name_at_purchase)
                                    ->weight('bold'),

                                TextEntry::make('quantity')->label('تعداد'),

                                TextEntry::make('price')->label('قیمت واحد')->numeric()->suffix(' تومان'),

                                // 👈 اصلاح محاسبه با استفاده از آبجکت (فلش -> به جای آرایه)
                                TextEntry::make('total_row_price')
                                    ->label('قیمت کل')
                                    ->state(fn($record): float => ($record->quantity ?? 1) * ($record->price ?? 0))
                                    ->numeric()
                                    ->suffix(' تومان')
                                    ->color('success'),
                            ])
                    ])
            ]);
    }
}
