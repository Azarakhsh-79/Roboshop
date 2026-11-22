<?php

namespace App\Filament\Superadmin\Resources\ManagedBotResource\Pages;

use App\Filament\Superadmin\Resources\ManagedBotResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditManagedBot extends EditRecord
{
    protected static string $resource = ManagedBotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
