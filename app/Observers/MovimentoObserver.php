<?php

namespace App\Observers;

use App\Enums\StatusConciliacao;
use App\Enums\TipoMovimento;
use App\Models\Centro;
use App\Models\Movimento;
use Illuminate\Support\Facades\Auth;

class MovimentoObserver
{
    public function creating(Movimento $movimento): void
    {
        if (! $movimento->usuario_id && Auth::check()) {
            $movimento->usuario_id = Auth::id();
        }

        // O formulario do MovimentoResource nao tem campo paroquia_id (o
        // centro ja implica a paroquia) — deriva-se sempre do centro
        // seleccionado, nunca do utilizador (admin_geral nao tem paroquia_id).
        if (! $movimento->paroquia_id && $movimento->centro_id) {
            $movimento->paroquia_id = Centro::withoutGlobalScopes()->find($movimento->centro_id)?->paroquia_id;
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
