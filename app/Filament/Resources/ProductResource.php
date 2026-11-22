<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Support\Str;
use Filament\Support\RawJs;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $modelLabel = 'محصول';

    protected static ?string $pluralModelLabel = 'محصولات';

    protected static ?string $navigationGroup = 'فروشگاه';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('product_tabs')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('مشخصات اصلی')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Forms\Components\Hidden::make('bot_id')
                                    ->default(fn() => session('current_shop_id'))
                                    ->required(),

                                Forms\Components\TextInput::make('name')
                                    ->label('نام محصول')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(
                                        modifyRuleUsing: fn(Unique $rule) => $rule->where('bot_id', session('current_shop_id')),
                                        ignoreRecord: true
                                    )
                                    ->validationMessages([
                                        'required' => 'وارد کردن «نام محصول» الزامی است.',
                                        'unique' => 'شما قبلاً محصولی دقیقاً با همین نام در فروشگاه خود ثبت کرده‌اید!',
                                    ]),

                                Forms\Components\TextInput::make('sku')
                                    ->label('کد محصول (SKU)')
                                    ->nullable()
                                    ->unique(
                                        modifyRuleUsing: fn(Unique $rule) => $rule->where('bot_id', session('current_shop_id')),
                                        ignoreRecord: true
                                    )
                                    ->placeholder('مثال: 1005'),

                                Forms\Components\Select::make('category_id')
                                    ->label('دسته‌بندی')
                                    ->required()
                                    ->searchable()
                                    ->validationMessages([
                                        'required' => 'انتخاب «دسته‌بندی» برای محصول الزامی است.',
                                    ])
                                    ->options(function () {
                                        $categories = \App\Models\Category::where('bot_id', session('current_shop_id'))
                                            ->doesntHave('children')
                                            ->with('parent')
                                            ->get();

                                        $options = [];
                                        foreach ($categories as $category) {
                                            $options[$category->id] = $category->parent_id
                                                ? "{$category->parent->name} » {$category->name}"
                                                : "📁 {$category->name}";
                                        }

                                        return $options;
                                    }),

                                Forms\Components\RichEditor::make('description')
                                    ->label('توضیحات محصول')
                                    ->toolbarButtons([
                                        'bold',
                                        'italic',
                                        'underline',
                                        'strike',
                                        'h2',
                                        'h3',
                                        'bulletList',
                                        'orderedList',
                                        'link',
                                        'redo',
                                        'undo',
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('قیمت‌گذاری و انبار')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('price')
                                        ->label('قیمت فروش (تومان)')
                                        ->numeric()
                                        ->required()
                                        ->minValue(0)
                                        ->mask(RawJs::make('$money($input)'))
                                        ->stripCharacters(',')
                                        ->prefix('تومان')
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                            $variants = $get('variants') ?? [];
                                            foreach ($variants as $key => $variant) {
                                                if (empty($variant['price'])) {
                                                    $set("variants.{$key}.price", $state);
                                                }
                                            }
                                        })
                                        ->validationMessages([
                                            'required' => 'لطفاً «قیمت فروش» را وارد کنید.',
                                            'min' => 'قیمت فروش نمی‌تواند منفی باشد.',
                                        ]),

                                    Forms\Components\TextInput::make('purchase_price')
                                        ->label('قیمت خرید (تومان)')
                                        ->numeric()
                                        ->required()
                                        ->minValue(0)
                                        ->mask(RawJs::make('$money($input)'))
                                        ->stripCharacters(',')
                                        ->prefix('تومان')
                                        ->live(onBlur: true)
                                        ->validationMessages([
                                            'required' => 'لطفاً «قیمت خرید» را وارد کنید.',
                                            'min' => 'قیمت خرید نمی‌تواند منفی باشد.',
                                        ]),
                                ]),

                                Forms\Components\Grid::make(3)->schema([
                                    Forms\Components\TextInput::make('discount_price')
                                        ->label('با تخفیف')
                                        ->numeric()
                                        ->lt('price')
                                        ->mask(RawJs::make('$money($input)'))
                                        ->stripCharacters(',')
                                        ->prefix('تومان')
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                            $variants = $get('variants') ?? [];
                                            foreach ($variants as $key => $variant) {
                                                if (empty($variant['discount_price'])) {
                                                    $set("variants.{$key}.discount_price", $state);
                                                }
                                            }
                                        })
                                        ->validationMessages([
                                            'lt' => '❌ مبلغ تخفیف باید کمتر از قیمت اصلی محصول باشد!',
                                        ]),

                                    Forms\Components\TextInput::make('discount_days')
                                        ->label('اعتبار تخفیف (به روز)')
                                        ->numeric()
                                        ->suffix('روز')
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Forms\Set $set, $state) {
                                            $set('discount_expires_at', $state ? now()->addDays((int) $state)->endOfDay()->toDateTimeString() : null);
                                        })
                                        ->formatStateUsing(function ($record) {
                                            if ($record && $record->discount_expires_at && $record->discount_expires_at->isFuture()) {
                                                return (int) now()->startOfDay()->diffInDays($record->discount_expires_at->startOfDay());
                                            }

                                            return null;
                                        })
                                        ->dehydrated(false)
                                        ->validationMessages([
                                            'numeric' => 'اعتبار تخفیف باید یک عدد باشد.',
                                        ]),

                                    Forms\Components\Placeholder::make('profit_estimate')
                                        ->label('تخمین سود خالص (هر عدد)')
                                        ->content(function (Forms\Get $get): \Illuminate\Support\HtmlString {
                                            $price = (int) str_replace(',', '', $get('price') ?? 0);
                                            $purchase = (int) str_replace(',', '', $get('purchase_price') ?? 0);
                                            $discountPrice = (int) str_replace(',', '', $get('discount_price') ?? 0);

                                            if ($price <= 0 || $purchase <= 0) {
                                                return new \Illuminate\Support\HtmlString('<span style="color: gray;">—</span>');
                                            }

                                            $finalPrice = ($discountPrice > 0 && $discountPrice < $price) ? $discountPrice : $price;

                                            $profit = $finalPrice - $purchase;
                                            $percent = $finalPrice > 0 ? round(($profit / $finalPrice) * 100, 1) : 0;

                                            if ($profit < 0) {
                                                return new \Illuminate\Support\HtmlString("<span style='color: #ef4444; font-weight: bold;'>ضرر: " . number_format(abs($profit)) . " تومان ({$percent}٪) ⚠️</span>");
                                            }

                                            return new \Illuminate\Support\HtmlString("<span style='color: #10b981; font-weight: bold;'>سود: " . number_format($profit) . " تومان ({$percent}٪) ✅</span>");
                                        }),
                                ]),

                                Forms\Components\Hidden::make('discount_expires_at'),

                                Forms\Components\TextInput::make('stock')
                                    ->label('موجودی انبار')
                                    ->numeric()
                                    ->required()
                                    ->default(0)
                                    ->validationMessages([
                                        'required' => 'وارد کردن «موجودی انبار» الزامی است.',
                                        'numeric' => 'موجودی انبار باید یک عدد باشد.',
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('ویژگی‌های متغیر')
                            ->icon('heroicon-o-swatch')
                            ->schema([
                                Forms\Components\Repeater::make('variants')
                                    ->relationship()
                                    ->label('')
                                    ->addActionLabel('افزودن متغیر جدید (مثل سایز/رنگ)')
                                    ->schema([
                                        Forms\Components\Hidden::make('bot_id')
                                            ->default(fn() => session('current_shop_id')),

                                        Forms\Components\TextInput::make('variant_name')
                                            ->label('نام متغیر')
                                            ->placeholder('مثلاً: قرمز - XL')
                                            ->required()
                                            ->distinct()
                                            ->validationMessages([
                                                'required' => 'وارد کردن «نام متغیر» الزامی است.',
                                                'distinct' => 'شما این ویژگی را یک بار وارد کرده‌اید. نام ویژگی‌ها نباید تکراری باشد!',
                                            ]),

                                        Forms\Components\TextInput::make('price')
                                            ->label('قیمت')
                                            ->numeric()
                                            ->required()
                                            ->mask(RawJs::make('$money($input)'))
                                            ->stripCharacters(',')
                                            ->prefix('تومان')
                                            ->default(fn(Forms\Get $get) => $get('../../price'))
                                            ->validationMessages([
                                                'required' => 'وارد کردن «قیمت» برای متغیر الزامی است.',
                                            ]),

                                        Forms\Components\TextInput::make('discount_price')
                                            ->label('با تخفیف')
                                            ->numeric()
                                            ->lt('price')
                                            ->mask(RawJs::make('$money($input)'))
                                            ->stripCharacters(',')
                                            ->prefix('تومان')
                                            ->default(fn(Forms\Get $get) => $get('../../discount_price'))
                                            ->validationMessages([
                                                'lt' => 'مبلغ تخفیف باید کمتر از قیمت همین متغیر باشد!',
                                            ]),

                                        Forms\Components\TextInput::make('stock')
                                            ->label('موجودی')
                                            ->numeric()
                                            ->required()
                                            ->default(0)
                                            ->validationMessages([
                                                'required' => 'وارد کردن «موجودی» برای متغیر الزامی است.',
                                                'numeric' => 'موجودی باید یک عدد باشد.',
                                            ]),

                                        Forms\Components\Toggle::make('is_active')
                                            ->label('فعال')
                                            ->default(true),
                                    ])
                                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                                    ->defaultItems(1)
                                    ->collapsible()
                                    ->itemLabel(fn(array $state): ?string => $state['variant_name'] ?? null),
                            ]),

                        Forms\Components\Tabs\Tab::make('تصاویر و انتشار')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Forms\Components\Repeater::make('images')
                                    ->relationship()
                                    ->label('گالری تصاویر')
                                    ->addActionLabel('افزودن تصویر')
                                    ->schema([
                                        Forms\Components\Hidden::make('bot_id')
                                            ->default(fn() => session('current_shop_id')),

                                        Forms\Components\Hidden::make('type')
                                            ->default('photo'),

                                        Forms\Components\FileUpload::make('file_id')
                                            ->label('آپلود تصویر')
                                            ->image()
                                            ->directory('product-images')
                                            ->imageEditor()
                                            ->saveUploadedFileUsing(function (TemporaryUploadedFile $file): string {
                                                return static::processUploadedImage($file);
                                            })
                                            ->required()
                                            ->columnSpanFull()
                                            ->validationMessages([
                                                'required' => 'انتخاب تصویر الزامی است.',
                                            ]),
                                    ])
                                    ->orderColumn('sort_order')
                                    ->reorderableWithDragAndDrop()
                                    ->defaultItems(0)
                                    ->maxItems(10)
                                    ->grid(2)
                                    ->collapsible(),

                                Forms\Components\Section::make('تنظیمات انتشار')
                                    ->schema([
                                        Forms\Components\Toggle::make('is_active')
                                            ->label('نمایش در فروشگاه')
                                            ->default(true),

                                        Forms\Components\Toggle::make('share_to_channel')
                                            ->label('ارسال/آپدیت در کانال تلگرام 📣')
                                            ->default(true)
                                            ->dehydrated(false),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->columnSpanFull()
                    ->activeTab(1),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // 💻📱 تصویر محصول در همه دستگاه‌ها
                Tables\Columns\ImageColumn::make('cover_image')
                    ->label('تصویر')
                    ->circular()
                    ->defaultImageUrl(url('/images/placeholder.png'))
                    ->getStateUsing(fn(Product $record): ?string => $record->images->first()?->file_id),

                // 💻📱 نام محصول + توضیحات (کد و دسته در موبایل)
                Tables\Columns\TextColumn::make('name')
                    ->label('نام محصول')
                    ->searchable()
                    ->sortable()
                    ->wrap() // 👈 جلوگیری از اسکرول افقی در موبایل
                    ->limit(40)
                    // 🌟 جادوی موبایل: کد و دسته محصول زیر نام آن قرار می‌گیرد اما با کلاس md:hidden در دسکتاپ مخفی می‌شود
                    ->description(function (Product $record) {
                        $sku = $record->sku ? "کد: {$record->sku}" : '';
                        $catName = $record->category ? $record->category->name : '';
                        $cat = $catName ? "دسته: {$catName}" : '';

                        $desc = collect([$sku, $cat])->filter()->implode(' | ');
                        if (!$desc)
                            return null;

                        return new \Illuminate\Support\HtmlString("<span class='md:hidden text-xs text-gray-500 mt-1 block'>{$desc}</span>");
                    }),

                // 💻 فقط دسکتاپ: کد محصول
                Tables\Columns\TextColumn::make('sku')
                    ->label('کد (SKU)')
                    ->searchable()
                    ->color('gray')
                    ->visibleFrom('md'), // 👈 جادوی رسپانسیو

                // 💻 فقط دسکتاپ: دسته‌بندی
                Tables\Columns\TextColumn::make('category.name')
                    ->label('دسته‌بندی')
                    ->formatStateUsing(function ($state, $record) {
                        return ($record->category && $record->category->parent_id)
                            ? "{$record->category->parent->name} » {$state}"
                            : "📁 {$state}";
                    })
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->visibleFrom('md'), // 👈 جادوی رسپانسیو

                // 💻📱 قیمت: نمایش در همه دستگاه‌ها
                Tables\Columns\TextColumn::make('display_price')
                    ->label('قیمت (تومان)') // لیبل کوتاه‌تر
                    ->getStateUsing(function (Product $record): string {
                        $price = $record->getFinalPrice();
                        return number_format($price);
                    })
                    ->sortable()
                    ->badge()
                    ->color('success'),

                // 💻📱 موجودی: نمایش در همه دستگاه‌ها
                Tables\Columns\TextColumn::make('stock')
                    ->label('موجودی')
                    ->sortable()
                    ->badge()
                    ->color(fn(int $state): string => match (true) {
                        $state === 0 => 'danger',
                        $state <= 5 => 'warning',
                        default => 'success',
                    }),

                // 💻📱 وضعیت نمایش
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('وضعیت'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('وضعیت نمایش')
                    ->native(false),

                Tables\Filters\SelectFilter::make('category_id')
                    ->label('دسته‌بندی')
                    ->searchable()
                    ->options(function () {
                        $categories = \App\Models\Category::where('bot_id', session('current_shop_id'))
                            ->doesntHave('children')
                            ->with('parent')
                            ->get();

                        $options = [];
                        foreach ($categories as $category) {
                            $options[$category->id] = $category->parent_id
                                ? "{$category->parent->name} » {$category->name}"
                                : "📁 {$category->name}";
                        }

                        return $options;
                    }),
            ])
            ->actions([
                // ⚙️ منوی کشویی سه نقطه برای نظم دادن به دکمه‌ها
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\RestoreAction::make(),

                    // 🚀 دکمه کپی با انتقال به فرم ایجاد محصول
                    Tables\Actions\Action::make('duplicate')
                        ->label('کپی محصول')
                        ->icon('heroicon-o-document-duplicate')
                        ->color('warning')
                        ->url(fn(Product $record): string => ProductResource::getUrl('create', ['source_id' => $record->id])),
                ])
                    ->label('عملیات')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip('عملیات بیشتر'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    protected static function processUploadedImage(TemporaryUploadedFile $file): string
    {
        $filename = Str::uuid() . '.webp';
        $path = 'product-images/' . $filename;
        $absolutePath = storage_path('app/public/' . $path);

        if (!file_exists(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0755, true);
        }

        $content = file_get_contents($file->getRealPath());
        $image = imagecreatefromstring($content);

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width > 1200) {
            $newWidth = 1200;
            $newHeight = (int) ($height * ($newWidth / $width));
            $resized = imagecreatetruecolor($newWidth, $newHeight);

            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);

            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        imagewebp($image, $absolutePath, 85);
        imagedestroy($image);
        chmod($absolutePath, 0644);

        return $path;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('bot_id', session('current_shop_id'))
            ->with('category.parent')
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
