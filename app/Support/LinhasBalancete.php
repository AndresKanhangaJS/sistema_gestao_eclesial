<?php

namespace App\Support;

/**
 * Catalogo de linhas fixas do relatorio "Balancete" (modelo oficial da
 * Arquidiocese, pedido do cliente 2026-09-29) usadas para taguear
 * CategoriaReceita/CategoriaDespesa (coluna linha_balancete). Uma categoria
 * tagueada cai sempre na linha correspondente do Balancete; sem tag, cai no
 * residuo (1.1.5 Receitas Diversas / 2.2.8 Outras Despesas).
 *
 * BISPADO e CONTRIBUTO_DIZIMO ficam fora dos catalogos RECEITA/DESPESA
 * (nao aparecem no Select de CategoriaDespesaResource): sao formula
 * (percentagem sobre o total de Receitas/Congruas do mes), nao um
 * lancamento manual — App\Services\BalanceteService::fechar() cria a
 * CategoriaDespesa reservada automaticamente (firstOrCreate) na primeira
 * vez que fecha um Balancete para essa paroquia.
 */
class LinhasBalancete
{
    public const RECEITA = [
        '1.1.1' => 'Subsídios',
        '1.1.2' => 'Donativos',
        '1.1.4' => 'Entradas do Cartório',
        '1.3' => 'Intenções de Missa',
    ];

    public const DESPESA = [
        '2.1.1' => 'Subsídio aos Padres',
        '2.1.2' => 'Estipêndios',
        '2.1.3' => 'Subsídio Casa Paroquial',
        '2.2.1' => 'Salário Funcionários + INSS',
        '2.2.2' => 'Despesas de Culto',
        '2.2.3' => 'Transporte - Viagens',
        '2.2.4' => 'Energia',
        '2.2.5' => 'Água',
        '2.2.6' => 'Internet - Comunicações',
        '2.2.7' => 'Donativos e esmolas',
        '2.3.3' => 'Missas Binadas',
        '2.3.4' => 'Colectas Obrigatórias',
    ];

    public const BISPADO = '2.3.1';

    public const CONTRIBUTO_DIZIMO = '2.3.2';
}
