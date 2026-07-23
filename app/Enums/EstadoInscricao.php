<?php

namespace App\Enums;

enum EstadoInscricao: string
{
    case Inscrito = 'inscrito';
    case Aprovado = 'aprovado';
    case Reprovado = 'reprovado';
    case Desistente = 'desistente';
    case Cancelado = 'cancelado';
}
