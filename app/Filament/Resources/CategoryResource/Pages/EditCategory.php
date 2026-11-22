<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Category;
use App\Models\Product;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use App\Jobs\ApplyBulkDiscountJob;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

      protected function getHeaderActions(): array
    {
        return [
            // 🚀 منوی کشویی امکانات ویژه در هدر صفحه ویرایش
            ActionGroup::make([
                // 📦 انتقال کالا
                Action::make('move_products')
                    ->label('انتقال کالا')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('info')
                    ->visible(fn(Category $record) => $record->products()->exists())
                    ->form([
                        Select::make('target_category_id')
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

                        Notification::make()
                            ->success()
                            ->title('انتقال موفق')
                            ->body("تعداد {$updatedCount} محصول منتقل شد.")
                            ->send();

                        // ریدایرکت به همین صفحه برای آپدیت شدن اطلاعات
                        redirect(request()->header('Referer'));
                    }),

                // 💸 تخفیف گروهی
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
                        TextInput::make('percent')
                            ->label('درصد تخفیف')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(99)
                            ->required()
                            ->helperText('برای لغو تمام تخفیف‌های این دسته، عدد صفر (0) را وارد کنید.'),
                        TextInput::make('days')
                            ->label('مدت اعتبار (روز)')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->helperText('برای اینکه تخفیف هرگز منقضی نشود، عدد صفر (0) را وارد کنید.'),
                    ])
                    ->action(function (Category $record, array $data) {
                        $categoryIds = Category::where('parent_id', $record->id)->pluck('id')->toArray();
                        $categoryIds[] = $record->id;
                        $productsCount = Product::whereIn('category_id', $categoryIds)->count();
                        $adminChatId = auth()->user()->chat_id ?? null;

                        ApplyBulkDiscountJob::dispatch($record->bot, $record->id, $data['percent'], $data['days'], null, $adminChatId);

                        Notification::make()
                            ->success()
                            ->title('عملیات در پس‌زمینه آغاز شد')
                            ->body("درخواست تخفیف ارسال شد.")
                            ->send();
                    }),
            ])
            ->label('عملیات ویژه')
            ->icon('heroicon-m-sparkles')
            ->button() // ظاهر آن را در هدر تبدیل به یک دکمه زیبا می‌کند
            ->color('gray'),

            // 🗑 دکمه حذف استاندارد فیلامنت همراه با لایه‌های امنیتی شما
            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action, Category $record) {
                    if ($record->products()->exists()) {
                        Notification::make()->danger()->title('خطا در حذف')->body('این دسته‌بندی دارای محصول است.')->send();
                        $action->halt();
                    }
                    if ($record->children()->exists()) {
                        Notification::make()->danger()->title('خطا در حذف')->body('این دسته‌بندی دارای زیردسته است.')->send();
                        $action->halt();
                    }
                }),
        ];
    }

     protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

