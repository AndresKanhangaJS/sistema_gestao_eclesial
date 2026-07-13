<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Export Excel generico reutilizado pelos relatorios cujos dados sao
 * agregados/computados (nao uma simples query Eloquent tabular).
 */
class ArrayExport implements FromArray, WithHeadings
{
    /**
     * Caracteres que o Excel/Sheets interpreta como inicio de formula quando
     * uma celula comeca por eles (CSV/Excel Formula Injection — OWASP).
     * Os dados incluem strings vindas do utilizador (nome de fiel, motivo,
     * etc.), por isso sao neutralizados antes de ir para o ficheiro.
     */
    private const PREFIXOS_PERIGOSOS = ['=', '+', '-', '@', "\t", "\r"];

    public function __construct(
        private readonly array $linhas,
        private readonly array $cabecalhos,
    ) {}

    public function array(): array
    {
        return array_map(
            fn (array $linha) => array_map([self::class, 'sanitizarCelula'], $linha),
            $this->linhas
        );
    }

    public function headings(): array
    {
        return $this->cabecalhos;
    }

    private static function sanitizarCelula(mixed $valor): mixed
    {
        if (! is_string($valor) || $valor === '') {
            return $valor;
        }

        return in_array($valor[0], self::PREFIXOS_PERIGOSOS, true)
            ? "'".$valor
            : $valor;
    }
}
