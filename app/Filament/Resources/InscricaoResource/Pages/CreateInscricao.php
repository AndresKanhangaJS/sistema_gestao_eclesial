<?php

namespace App\Filament\Resources\InscricaoResource\Pages;

use App\Filament\Resources\InscricaoResource;
use App\Models\Centro;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateInscricao extends CreateRecord
{
    protected static string $resource = InscricaoResource::class;

    /**
     * centro_id vem do centro do utilizador autenticado para quem tem um
     * centro proprio; admin_geral/coordenador_catequese_paroquia (sem
     * centro_id fixo) escolhem no formulario — ver InscricaoResource::
     * GESTORES_CENTRO_LIVRE. Bug corrigido: antes disto, um
     * coordenador_catequese_paroquia (centro_id nulo) fazia a gravacao
     * falhar com "centro_id cannot be null" (docs/modulos/catequese.md
     * seccao 10). paroquia_id acompanha sempre o centro. Ainda nao existe
     * Observer para isto, por isso o Resource e a unica linha de defesa.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();

        if (! $user?->hasRole(['admin_geral', 'coordenador_catequese_paroquia'])) {
            $data['centro_id'] = $user?->centro_id;
        }

        $centro = Centro::withoutGlobalScopes()->find($data['centro_id']);
        $data['paroquia_id'] = $centro?->paroquia_id ?? $user?->paroquia_id;

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Inscrição registada';
    }
}
