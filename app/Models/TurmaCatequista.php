<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TurmaCatequista extends Pivot
{
    protected $table = 'turma_catequista';

    public $incrementing = true;

    protected $fillable = [
        'turma_id',
        'catequista_id',
        'papel',
        'data_inicio',
        'data_fim',
    ];

    protected $casts = [
        'data_inicio' => 'date',
        'data_fim' => 'date',
    ];

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function catequista(): BelongsTo
    {
        return $this->belongsTo(Catequista::class);
    }
}
