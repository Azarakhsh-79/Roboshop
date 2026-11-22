<?php

namespace App\Filament\Resources\DiscountCodeResource\Pages;

use App\Filament\Resources\DiscountCodeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDiscountCode extends CreateRecord
{
    protected static string $resource = DiscountCodeResource::class;

    // 🚀 با این متد، بعد از ایجاد موفق، به صفحه لیست برمی‌گردد
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
