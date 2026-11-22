<?php

namespace App\Filament\Resources\BroadcastResource\Pages;

use App\Filament\Resources\BroadcastResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBroadcast extends EditRecord
{
    protected static string $resource = BroadcastResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('حذف پیام')
                // جلوگیری از حذف پیامی که در حال ارسال است
                ->hidden(fn ($record) => $record->status === 'processing'),
        ];
    }

    // جلوگیری از ویرایش پیام‌هایی که ارسال شده‌اند یا در حال پردازش هستند
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (in_array($this->record->status, ['processing', 'completed'])) {
            \Filament\Notifications\Notification::make()
                ->warning()
                ->title('امکان ویرایش وجود ندارد')
                ->body('این پیام پردازش شده است و دیگر قابل ویرایش نیست.')
                ->send();

            $this->halt(); // توقف فرآیند ذخیره‌سازی
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
