<?php

namespace App\Filament\Resources\CatequizandoResource\Pages;

use App\Filament\Imports\CatequizandoImporter;
use App\Filament\Resources\CatequizandoResource;
use App\Models\Catequizando;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListCatequizandos extends ListRecords
{
    protected static string $resource = CatequizandoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Mesma policy do CreateAction — importar em massa e so outra
            // forma de criar catequizandos, nao um privilegio a parte
            // (mesmo padrao de FielResource\Pages\ListFiels).
            Actions\ImportAction::make()
                ->label('Importar Catequizandos')
                ->importer(CatequizandoImporter::class)
                ->visible(fn () => Auth::user()?->can('create', Catequizando::class) ?? false),
            Actions\CreateAction::make(),
        ];
    }
}
