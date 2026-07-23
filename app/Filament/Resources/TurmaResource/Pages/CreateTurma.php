<?php

namespace App\Filament\Resources\TurmaResource\Pages;

use App\Filament\Resources\TurmaResource;
use App\Models\Centro;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateTurma extends CreateRecord
{
    protected static string $resource = TurmaResource::class;

    /**
     * Sem Observer proprio ainda — reforco do lado do servidor do que o form
     * ja esconde na UI (mesmo raciocinio do ForcaParoquiaUtilizadorObserver).
     * paroquia_id nunca e um campo do form — deriva-se sempre do centro.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();

        if (! $user || ! $user->hasRole(['admin_geral', 'coordenador_catequese_paroquia'])) {
            $data['centro_id'] = $user?->centro_id;
        }

        $centro = Centro::withoutGlobalScopes()->find($data['centro_id']);
        $data['paroquia_id'] = $centro?->paroquia_id;

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Turma criada';
    }
}
