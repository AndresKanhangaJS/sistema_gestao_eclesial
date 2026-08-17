<?php

namespace App\Filament\Resources\CatequistaResource\Pages;

use App\Filament\Imports\CatequistaImporter;
use App\Filament\Resources\CatequistaResource;
use App\Models\Catequista;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListCatequistas extends ListRecords
{
    protected static string $resource = CatequistaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Mesma policy do CreateAction — importar em massa e so outra
            // forma de registar catequistas, nao um privilegio a parte
            // (mesmo padrao de FielResource\Pages\ListFiels).
            Actions\ImportAction::make()
                ->label('Importar Catequistas')
                ->importer(CatequistaImporter::class)
                ->visible(fn () => Auth::user()?->can('create', Catequista::class) ?? false),
            Actions\CreateAction::make(),
        ];
    }
}
