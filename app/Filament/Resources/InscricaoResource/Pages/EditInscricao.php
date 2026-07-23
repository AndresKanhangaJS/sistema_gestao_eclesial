<?php

namespace App\Filament\Resources\InscricaoResource\Pages;

use App\Filament\Resources\InscricaoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInscricao extends EditRecord
{
    protected static string $resource = InscricaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * centro_id/paroquia_id nunca mudam por edicao do formulario — ver
     * CreateInscricao.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['centro_id'] = $this->record->centro_id;
        $data['paroquia_id'] = $this->record->paroquia_id;

        return $data;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Inscrição actualizada';
    }
}
