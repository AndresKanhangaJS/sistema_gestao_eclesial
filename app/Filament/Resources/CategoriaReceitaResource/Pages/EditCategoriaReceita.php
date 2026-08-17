<?php

namespace App\Filament\Resources\CategoriaReceitaResource\Pages;

use App\Filament\Resources\CategoriaReceitaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCategoriaReceita extends EditRecord
{
    protected static string $resource = CategoriaReceitaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Categoria de Receita actualizada';
    }
}
