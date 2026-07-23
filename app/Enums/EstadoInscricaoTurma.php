<?php

namespace App\Enums;

enum EstadoInscricaoTurma: string
{
    case Ativo = 'ativo';
    case Transferido = 'transferido';
    case Removido = 'removido';
}
