<?php

namespace App\Filament\Resources\AnoLetivoResource\Pages;

use App\Filament\Resources\AnoLetivoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAnoLetivos extends ListRecords
{
    protected static string $resource = AnoLetivoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
