<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;

/**
 * So utilizadores activos podem entrar no sistema. Adicionar 'status' as
 * credenciais faz o Auth::attempt() falhar logo na autenticacao para contas
 * inactivas — mensagem generica de "credenciais invalidas" (ja traduzida
 * pelo pacote pt_PT do Filament), sem revelar se a conta existe ou esta so
 * desactivada. Reforcado tambem em User::canAccessPanel() para sessoes que
 * ja estejam autenticadas quando a conta e desactivada.
 */
class Login extends BaseLogin
{
    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            ...parent::getCredentialsFromFormData($data),
            'status' => 'ativo',
        ];
    }
}
