<?php

namespace App\Filament\Resources\CategoriaReceitaResource\Pages;

use App\Filament\Resources\CategoriaReceitaResource;
use App\Filament\Resources\CategoriaReceitaResource\Widgets\CategoriasFixasWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCategoriaReceitas extends ListRecords
{
    protected static string $resource = CategoriaReceitaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CategoriasFixasWidget::class,
        ];
    }
}
