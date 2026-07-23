<?php

namespace App\Filament\Resources\CatequizandoResource\Pages;

use App\Filament\Resources\CatequizandoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCatequizandos extends ListRecords
{
    protected static string $resource = CatequizandoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
