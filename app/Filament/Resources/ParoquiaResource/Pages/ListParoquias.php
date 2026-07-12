<?php

namespace App\Filament\Resources\ParoquiaResource\Pages;

use App\Filament\Resources\ParoquiaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListParoquias extends ListRecords
{
    protected static string $resource = ParoquiaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
