<?php

namespace App\Models;

use App\Enums\StatusConciliacao;
use App\Enums\TipoMovimento;
use App\Scopes\ParoquiaScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Movimento extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'paroquia_id',
        'centro_id',
        'usuario_id',
        'fiel_id',
        'metodo_pagamento_id',
        'banco_id',
        'tipo',
        'categoria_despesa_id',
        'valor',
        'ano_competencia',
        'mes_competencia',
        'data_movimento',
        'comprovativo_path',
        'numero_referencia_bancaria',
        'status_conciliacao',
        'motivo_rejeicao',
    ];

    protected $casts = [
        'tipo' => TipoMovimento::class,
        'status_conciliacao' => StatusConciliacao::class,
        'valor' => 'decimal:2',
        'ano_competencia' => 'integer',
        'mes_competencia' => 'integer',
        'data_movimento' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ParoquiaScope);
    }

    public function paroquia(): BelongsTo
    {
        return $this->belongsTo(Paroquia::class);
    }

    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function fiel(): BelongsTo
    {
        return $this->belongsTo(Fiel::class);
    }

    public function metodoPagamento(): BelongsTo
    {
        return $this->belongsTo(MetodoPagamento::class);
    }

    public function banco(): BelongsTo
    {
        return $this->belongsTo(Banco::class);
    }

    public function categoriaDespesa(): BelongsTo
    {
        return $this->belongsTo(CategoriaDespesa::class);
    }
}
