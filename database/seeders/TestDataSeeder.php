<?php

namespace Database\Seeders;

use App\Models\Centro;
use App\Models\Paroquia;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dados de teste para validar o fluxo de autenticacao/RBAC multi-tenant.
 * Um utilizador por perfil, ligado (ou nao) a uma paroquia/centro de teste.
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

        $centro = Centro::withoutGlobalScopes()->firstOrCreate(
            ['paroquia_id' => $paroquia->id, 'nome' => 'Centro de Teste'],
        );

        $utilizadores = [
            ['role' => 'admin_geral', 'paroquia_id' => null, 'centro_id' => null],
            ['role' => 'administrador_paroquial', 'paroquia_id' => $paroquia->id, 'centro_id' => null],
            ['role' => 'tesoureiro_paroquial', 'paroquia_id' => $paroquia->id, 'centro_id' => null],
            ['role' => 'tesoureiro_centro', 'paroquia_id' => $paroquia->id, 'centro_id' => $centro->id],
            ['role' => 'consultor', 'paroquia_id' => null, 'centro_id' => null],
            // Modulo Catequese (docs/modulos/catequese.md)
            ['role' => 'coordenador_catequese_paroquia', 'paroquia_id' => $paroquia->id, 'centro_id' => null],
            ['role' => 'coordenador_catequese_centro', 'paroquia_id' => $paroquia->id, 'centro_id' => $centro->id],
            ['role' => 'secretario_catequese', 'paroquia_id' => $paroquia->id, 'centro_id' => $centro->id],
            ['role' => 'tesoureiro_catequese', 'paroquia_id' => $paroquia->id, 'centro_id' => $centro->id],
        ];

        foreach ($utilizadores as $dados) {
            $user = User::firstOrCreate(
                ['email' => $dados['role'].'@sge.local'],
                [
                    'name' => ucfirst(str_replace('_', ' ', $dados['role'])),
                    'password' => bcrypt('password'),
                    'paroquia_id' => $dados['paroquia_id'],
                    'centro_id' => $dados['centro_id'],
                ]
            );

            if (! $user->hasRole($dados['role'])) {
                $user->assignRole($dados['role']);
            }
        }
    }
}
