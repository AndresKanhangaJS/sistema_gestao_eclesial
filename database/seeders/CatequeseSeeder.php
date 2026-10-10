<?php

namespace Database\Seeders;

use App\Models\AnoCatequetico;
use App\Models\Catecismo;
use App\Models\Sacramento;
use Illuminate\Database\Seeder;

/**
 * Dados de referencia globais (sem paroquia_id) do modulo Catequese —
 * geridos por admin_geral, ver docs/modulos/catequese.md seccao 3. Sem isto
 * nao e possivel criar nenhuma Turma (ano_catequetico_id/sacramento sao
 * obrigatorios).
 *
 * Idempotente: updateOrCreate pela chave natural `ordem` (0 e valido),
 * por isso pode correr-se varias vezes sem duplicar nem apagar nada. Os
 * anos catequeticos genericos antigos (5º e 6º Ano) passam a ser actualizados
 * para "1º Crisma"/"2º Crisma" na mesma ordem.
 */
class CatequeseSeeder extends Seeder
{
    public function run(): void
    {
        $anos = [
            0 => 'Iniciação',
            1 => '1º Ano',
            2 => '2º Ano',
            3 => '3º Ano',
            4 => '4º Ano',
            5 => '1º Crisma',
            6 => '2º Crisma',
            7 => 'Perseverança',
        ];

        foreach ($anos as $ordem => $nome) {
            AnoCatequetico::updateOrCreate(['ordem' => $ordem], ['nome' => $nome, 'status' => 'ativo']);
        }

        $sacramentos = [
            1 => 'Baptismo',
            2 => 'Comunhão',
            3 => 'Crisma',
            4 => 'Perseverança',
        ];

        foreach ($sacramentos as $ordem => $nome) {
            Sacramento::updateOrCreate(['ordem' => $ordem], ['nome' => $nome, 'status' => 'ativo']);
        }

        // Catecismos oficiais usados pelas turmas — nao se deduzem do ano
        // catequetico (o mesmo ano pode ter turmas com catecismos diferentes).
        $catecismos = [
            1 => 'Deus Chama-nos',
            2 => 'Deus Ama-nos',
            3 => 'Creio em Jesus Salvador',
            4 => 'Jesus é a Vida/Eleitos',
            5 => 'A Minha Primeira Comunhão',
            6 => 'Discípulo de Cristo',
            7 => 'Apóstolo de Cristo',
            8 => 'Jesus 1/2',
            9 => 'Minha História Divina',
            10 => 'Catecismo de Adulto',
        ];

        foreach ($catecismos as $ordem => $nome) {
            Catecismo::updateOrCreate(['ordem' => $ordem], ['nome' => $nome, 'status' => 'ativo']);
        }
    }
}
