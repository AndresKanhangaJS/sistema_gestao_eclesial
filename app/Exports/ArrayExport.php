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
    public function __construct(
        private readonly array $linhas,
        private readonly array $cabecalhos,
    ) {
    }

    public function array(): array
    {
        return $this->linhas;
    }

    public function headings(): array
    {
        return $this->cabecalhos;
    }
}
