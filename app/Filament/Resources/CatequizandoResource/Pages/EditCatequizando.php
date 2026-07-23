<?php

namespace App\Filament\Resources\CatequizandoResource\Pages;

use App\Filament\Resources\CatequizandoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCatequizando extends EditRecord
{
    protected static string $resource = CatequizandoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * centro_id nunca muda por edicao livre do formulario — so pela accao
     * "Transferir de Centro" no CentrosRelationManager, que tem efeitos em
     * catequizando_centros e inscricao_turma (docs/modulos/catequese.md
     * seccao 7.1). O campo esta oculto no form, mas isso nao impede
     * adulteracao do estado Livewire no cliente — reforcamos aqui.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['centro_id'] = $this->record->centro_id;
        $data['paroquia_id'] = $this->record->paroquia_id;

        return $data;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Catequizando actualizado';
    }
}
