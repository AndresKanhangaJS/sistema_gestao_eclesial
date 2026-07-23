<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CatequizandoCentro extends Pivot
{
    protected $table = 'catequizando_centros';

    public $incrementing = true;

    protected $fillable = [
        'catequizando_id',
        'centro_id',
        'data_inicio',
        'data_fim',
        'motivo_transferencia',
    ];

    protected $casts = [
        'data_inicio' => 'date',
        'data_fim' => 'date',
    ];

    public function catequizando(): BelongsTo
    {
        return $this->belongsTo(Catequizando::class);
    }

    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }
}
