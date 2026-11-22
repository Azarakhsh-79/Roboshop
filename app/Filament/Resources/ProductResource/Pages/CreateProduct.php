<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use App\Models\Product;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    // 🚀 جادوی پر کردن خودکار فرم هنگام کپی کردن محصول
    public function mount(): void
    {
        parent::mount();

        $sourceId = request()->query('source_id');

        if ($sourceId) {
            // واکشی محصول اصلی همراه با متغیرهای آن
            $product = Product::with('variants')->find($sourceId);

            if ($product && $product->bot_id == session('current_shop_id')) {
                $data = $product->toArray();

                // 🧹 پاک کردن اطلاعات اختصاصی که نباید کپی شوند
                unset($data['id'], $data['sku'], $data['created_at'], $data['updated_at'], $data['channel_message_id']);

                // تغییرات ظاهری برای جلوگیری از اشتباه ادمین
                $data['name'] = $data['name'] . ' (کپی)';
                $data['is_active'] = false; // محصول جدید به طور پیش‌فرض خاموش باشد

                // 📦 کپی کردن تمام متغیرها (سایز، رنگ، قیمت‌ها)
                if ($product->variants) {
                    $data['variants'] = $product->variants->map(function ($variant) {
                        return \Illuminate\Support\Arr::except($variant->toArray(), ['id', 'product_id', 'bot_id', 'created_at', 'updated_at']);
                    })->toArray();
                }

                // تزریق اطلاعات به فرم فیلامنت
                $this->form->fill($data);

                Notification::make()
                    ->title('اطلاعات بارگذاری شد')
                    ->body('اطلاعات محصول قبلی در فرم قرار گرفت. لطفاً کد SKU و تصاویر جدید را اضافه کرده و ذخیره کنید.')
                    ->info()
                    ->send();
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        // 🚀 خواندن وضعیت واقعی فرم قبل از اینکه فیلامنت آن را پاک کند
        $state = $this->form->getRawState();

        if (!empty($state['share_to_channel']) && $state['share_to_channel'] == true) {
            $this->shareToTelegramChannel($this->record);
        }
    }

    protected function shareToTelegramChannel($product)
    {
        $channelId = $product->bot->settings()->where('key', 'channel_id')->value('value');
        if ($channelId) {
            \App\Services\TelegramProductService::send($product, $channelId, true);
        }
    }

    // 🚀 جادوی نمایش خطاهای فرم به صورت پاپ‌آپ (Notification) یکپارچه
    protected function onValidationError(ValidationException $exception): void
    {
        // جمع‌آوری تمام خطاهای فیلدها و ساخت یک لیست متنی تمیز
        $errors = collect($exception->errors())->flatten()->unique()->implode('<br> ❌ ');

        Notification::make()
            ->title('خطا در اطلاعات وارد شده!')
            ->body(new HtmlString('لطفاً موارد زیر را اصلاح کنید:<br><br> ❌ ' . $errors))
            ->danger()
            ->duration(8000) // نمایش پاپ‌آپ برای ۸ ثانیه تا ادمین فرصت خواندن داشته باشد
            ->send();
    }
}
