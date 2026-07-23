<?php

namespace App\Filament\Resources\CatequizandoResource\Pages;

use App\Filament\Resources\CatequizandoResource;
use App\Models\Centro;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateCatequizando extends CreateRecord
{
    protected static string $resource = CatequizandoResource::class;

    /**
     * Ainda nao existe Observer para Catequizando (so Centro/Fiel/
     * CategoriaDespesa/Banco tem ForcaParoquiaUtilizadorObserver — ver
     * docs/modulos/catequese.md seccao 2, "pendente"), por isso o Resource e
     * a unica linha de defesa: o campo centro_id so aparece no form para
     * admin_geral/coordenador_catequese_paroquia, mas ->visible(false) nao
     * impede adulteracao do estado Livewire no cliente, entao repetimos aqui
     * a mesma logica do lado do servidor. paroquia_id nunca e um campo do
     * form — deriva-se sempre do centro escolhido.
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

    /**
     * catequizando_centros guarda o historico completo de centros (molde
     * fiel_centros); sem Observer proprio ainda, a primeira linha do
     * historico e criada aqui, ao mesmo tempo que o proprio catequizando.
     */
    protected function afterCreate(): void
    {
        $this->record->centros()->attach($this->record->centro_id, [
            'data_inicio' => now(),
        ]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Catequizando registado';
    }
}
