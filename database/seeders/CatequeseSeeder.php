<?php

namespace Database\Seeders;

use App\Models\AnoCatequetico;
use App\Models\Sacramento;
use Illuminate\Database\Seeder;

/**
 * Dados de referencia globais (sem paroquia_id) do modulo Catequese —
 * geridos por admin_geral, ver docs/modulos/catequese.md seccao 3. Sem isto
 * nao e possivel criar nenhuma Turma (ano_catequetico_id/sacramento sao
 * obrigatorios). Numeracao generica 1º-6º ano, alinhada com o intervalo do
 * esboco original de fichas de catequese; a correspondencia com os nomes de
 * catecismo (Deus Chamou, Deus Ama-nos, ...) fica para quando as
 * especificacoes completas de Catequista/turma fecharem.
 */
class CatequeseSeeder extends Seeder
{
    public function run(): void
    {
        $anos = [
            1 => '1º Ano',
            2 => '2º Ano',
            3 => '3º Ano',
            4 => '4º Ano',
            5 => '5º Ano',
            6 => '6º Ano',
        ];

        foreach ($anos as $ordem => $nome) {
            AnoCatequetico::firstOrCreate(['ordem' => $ordem], ['nome' => $nome, 'status' => 'ativo']);
        }

        $sacramentos = [
            1 => 'Baptismo',
            2 => 'Comunhão',
            3 => 'Crisma',
        ];

        foreach ($sacramentos as $ordem => $nome) {
            Sacramento::firstOrCreate(['ordem' => $ordem], ['nome' => $nome, 'status' => 'ativo']);
        }
    }
}
