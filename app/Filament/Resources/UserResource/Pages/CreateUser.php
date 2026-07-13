<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    private ?string $role = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->role = UserResource::papelPermitido($data['role']);
        unset($data['role']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncRoles([$this->role]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Utilizador registado';
    }
}
