<?php

namespace App\Filament\Resources\BroadcastResource\Pages;

use App\Filament\Resources\BroadcastResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateBroadcast extends CreateRecord
{
    protected static string $resource = BroadcastResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // اختصاص خودکار آیدی ربات/فروشگاه فعلی به این پیام همگانی
        // (اگر از سیستم چندمستاجره رسمی فیلامنت استفاده می‌کنید این بخش خودکار انجام می‌شود،
        // اما در حالت مدیریت با سشن، از کد زیر استفاده کنید)
        $botId = session('bot_id') ?? auth()->user()->bot_id;

        $data['bot_id'] = $botId;
        $data['status'] = 'pending';
        $data['sent_count'] = 0;
        $data['total_users'] = 0;

        return $data;
    }

    // هدایت کاربر به صفحه لیست پیام‌ها پس از ذخیره موفقیت‌آمیز
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
