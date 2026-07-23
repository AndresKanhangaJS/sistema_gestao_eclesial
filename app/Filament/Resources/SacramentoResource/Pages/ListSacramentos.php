<?php

namespace App\Filament\Resources\SacramentoResource\Pages;

use App\Filament\Resources\SacramentoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSacramentos extends ListRecords
{
    protected static string $resource = SacramentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
