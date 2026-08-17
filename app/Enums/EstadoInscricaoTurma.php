<?php

namespace App\Enums;

enum EstadoInscricaoTurma: string
{
    case Ativo = 'ativo';
    case Transferido = 'transferido';
    case Removido = 'removido';

    public function label(): string
    {
        return match ($this) {
            self::Ativo => 'Activo',
            self::Transferido => 'Transferido',
            self::Removido => 'Removido',
        };
    }
}
