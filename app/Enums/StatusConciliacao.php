<?php

namespace App\Enums;

enum StatusConciliacao: string
{
    case Pendente = 'pendente';
    case Aprovado = 'aprovado';
    case Rejeitado = 'rejeitado';
}
