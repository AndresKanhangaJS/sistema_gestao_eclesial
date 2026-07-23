<?php

namespace App\Filament\Resources\CatequistaResource\Pages;

use App\Filament\Resources\CatequistaResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateCatequista extends CreateRecord
{
    protected static string $resource = CatequistaResource::class;

    /**
     * Sem Observer proprio ainda (so Centro/Fiel/CategoriaDespesa/Banco tem
     * ForcaParoquiaUtilizadorObserver) — o Resource reforca aqui, do lado do
     * servidor, o que o form ja esconde na UI.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();

        if (! $user?->hasRole('admin_geral')) {
            $data['paroquia_id'] = $user?->paroquia_id;
        }

        if (! $user?->hasRole(['admin_geral', 'coordenador_catequese_paroquia'])) {
            $data['centro_id'] = $user?->centro_id;
        }

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Catequista registado';
    }
}
