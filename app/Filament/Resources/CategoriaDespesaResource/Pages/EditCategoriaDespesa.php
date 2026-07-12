<?php

namespace App\Filament\Resources\CategoriaDespesaResource\Pages;

use App\Filament\Resources\CategoriaDespesaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCategoriaDespesa extends EditRecord
{
    protected static string $resource = CategoriaDespesaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Categoria de Despesa actualizada';
    }
}
