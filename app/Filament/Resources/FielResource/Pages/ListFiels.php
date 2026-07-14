<?php

namespace App\Filament\Resources\FielResource\Pages;

use App\Filament\Imports\FielImporter;
use App\Filament\Resources\FielResource;
use App\Models\Fiel;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListFiels extends ListRecords
{
    protected static string $resource = FielResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Mesma policy do CreateAction (administrador_paroquial e
            // tesoureiro_paroquial) — importar em massa e so outra forma de
            // criar fieis, nao um privilegio a parte.
            Actions\ImportAction::make()
                ->label('Importar Fiéis')
                ->importer(FielImporter::class)
                ->visible(fn () => Auth::user()?->can('create', Fiel::class) ?? false),
            Actions\CreateAction::make(),
        ];
    }
}
