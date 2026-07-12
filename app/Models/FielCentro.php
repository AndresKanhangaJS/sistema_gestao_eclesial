<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class FielCentro extends Pivot
{
    protected $table = 'fiel_centros';

    public $incrementing = true;

    protected $fillable = [
        'fiel_id',
        'centro_id',
        'data_inicio',
        'data_fim',
        'principal',
        'motivo_transferencia',
    ];

    protected $casts = [
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'principal' => 'boolean',
    ];

    public function fiel(): BelongsTo
    {
        return $this->belongsTo(Fiel::class);
    }

    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }
}
