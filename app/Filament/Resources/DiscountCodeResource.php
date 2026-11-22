<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DiscountCodeResource\Pages;
use App\Models\DiscountCode;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use Filament\Support\RawJs;

class DiscountCodeResource extends Resource
{
    protected static ?string $model = DiscountCode::class;
    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $modelLabel = 'کد تخفیف';
    protected static ?string $pluralModelLabel = 'کدهای تخفیف';
    protected static ?string $navigationGroup = 'فروشگاه';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('جزئیات کد تخفیف')
                    ->schema([
                        // 🔒 مقداردهی خودکار و مخفی آیدی فروشگاه
                        Forms\Components\Hidden::make('bot_id')
                            ->default(fn() => session('current_shop_id'))
                            ->required(),

                        Forms\Components\TextInput::make('code')
                            ->label('کد تخفیف')
                            ->required()
                            ->unique(
                                modifyRuleUsing: fn (Unique $rule) => $rule->where('bot_id', session('current_shop_id')),
                                ignoreRecord: true
                            )
                            ->helperText('مشتری با وارد کردن این کد در سبد خرید، تخفیف می‌گیرد. مثال: YALDA1403')
                            ->columnSpanFull(),

                        Forms\Components\Select::make('type')
                            ->label('نوع تخفیف')
                            ->options([
                                'percentage' => 'درصدی (٪)',
                                'fixed_amount' => 'مبلغ ثابت (تومان)',
                            ])
                            ->required()
                            ->default('percentage')
                            ->live(),

                        Forms\Components\TextInput::make('value')
                            ->label('مقدار تخفیف')
                            ->required()
                            ->numeric()
                            ->mask(RawJs::make('$money($input)'))
                            ->stripCharacters(',')
                            ->suffix(fn (Forms\Get $get) => $get('type') === 'percentage' ? '٪' : 'تومان')
                            ->helperText(fn (Forms\Get $get) => $get('type') === 'percentage' ? 'یک عدد بین 1 تا 100 وارد کنید.' : 'مبلغ تخفیف را به تومان وارد کنید.')
                            ->rule(fn (Forms\Get $get) => $get('type') === 'percentage' ? 'max:100' : null),

                        Forms\Components\TextInput::make('min_purchase_amount')
                            ->label('حداقل مبلغ خرید')
                            ->numeric()
                            ->default(0)
                            ->mask(RawJs::make('$money($input)'))
                            ->stripCharacters(',')
                            ->prefix('تومان')
                            ->helperText('کد تخفیف فقط در صورتی کار می‌کند که جمع مبلغ سبد خرید از این عدد بیشتر باشد (عدد 0 یعنی بدون سقف).'),

                        Forms\Components\TextInput::make('usage_limit')
                            ->label('محدودیت تعداد استفاده')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('این کد کلاً چند بار می‌تواند توسط مشتریان استفاده شود؟ (خالی = نامحدود)'),

                        // 🚀 فیلد جدید: دریافت تعداد روز به جای تاریخ
                        Forms\Components\TextInput::make('expires_in_days')
                            ->label('اعتبار کد (به روز)')
                            ->numeric()
                            ->minValue(1)
                            ->suffix('روز')
                            ->helperText('مثلاً 5 یعنی این کد تا 5 روز آینده (پایان شب) معتبر است. برای دائمی بودن خالی بگذارید.')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                // 🧮 محاسبه هوشمند: به تاریخ امروز روزها را اضافه می‌کنیم و آن را روی پایان روز (ساعت 23:59:59) ست می‌کنیم
                                $set('expires_at', $state ? now()->addDays((int) $state)->endOfDay()->toDateTimeString() : null);
                            })
                            ->formatStateUsing(function ($record) {
                                // 💡 هنگام ویرایش، تاریخ دیتابیس را خوانده و تبدیل به روز باقیمانده می‌کند تا ادمین عدد را ببیند
                                if ($record && $record->expires_at && $record->expires_at->isFuture()) {
                                    return now()->startOfDay()->diffInDays($record->expires_at->startOfDay());
                                }
                                return null;
                            })
                            ->dehydrated(false), // 🚫 این فیلد مجازی است و مستقیماً در دیتابیس ذخیره نمی‌شود

                        // 🔒 فیلد مخفی برای ذخیره تاریخ محاسبه شده در دیتابیس
                        Forms\Components\Hidden::make('expires_at'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('وضعیت فعالیت')
                            ->default(true)
                            ->inline(false)
                            ->helperText('برای غیرفعال کردن موقت این کد، می‌توانید این گزینه را خاموش کنید.'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('کد تخفیف')
                    ->searchable()
                    ->copyable()
                    ->weight('bold')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('type')
                    ->label('نوع')
                    ->formatStateUsing(fn ($state) => $state === 'percentage' ? 'درصدی' : 'مبلغ ثابت')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('value')
                    ->label('مقدار تخفیف')
                    ->formatStateUsing(fn ($state, $record) => $record->type === 'percentage' ? "{$state}٪" : number_format($state) . ' ت')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('usage_limit')
                    ->label('ظرفیت کل')
                    ->formatStateUsing(fn ($state) => $state ?? 'نامحدود'),

                Tables\Columns\TextColumn::make('usage_count')
                    ->label('استفاده شده')
                    ->badge()
                    ->color(fn ($record) => ($record->usage_limit && $record->usage_count >= $record->usage_limit) ? 'danger' : 'success'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('فعال'),

               Tables\Columns\TextColumn::make('expires_at')
                    ->label('انقضا')
                    ->formatStateUsing(fn ($state) => $state ? \Morilog\Jalali\Jalalian::fromDateTime($state)->format('Y/m/d H:i') : 'بدون انقضا')
                    ->sortable()
                    ->color(fn ($record) => ($record->expires_at && $record->expires_at->isPast()) ? 'danger' : 'gray'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('وضعیت')
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    // 🔒 ایزوله کردن نمایش داده‌ها (فقط کدهای تخفیف همین فروشگاه)
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('bot_id', session('current_shop_id'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDiscountCodes::route('/'),
            'create' => Pages\CreateDiscountCode::route('/create'),
            'edit' => Pages\EditDiscountCode::route('/{record}/edit'),
        ];
    }
}
