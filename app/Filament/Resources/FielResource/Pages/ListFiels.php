<?php

namespace App\Filament\Resources\FielResource\Pages;

use App\Filament\Resources\FielResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFiels extends ListRecords
{
    protected static string $resource = FielResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
