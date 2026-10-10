<?php

namespace App\Console\Commands;

use App\Support\ImportacaoCatequese\Leitor;
use Illuminate\Console\Command;

/**
 * Fase 1 da carga real da Catequese 2026/2027: le o DOCX + XLSX originais e
 * escreve o ficheiro de staging (staging.json) e o relatorio problemas.csv.
 * Nao toca na base de dados. Ver docs/modulos/catequese-importacao-2026-2027.md.
 */
class PrepararImportacaoCatequese extends Command
{
    protected $signature = 'catequese:preparar-importacao
        {docx : Caminho da Lista Nominal (.docx)}
        {xlsx : Caminho da Base de Dados (.xlsx)}';

    protected $description = 'Le os ficheiros originais da Catequese 2026/2027 e gera staging.json + problemas.csv';

    public static function diretorio(): string
    {
        return storage_path('app/private/importacao-catequese');
    }

    public function handle(): int
    {
        $dados = (new Leitor)->ler($this->argument('docx'), $this->argument('xlsx'));

        $dir = self::diretorio();
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $problemas = $dados['problemas'];
        unset($dados['problemas']);
        file_put_contents($dir.'/staging.json', json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $csv = fopen($dir.'/problemas.csv', 'w');
        fwrite($csv, "\xEF\xBB\xBF");
        fputcsv($csv, ['tipo', 'detalhe'], ';');
        foreach ($problemas as $p) {
            fputcsv($csv, [$p['tipo'], $p['detalhe']], ';');
        }
        fclose($csv);

        $this->info('Staging gerado em '.$dir);
        $this->table(['Entidade', 'Total'], [
            ['Turmas', count($dados['turmas'])],
            ['Catequistas (únicos)', count($dados['catequistas'])],
            ['Catequizandos', count($dados['catequizandos'])],
            ['  com turma', count(array_filter($dados['catequizandos'], fn ($c) => $c['turma_indice'] !== null))],
            ['  sem turma', count(array_filter($dados['catequizandos'], fn ($c) => $c['turma_indice'] === null))],
            ['  sem data de nascimento', count(array_filter($dados['catequizandos'], fn ($c) => $c['ano_nascimento'] === null))],
        ]);

        $this->line('Problemas por tipo:');
        $porTipo = [];
        foreach ($problemas as $p) {
            $porTipo[$p['tipo']] = ($porTipo[$p['tipo']] ?? 0) + 1;
        }
        ksort($porTipo);
        $this->table(['Tipo', 'N'], array_map(fn ($k, $v) => [$k, $v], array_keys($porTipo), $porTipo));
        $this->line('Detalhe: '.$dir.'/problemas.csv');

        return self::SUCCESS;
    }
}
