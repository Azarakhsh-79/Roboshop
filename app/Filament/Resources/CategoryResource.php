<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\TextInput;
use App\Jobs\ApplyBulkDiscountJob;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $modelLabel = 'دسته‌بندی';
    protected static ?string $pluralModelLabel = 'دسته‌بندی‌ها';
    protected static ?string $navigationGroup = 'فروشگاه';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('اطلاعات دسته‌بندی')
                    ->schema([
                        Forms\Components\Hidden::make('bot_id')
                            ->default(fn() => session('current_shop_id'))
                            ->required(),

                        Forms\Components\TextInput::make('name')
                            ->label('نام دسته‌بندی')
                            ->required()
                            ->helperText('میتونید از ایموجی هم استفاده کنید، اما مراقب باشید که نام دسته‌بندی تکراری در همان سطح نداشته باشید!')
                            ->maxLength(255)
                            ->unique(
                                modifyRuleUsing: function (Unique $rule, Forms\Get $get) {
                                    $parentId = $get('parent_id');
                                    return $rule
                                        ->where('bot_id', session('current_shop_id'))
                                        ->when(
                                            $parentId,
                                            fn($query) => $query->where('parent_id', $parentId),
                                            fn($query) => $query->whereNull('parent_id')
                                        );
                                },
                                ignoreRecord: true
                            )
                            ->validationMessages([
                                'unique' => 'یک دسته‌بندی با همین نام دقیقاً در همین سطح وجود دارد!',
                            ]),

                        \Filament\Forms\Components\Textarea::make('description')
                            ->label('توضیحات دسته‌بندی')
                            ->placeholder('مثال: در این بخش بهترین محصولات دیجیتال را با گارانتی اصالت کالا مشاهده می‌کنید...')
                            ->helperText('این متن در ربات تلگرام به عنوان متن معرفی و خوش‌آمدگوییِ این دسته‌بندی نمایش داده می‌شود.')
                            ->rows(4)
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('parent_id')
                            ->label('قرارگیری به عنوان زیرمجموعه (اختیاری)')
                            ->relationship(
                                name: 'parent',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query, ?Category $record) {
                                    $query->where('bot_id', session('current_shop_id'))
                                        ->whereNull('parent_id');
                                    if ($record) {
                                        $query->where('id', '!=', $record->id);
                                    }

                                    $categoriesWithProducts = Product::distinct()->pluck('category_id')->toArray();
                                    if (!empty($categoriesWithProducts)) {
                                        $query->whereNotIn('id', $categoriesWithProducts);
                                    }
                                }
                            )
                            ->getOptionLabelFromRecordUsing(fn($record) => "📁 {$record->name}")
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->placeholder('بدون زیرمجموعه (این یک دسته‌بندی اصلی است)')
                            ->helperText('اگر می‌خواهید این دسته خودش یک "دسته‌بندی اصلی" باشد، این فیلد را خالی بگذارید.'),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('ترتیب نمایش (اختیاری)')
                            ->numeric()
                            ->default(0)
                            ->helperText('اگر می‌خواهید این دسته اول قرار گیرد 0 بگذارید. سیستم خودکار سایر دسته‌ها را مرتب می‌کند.'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('فعال بودن دسته‌بندی')
                            ->default(true),

                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        // 🚀 متد فرمت کردن نام و ساختار درختی
        $formatNameClosure = function ($state, $record) {
            static $treeCache = null;

            if ($treeCache === null) {
                $allCats = Category::where('bot_id', session('current_shop_id'))
                    ->select('id', 'parent_id', 'sort_order')
                    ->orderBy('sort_order')
                    ->get();

                $treeCache = [];
                $parents = $allCats->whereNull('parent_id')->values();
                foreach ($parents as $pIndex => $parent) {
                    $pNum = $pIndex + 1;
                    $treeCache[$parent->id] = (string) $pNum;

                    $children = $allCats->where('parent_id', $parent->id)->values();
                    foreach ($children as $cIndex => $child) {
                        $cNum = $cIndex + 1;
                        $treeCache[$child->id] = "{$pNum}.{$cNum}";
                    }
                }
            }

            $prefix = $treeCache[$record->id] ?? '';
            $name = $state ?? $record->name;

            if ($record->parent_id) {
                return "&nbsp;&nbsp;&nbsp;&nbsp;<span class='text-gray-400 font-mono text-sm ml-1'>{$prefix}</span> <span class='text-gray-600'>{$name}</span>";
            } else {
                return "<span class='text-primary-600 font-mono font-bold text-base ml-1'>{$prefix}</span> <span class='text-primary-600 font-bold'>{$name}</span>";
            }
        };

        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $categories = Category::where('bot_id', session('current_shop_id'))->get();
                $parentSortOrders = $categories->whereNull('parent_id')->pluck('sort_order', 'id')->toArray();

                $orderedIds = $categories->sortBy(function ($category) use ($parentSortOrders) {
                    $parentOrder = $category->parent_id ? ($parentSortOrders[$category->parent_id] ?? 9999) : $category->sort_order;
                    $isChild = $category->parent_id ? 1 : 0;
                    return sprintf('%06d-%d-%06d', $parentOrder, $isChild, $category->sort_order);
                })->pluck('id')->toArray();

                if (!empty($orderedIds)) {
                    $idsString = implode(',', $orderedIds);
                    $query->orderByRaw("FIELD(id, {$idsString})");
                }
            })
            ->columns([

                // 💻📱 ستون نام و توضیحات: در هر دو دستگاه (موبایل و دسکتاپ) نمایش داده می‌شود
                Tables\Columns\TextColumn::make('name')
                    ->label('نام دسته‌بندی')
                    ->formatStateUsing($formatNameClosure)
                    ->html()
                    ->searchable()
                    ->sortable(false)
                    ->wrap() // 👈 باعث می‌شود متون طولانی در موبایل بشکنند و اسکرول ایجاد نکنند
                    ->description(fn($record) => \Illuminate\Support\Str::limit($record->description, 45)),

                // 💻📱 ستون تعداد: در هر دو دستگاه نمایش داده می‌شود
                Tables\Columns\TextColumn::make('products_count')
                    ->counts('products')
                    ->label('کالا') // نام کوتاه‌تر برای جا شدن در موبایل
                    ->badge()
                    ->color('success'),

                // 💻📱 ستون وضعیت: در هر دو دستگاه نمایش داده می‌شود
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('وضعیت'),

                // 💻 ستون ترتیب: فقط در دسکتاپ و تبلت
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('ترتیب')
                    ->color('gray')
                    ->visibleFrom('md'), // 👈 جادوی رسپانسیو: مخفی شدن در موبایل

                // 💻 ستون تاریخ: فقط در دسکتاپ و تبلت
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ ایجاد')
                    ->dateTime('Y/m/d')
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visibleFrom('md'), // 👈 جادوی رسپانسیو: مخفی شدن در موبایل

            ])
            ->paginated([10, 25, 50, 100, 'all'])
            ->filters([
                Tables\Filters\Filter::make('category_type')
                    ->form([
                        \Filament\Forms\Components\Select::make('type')
                            ->label('نحوه نمایش')
                            ->options([
                                'parents' => 'فقط دسته‌های اصلی (والد)',
                                'children' => 'فقط زیردسته‌ها (فرزند)',
                            ])
                            ->placeholder('نمایش کامل (ساختار درختی)'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['type']) {
                            'parents' => $query->whereNull('parent_id'),
                            'children' => $query->whereNotNull('parent_id'),
                            default => $query,
                        };
                    }),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('وضعیت')
                    ->native(false),
            ])
            ->actions([
                // ⚙️ منوی کشویی سه نقطه
                \Filament\Tables\Actions\ActionGroup::make([

                    // انتقال کالا
                    Action::make('move_products')
                        ->label('انتقال کالا')
                        ->icon('heroicon-o-arrows-right-left')
                        ->color('info')
                        ->visible(fn(Category $record) => $record->products()->exists())
                        ->form([
                            \Filament\Forms\Components\Select::make('target_category_id')
                                ->label('دسته‌بندی مقصد')
                                ->required()
                                ->options(function (Category $record) {
                                    $categoriesWithChildren = Category::whereNotNull('parent_id')->pluck('parent_id')->toArray();
                                    return Category::where('id', '!=', $record->id)
                                        ->where('bot_id', session('current_shop_id'))
                                        ->whereNotIn('id', $categoriesWithChildren)
                                        ->pluck('name', 'id');
                                })
                                ->searchable()
                                ->optionsLimit(5)
                                ->helperText('دقت کنید دسته‌هایی که دارای زیردسته هستند مجاز به پذیرش محصول نیستند.'),
                        ])
                        ->action(function (Category $record, array $data) {
                            $targetId = $data['target_category_id'];
                            $targetCat = Category::find($targetId);

                            if (!$targetCat) {
                                Notification::make()->danger()->title('خطا')->body('دسته مقصد یافت نشد!')->send();
                                return;
                            }

                            $updatedCount = Product::where('category_id', $record->id)->update(['category_id' => $targetId]);
                            Notification::make()->success()->title('انتقال موفق')->body("تعداد {$updatedCount} محصول منتقل شد.")->send();
                        }),

                    // تخفیف گروهی
                    Action::make('bulk_discount')
                        ->label('تخفیف گروهی')
                        ->icon('heroicon-o-receipt-percent')
                        ->color('warning')
                        ->modalHeading(fn($record) => "اعمال تخفیف روی بخش: {$record->name}")
                        ->modalDescription('این تخفیف روی تمامی محصولات این دسته و زیردسته‌های آن اعمال می‌شود. ربات به صورت خودکار پیام‌های کانال را آپدیت خواهد کرد.')
                        ->modalSubmitActionLabel('شروع پردازش')
                        ->beforeFormFilled(function (Action $action, Category $record) {
                            $categoryIds = Category::where('parent_id', $record->id)->pluck('id')->toArray();
                            $categoryIds[] = $record->id;
                            $productsCount = Product::whereIn('category_id', $categoryIds)->count();

                            if ($productsCount === 0) {
                                Notification::make()->danger()->title('توقف عملیات')->body('هیچ محصولی یافت نشد!')->send();
                                $action->halt();
                            }
                        })
                        ->form([
                            TextInput::make('percent')->label('درصد تخفیف')->numeric()->minValue(0)->maxValue(99)->required(),
                            TextInput::make('days')->label('مدت اعتبار (روز)')->numeric()->minValue(0)->required(),
                        ])
                        ->action(function (Category $record, array $data) {
                            $categoryIds = Category::where('parent_id', $record->id)->pluck('id')->toArray();
                            $categoryIds[] = $record->id;
                            $productsCount = Product::whereIn('category_id', $categoryIds)->count();
                            $adminChatId = auth()->user()->chat_id ?? null;

                            ApplyBulkDiscountJob::dispatch($record->bot, $record->id, $data['percent'], $data['days'], null, $adminChatId);

                            Notification::make()->success()->title('عملیات در پس‌زمینه آغاز شد')->body("درخواست تخفیف ارسال شد.")->send();
                        }),

                    // ویرایش
                    Tables\Actions\EditAction::make(),

                    // حذف امن
                    Tables\Actions\DeleteAction::make()
                        ->before(function (Tables\Actions\DeleteAction $action, Category $record) {
                            if ($record->products()->exists()) {
                                Notification::make()->danger()->title('خطا در حذف')->body('این دسته‌بندی دارای محصول است.')->send();
                                $action->halt();
                            }
                            if ($record->children()->exists()) {
                                Notification::make()->danger()->title('خطا در حذف')->body('این دسته‌بندی دارای زیردسته است.')->send();
                                $action->halt();
                            }
                        }),
                ])
                    ->label('عملیات')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip('عملیات بیشتر'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('bot_id', session('current_shop_id'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
