<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetodoPagamento extends Model
{
    use HasFactory;

    protected $table = 'metodos_pagamento';

    protected $fillable = [
        'nome',
        'exige_comprovativo',
        'status',
    ];

    protected $casts = [
        'exige_comprovativo' => 'boolean',
    ];

    public function movimentos(): HasMany
    {
        return $this->hasMany(Movimento::class);
    }
}
