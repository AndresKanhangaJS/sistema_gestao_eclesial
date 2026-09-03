<?php

namespace App\Filament\Resources\CatequizandoResource\Pages;

use App\Filament\Resources\CatequizandoResource;
use App\Models\Centro;
use App\Models\Inscricao;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateCatequizando extends CreateRecord
{
    protected static string $resource = CatequizandoResource::class;

    /**
     * Dados do separador "Inscrição" (statePath 'inscricao'), extraidos em
     * mutateFormDataBeforeCreate() e usados em afterCreate() para gerar a
     * Inscricao logo a seguir ao Catequizando, na mesma transaccao
     * (hasDatabaseTransactions() abaixo) — ver docs/modulos/catequese.md
     * seccao 27.
     */
    private ?array $dadosInscricao = null;

    public function getTitle(): string
    {
        return 'Inscrever Catequizando';
    }

    /**
     * Sem isto, um erro ao criar a Inscricao em afterCreate() deixaria o
     * Catequizando (e o vinculo em catequizando_centros) gravados sozinhos —
     * exactamente as "duas voltas" que este formulario existe para eliminar.
     */
    public function hasDatabaseTransactions(): bool
    {
        return true;
    }

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

        $this->dadosInscricao = $data['inscricao'] ?? null;
        unset($data['inscricao']);

        return $data;
    }

    /**
     * catequizando_centros guarda o historico completo de centros (molde
     * fiel_centros); sem Observer proprio ainda, a primeira linha do
     * historico e criada aqui, ao mesmo tempo que o proprio catequizando.
     * A Inscricao (tipo=nova) nasce logo a seguir, com os mesmos
     * centro_id/paroquia_id ja resolvidos acima — nunca escolhidos de novo,
     * mesma regra do InscricaoResource (centro_id forcado, nunca livre).
     */
    protected function afterCreate(): void
    {
        $this->record->centros()->attach($this->record->centro_id, [
            'data_inicio' => now(),
        ]);

        if (blank($this->dadosInscricao)) {
            return;
        }

        $sacramentos = $this->dadosInscricao['sacramentos'] ?? [];
        unset($this->dadosInscricao['sacramentos']);

        $inscricao = Inscricao::create([
            ...$this->dadosInscricao,
            'paroquia_id' => $this->record->paroquia_id,
            'centro_id' => $this->record->centro_id,
            'catequizando_id' => $this->record->id,
            'tipo' => 'nova',
        ]);

        $inscricao->sacramentos()->sync($sacramentos);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Catequizando inscrito';
    }
}
