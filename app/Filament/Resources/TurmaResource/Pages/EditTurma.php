<?php

namespace App\Filament\Resources\TurmaResource\Pages;

use App\Filament\Resources\TurmaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTurma extends EditRecord
{
    protected static string $resource = TurmaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * O centro de uma turma e fixo (docs/modulos/catequese.md seccao 7.1) —
     * o campo nem aparece no form em modo de edicao, mas reforcamos aqui
     * contra adulteracao do estado Livewire no cliente.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['centro_id'] = $this->record->centro_id;
        $data['paroquia_id'] = $this->record->paroquia_id;

        return $data;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Turma actualizada';
    }
}
