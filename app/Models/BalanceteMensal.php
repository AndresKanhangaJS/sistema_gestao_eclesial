<?php

namespace App\Models;

use App\Models\Concerns\TemIdMascarado;
use App\Scopes\ParoquiaScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fecho mensal do Balancete por centro (Modulo 7 — ver App\Services\
 * BalanceteService). Guarda os totais oficiais do mes e o saldo transitado
 * para o mes seguinte; a unique key (paroquia_id, centro_id, ano, mes) na
 * migration impede fechar o mesmo mes duas vezes.
 */
class BalanceteMensal extends Model
{
    use HasFactory;
    use SoftDeletes;
    use TemIdMascarado;

    protected $table = 'balancetes_mensais';

    protected $fillable = [
        'paroquia_id',
        'centro_id',
        'usuario_id',
        'ano',
        'mes',
        'saldo_mes_anterior',
        'total_receitas',
        'total_despesas',
        'saldo_mes',
        'percentual_bispado_usado',
        'percentual_dizimo_usado',
        'movimento_bispado_id',
        'movimento_dizimo_id',
        'fechado_em',
    ];

    protected $casts = [
        'ano' => 'integer',
        'mes' => 'integer',
        'saldo_mes_anterior' => 'decimal:2',
        'total_receitas' => 'decimal:2',
        'total_despesas' => 'decimal:2',
        'saldo_mes' => 'decimal:2',
        'percentual_bispado_usado' => 'decimal:2',
        'percentual_dizimo_usado' => 'decimal:2',
        'fechado_em' => 'datetime',
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

    public function movimentoBispado(): BelongsTo
    {
        return $this->belongsTo(Movimento::class, 'movimento_bispado_id');
    }

    public function movimentoDizimo(): BelongsTo
    {
        return $this->belongsTo(Movimento::class, 'movimento_dizimo_id');
    }
}
