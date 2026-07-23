<?php

namespace App\Models;

use App\Enums\EstadoInscricaoTurma;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class InscricaoTurma extends Pivot
{
    protected $table = 'inscricao_turma';

    public $incrementing = true;

    protected $fillable = [
        'inscricao_id',
        'turma_id',
        'status',
        'data_inicio',
        'data_fim',
        'motivo',
    ];

    protected $casts = [
        'status' => EstadoInscricaoTurma::class,
        'data_inicio' => 'date',
        'data_fim' => 'date',
    ];

    public function inscricao(): BelongsTo
    {
        return $this->belongsTo(Inscricao::class);
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }
}
