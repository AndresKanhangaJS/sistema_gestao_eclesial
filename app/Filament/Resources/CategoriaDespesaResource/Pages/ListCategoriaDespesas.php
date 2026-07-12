<?php

namespace App\Filament\Resources\CategoriaDespesaResource\Pages;

use App\Filament\Resources\CategoriaDespesaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCategoriaDespesas extends ListRecords
{
    protected static string $resource = CategoriaDespesaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
