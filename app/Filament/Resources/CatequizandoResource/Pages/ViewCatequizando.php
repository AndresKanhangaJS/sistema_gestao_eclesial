<?php

namespace App\Filament\Resources\CatequizandoResource\Pages;

use App\Filament\Resources\CatequizandoResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

/**
 * "Ver ficha" — sem infolist proprio de proposito: sem componentes
 * registados, o ViewRecord do Filament reaproveita CatequizandoResource::form()
 * em modo so-leitura (mesmos separadores Dados Pessoais/Naturalidade/
 * Contacto/Dados Religiosos; o separador "Inscrição" fica escondido, so
 * visivel no 'create'), com os RelationManagers (Centros/Inscrições) por
 * baixo — igual ao Edit, sem risco de alterar dados por engano.
 */
class ViewCatequizando extends ViewRecord
{
    protected static string $resource = CatequizandoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
