<?php

namespace App\Filament\Resources\CatequistaResource\Pages;

use App\Filament\Resources\CatequistaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditCatequista extends EditRecord
{
    protected static string $resource = CatequistaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = Auth::user();

        if (! $user?->hasRole('admin_geral')) {
            $data['paroquia_id'] = $this->record->paroquia_id;
        }

        if (! $user?->hasRole(['admin_geral', 'coordenador_catequese_paroquia'])) {
            $data['centro_id'] = $this->record->centro_id;
        }

        return $data;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Catequista actualizado';
    }
}
