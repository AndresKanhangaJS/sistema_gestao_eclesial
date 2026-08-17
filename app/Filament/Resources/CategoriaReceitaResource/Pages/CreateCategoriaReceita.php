<?php

namespace App\Filament\Resources\CategoriaReceitaResource\Pages;

use App\Filament\Resources\CategoriaReceitaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCategoriaReceita extends CreateRecord
{
    protected static string $resource = CategoriaReceitaResource::class;

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Categoria de Receita registada';
    }
}
