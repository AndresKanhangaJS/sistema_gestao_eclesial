<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    private ?string $role = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role'] = $this->record->roles->first()?->name;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->role = UserResource::papelPermitido($data['role']);
        unset($data['role']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->syncRoles([$this->role]);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Utilizador actualizado';
    }
}
