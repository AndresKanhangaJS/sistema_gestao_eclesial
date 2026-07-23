<?php

namespace App\Filament\Resources\SacramentoResource\Pages;

use App\Filament\Resources\SacramentoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSacramento extends EditRecord
{
    protected static string $resource = SacramentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
