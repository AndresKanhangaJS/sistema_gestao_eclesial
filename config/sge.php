<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Valor de aprovação de despesas
    |--------------------------------------------------------------------------
    |
    | Despesas de centro (tipo despesa_centro) com valor ate este limite ficam
    | automaticamente aprovadas na criacao. Acima do limite, ficam pendentes
    | e exigem aprovacao manual do tesoureiro_paroquial (spec 03-financeiro.md).
    |
    */
    'valor_aprovacao_despesa' => env('SGE_VALOR_APROVACAO_DESPESA', 50000),

];
