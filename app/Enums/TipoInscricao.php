<?php

namespace App\Enums;

enum TipoInscricao: string
{
    case Nova = 'nova';
    case Confirmacao = 'confirmacao';

    /**
     * Rótulo único, partilhado por todas as tabelas/badges que mostram o
     * tipo de inscrição (CatequizandoResource, InscricoesRelationManager,
     * InscricaoResource) — antes duplicado em cada sítio, cada um podia
     * divergir (ex.: "Nova" nuns lados, "Matrícula" noutros, reportado pelo
     * utilizador). "Matrícula" em vez de "Nova": termo pedido pelo
     * utilizador, mais claro para quem usa o sistema no dia-a-dia.
     */
    public function label(): string
    {
        return match ($this) {
            self::Nova => 'Matrícula',
            self::Confirmacao => 'Confirmação',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Nova => 'gray',
            self::Confirmacao => 'success',
        };
    }
}
