<?php

namespace App\Filament\Resources\AnoCatequeticoResource\Pages;

use App\Filament\Resources\AnoCatequeticoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAnoCatequeticos extends ListRecords
{
    protected static string $resource = AnoCatequeticoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
