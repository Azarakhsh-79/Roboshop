<?php

namespace App\Filament\Superadmin\Resources;

use App\Filament\Superadmin\Resources\PlanResource\Pages;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;
    protected static ?string $navigationIcon = 'heroicon-o-star';
    protected static ?string $navigationLabel = 'پلن‌های اشتراک';
    protected static ?string $modelLabel = 'پلن';
    protected static ?string $pluralModelLabel = 'پلن‌ها';
    protected static ?int $navigationSort = 4; // ترتیب نمایش در سایدبار

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // 📦 بخش اول: اطلاعات پایه
                Forms\Components\Section::make('اطلاعات پایه')
                    ->description('نام و توضیحات اصلی پلن را وارد کنید')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('نام پلن (مثال: ویژه، پایه)')
                                ->required()
                                ->live(onBlur: true)
                                // 💡 تولید خودکار نامک (Slug) از روی نام پلن
                                ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),

                            Forms\Components\TextInput::make('slug')
                                ->label('شناسه سیستم (Slug)')
                                ->required()
                                ->unique(ignoreRecord: true),
                        ]),

                        Forms\Components\Textarea::make('description')
                            ->label('توضیحات کوتاه پلن')
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('sort_order')
                                ->label('ترتیب نمایش')
                                ->numeric()
                                ->default(0)
                                ->helperText('پلن‌ها بر اساس این عدد (از کوچک به بزرگ) به کاربران نمایش داده می‌شوند.'),

                            Forms\Components\Toggle::make('is_active')
                                ->label('وضعیت فعالیت')
                                ->default(true)
                                ->inline(false),
                        ]),
                    ]),

                // ⚙️ بخش دوم: محدودیت‌ها و دسترسی‌ها
                Forms\Components\Section::make('دسترسی‌ها و ویژگی‌ها')
                    ->description('تنظیم امکانات و سقف مجاز برای این پلن')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->schema([

                        // 🏷 ورودی تگ برای لیست کردن ویژگی‌های نمایشی
                        Forms\Components\TagsInput::make('features')
                            ->label('ویژگی‌های نمایشی (برای صفحه خرید)')
                            ->placeholder('افزودن ویژگی...')
                            ->helperText('مثال: "پشتیبانی ۲۴ ساعته" را بنویسید و Enter بزنید.')
                            ->columnSpanFull(),

                        // 🔑 کلید-مقدار برای محدودیت‌های برنامه‌نویسی
                        Forms\Components\KeyValue::make('limits')
                            ->label('محدودیت‌های سیستمی')
                            ->keyLabel('نام متغیر (انگلیسی)')
                            ->valueLabel('مقدار مجاز')
                            ->addActionLabel('افزودن محدودیت جدید')
                            ->helperText('مثال => کلید: max_admins | مقدار: 1 (برای نامحدود می‌توانید از کلمه unlimited یا عدد -1 استفاده کنید)')
                            ->columnSpanFull(),
                    ]),

                // 💳 بخش سوم: قیمت‌گذاری
                Forms\Components\Section::make('بسته‌های قیمت‌گذاری')
                    ->description('دوره‌های زمانی مختلف و قیمت آن‌ها را مشخص کنید')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        // 🔁 تکرارکننده (Repeater) برای ساختن بازه‌های قیمتی
                        Forms\Components\Repeater::make('prices')
                            ->label('')
                            ->addActionLabel('افزودن دوره زمانی جدید')
                            ->schema([
                                Forms\Components\TextInput::make('duration_months')
                                    ->label('مدت زمان (به ماه)')
                                    ->numeric()
                                    ->required()
                                    ->placeholder('مثال: 3'),

                                Forms\Components\TextInput::make('price')
                                    ->label('قیمت کل (تومان)')
                                    ->numeric()
                                    ->required()
                                    ->placeholder('مثال: 150000'),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('نام پلن')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('slug')
                    ->label('شناسه')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('ترتیب')
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('وضعیت'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('آخرین ویرایش')
                    ->dateTime('Y/m/d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order', 'asc') // مرتب‌سازی پیش‌فرض بر اساس ترتیب شما
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
