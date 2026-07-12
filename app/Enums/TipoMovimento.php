<?php

namespace App\Enums;

enum TipoMovimento: string
{
    case Dizimo = 'dizimo';
    case Ofertorio = 'ofertorio';
    case Campanha = 'campanha';
    case DespesaCentro = 'despesa_centro';
}
