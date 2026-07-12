<?php

namespace App\Observers;

use App\Enums\StatusConciliacao;
use App\Enums\TipoMovimento;
use App\Models\Movimento;
use Illuminate\Support\Facades\Auth;

class MovimentoObserver
{
    public function creating(Movimento $movimento): void
    {
        if (! $movimento->usuario_id && Auth::check()) {
            $movimento->usuario_id = Auth::id();
        }
    }

    /**
     * Despesas ate o limite configuravel ficam aprovadas automaticamente;
     * acima do limite exigem aprovacao manual do tesoureiro_paroquial.
     */
    public function created(Movimento $movimento): void
    {
        if ($movimento->tipo === TipoMovimento::DespesaCentro
            && (float) $movimento->valor <= (float) config('sge.valor_aprovacao_despesa')
        ) {
            $movimento->status_conciliacao = StatusConciliacao::Aprovado;
            $movimento->saveQuietly();
        }
    }
}
