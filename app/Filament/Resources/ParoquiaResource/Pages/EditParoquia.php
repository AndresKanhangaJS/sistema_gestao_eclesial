<?php

namespace App\Filament\Resources\ParoquiaResource\Pages;

use App\Filament\Resources\ParoquiaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditParoquia extends EditRecord
{
    protected static string $resource = ParoquiaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Paróquia actualizada';
    }
}
