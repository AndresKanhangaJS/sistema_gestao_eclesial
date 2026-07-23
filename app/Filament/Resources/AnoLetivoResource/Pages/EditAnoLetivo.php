<?php

namespace App\Filament\Resources\AnoLetivoResource\Pages;

use App\Filament\Resources\AnoLetivoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAnoLetivo extends EditRecord
{
    protected static string $resource = AnoLetivoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * A paroquia de um ano lectivo e fixa apos a criacao (mesmo padrao do
     * centro_id em EditTurma) — o campo nem aparece no form para quem nao e
     * admin_geral, mas reforcamos aqui contra adulteracao do estado Livewire.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['paroquia_id'] = $this->record->paroquia_id;

        return $data;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Ano lectivo actualizado';
    }
}
