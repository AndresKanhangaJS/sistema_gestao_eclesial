<?php

namespace App\Filament\Resources\AnoLetivoResource\Pages;

use App\Filament\Resources\AnoLetivoResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateAnoLetivo extends CreateRecord
{
    protected static string $resource = AnoLetivoResource::class;

    /**
     * Sem Observer proprio ainda (so Centro/Fiel/CategoriaDespesa/Banco/User
     * tem ForcaParoquiaUtilizadorObserver) — o campo paroquia_id esta oculto
     * no form para quem nao e admin_geral, mas isso nao impede adulteracao
     * do estado Livewire no cliente. Reforcamos aqui, mesmo padrao dos
     * restantes Resources do modulo (CreateCatequista, CreateTurma, etc.).
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! Auth::user()?->hasRole('admin_geral')) {
            $data['paroquia_id'] = Auth::user()?->paroquia_id;
        }

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Ano lectivo criado';
    }
}
