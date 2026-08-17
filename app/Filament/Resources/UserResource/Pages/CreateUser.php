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

    /**
     * A senha de uma conta nova e sempre escolhida por quem regista, nunca
     * pelo proprio utilizador — obriga-o a trocar por uma pessoal no
     * primeiro login (ForcarAlteracaoSenha).
     */
    protected function afterCreate(): void
    {
        $this->record->syncRoles([$this->role]);

        $this->record->forceFill(['deve_alterar_senha' => true])->save();
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Utilizador registado';
    }
}
