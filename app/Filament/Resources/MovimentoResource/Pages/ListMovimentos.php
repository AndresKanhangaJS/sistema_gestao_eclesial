<?php

namespace App\Filament\Resources\MovimentoResource\Pages;

use App\Filament\Resources\MovimentoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMovimentos extends ListRecords
{
    protected static string $resource = MovimentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
