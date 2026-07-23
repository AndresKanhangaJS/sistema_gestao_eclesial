<?php

namespace App\Filament\Resources\AnoCatequeticoResource\Pages;

use App\Filament\Resources\AnoCatequeticoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAnoCatequetico extends EditRecord
{
    protected static string $resource = AnoCatequeticoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
