<?php

namespace App\Filament\Resources\FielResource\Pages;

use App\Filament\Resources\FielResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFiel extends EditRecord
{
    protected static string $resource = FielResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Fiel actualizado';
    }
}
