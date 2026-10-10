<?php

namespace Database\Seeders;

use App\Models\Centro;
use App\Models\Paroquia;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Paroquia real (Sao Carlos Lwanga) e os seus dois centros, para a carga
 * inicial da Catequese 2026/2027 (docs/modulos/catequese-importacao-2026-2027.md).
 *
 * NAO esta registado no DatabaseSeeder (que carrega dados de demonstracao):
 * corre-se a parte com `php artisan db:seed --class=ParoquiaRealSeeder`.
 * Idempotente (firstOrCreate pela chave natural), nao altera registos que
 * ja existam. Centro usa withoutGlobalScopes() porque o seeder corre sem utilizador autenticado.
 *
 * ATENCAO: responsavel, email_contato, telefone e responsavel_local sao
 * valores GENERICOS (marcadores) — a paroquia exige estes campos mas os
 * ficheiros de origem nao os trazem. Editar em producao (Filament) com os
 * dados reais antes de os usar em comunicacoes.
 */
class ParoquiaRealSeeder extends Seeder
{
    public function run(): void
    {
        $paroquia = Paroquia::firstOrCreate(
            ['nome' => 'São Carlos Lwanga'],
            [
                'diocese' => 'Arquidiocese de Luanda',
                'morada' => 'Urbanização Nova Vida, Rua 26',
                'responsavel' => 'A definir',
                'email_contato' => 'a.definir@saocarloslwanga.local',
                'telefone' => '900000000',
                'status' => 'ativo',
            ]
        );

        // Utilizadores de DESENVOLVIMENTO desta paroquia (a TestDataSeeder so
        // cria utilizadores na "Paroquia de Teste", por isso nao veem estes
        // dados). Mesma convencao de senha dos restantes utilizadores de
        // teste; em producao criar contas reais pelo UserResource.
        if (app()->environment('local', 'testing')) {
            foreach (['administrador_paroquial', 'coordenador_catequese_paroquia'] as $papel) {
                $user = User::firstOrCreate(
                    ['email' => $papel.'.slwanga@sge.local'],
                    [
                        'name' => ucfirst(str_replace('_', ' ', $papel)).' (São Carlos Lwanga)',
                        'password' => bcrypt('password'),
                        'paroquia_id' => $paroquia->id,
                        'centro_id' => null,
                    ]
                );
                if (! $user->hasRole($papel)) {
                    $user->assignRole($papel);
                }
            }
        }

        foreach (['Sede', 'São Miguel Arcanjo'] as $nome) {
            Centro::withoutGlobalScopes()->firstOrCreate(
                ['paroquia_id' => $paroquia->id, 'nome' => $nome],
                [
                    'localizacao' => 'Urbanização Nova Vida, Rua 26',
                    'responsavel_local' => 'A definir',
                    'status' => 'ativo',
                ]
            );
        }
    }
}
