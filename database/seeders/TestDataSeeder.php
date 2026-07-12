<?php

namespace Database\Seeders;

use App\Models\Paroquia;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dados de teste para validar o fluxo de autenticacao/RBAC multi-tenant.
 * Um utilizador por perfil, ligado (ou nao) a uma paroquia de teste.
 */
class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $paroquia = Paroquia::firstOrCreate(
            ['nome' => 'Paroquia de Teste'],
            [
                'diocese' => 'Diocese de Teste',
                'morada' => 'Rua de Teste, 1',
                'responsavel' => 'Pe. Teste',
                'email_contato' => 'paroquia@sge.local',
                'telefone' => '900000000',
            ]
        );

        $utilizadores = [
            ['role' => 'admin_geral', 'paroquia_id' => null],
            ['role' => 'tesoureiro_paroquial', 'paroquia_id' => $paroquia->id],
            ['role' => 'tesoureiro_centro', 'paroquia_id' => $paroquia->id],
            ['role' => 'consultor', 'paroquia_id' => null],
        ];

        foreach ($utilizadores as $dados) {
            $user = User::firstOrCreate(
                ['email' => $dados['role'] . '@sge.local'],
                [
                    'name' => ucfirst(str_replace('_', ' ', $dados['role'])),
                    'password' => bcrypt('password'),
                    'paroquia_id' => $dados['paroquia_id'],
                ]
            );

            if (! $user->hasRole($dados['role'])) {
                $user->assignRole($dados['role']);
            }
        }
    }
}
