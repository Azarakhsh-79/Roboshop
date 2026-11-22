<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use App\Models\Product; // 👈 کلاس مدل اضافه شد

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
           // 🚀 دکمه‌های امکانات ویژه در هدر صفحه ویرایش محصول
            Actions\ActionGroup::make([
                Actions\Action::make('duplicate')
                    ->label('کپی گرفتن از این محصول')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('warning')
                    ->url(fn (Product $record): string => ProductResource::getUrl('create', ['source_id' => $record->id])),
            ])
            ->label('عملیات ویژه')
            ->icon('heroicon-m-sparkles')
            ->button()
            ->color('gray'),

            // دکمه‌های استاندارد حذف و بازیابی نرم (Soft Delete)
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterSave(): void
    {
        // خواندن وضعیت واقعی فرم
        $state = $this->form->getRawState();

        if (!empty($state['share_to_channel']) && $state['share_to_channel'] == true) {
            $this->shareToTelegramChannel($this->record);
        }
    }

    protected function shareToTelegramChannel($product)
    {
        $channelId = $product->bot->settings()->where('key', 'channel_id')->value('value');
        if ($channelId) {
            \App\Services\TelegramProductService::editInChannel($product, $channelId);
        }
    }

    // 🚀 پاپ‌آپ خطاهای ویرایش
    protected function onValidationError(ValidationException $exception): void
    {
        $errors = collect($exception->errors())->flatten()->unique()->implode('<br> ❌ ');

        Notification::make()
            ->title('امکان ذخیره تغییرات وجود ندارد!')
            ->body(new HtmlString('دقت کنید:<br><br> ❌ ' . $errors))
            ->danger()
            ->duration(8000)
            ->send();
    }
}
