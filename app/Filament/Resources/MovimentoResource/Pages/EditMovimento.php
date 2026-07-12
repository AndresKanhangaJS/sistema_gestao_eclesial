<?php

namespace App\Filament\Resources\MovimentoResource\Pages;

use App\Filament\Resources\MovimentoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMovimento extends EditRecord
{
    protected static string $resource = MovimentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
