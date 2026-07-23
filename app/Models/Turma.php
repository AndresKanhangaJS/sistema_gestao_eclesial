<?php

namespace App\Models;

use App\Scopes\ParoquiaScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Turma extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'paroquia_id',
        'centro_id',
        'ano_letivo_id',
        'ano_catequetico_id',
        'publico_alvo',
        'periodo',
        'hora_inicio',
        'hora_fim',
        'tipo',
        'vagas_minimo',
        'vagas_maximo',
        'vagas_bloqueadas',
        'status',
    ];

    protected $casts = [
        'hora_inicio' => 'datetime:H:i',
        'hora_fim' => 'datetime:H:i',
        'vagas_bloqueadas' => 'boolean',
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

    public function anoLetivo(): BelongsTo
    {
        return $this->belongsTo(AnoLetivo::class);
    }

    public function anoCatequetico(): BelongsTo
    {
        return $this->belongsTo(AnoCatequetico::class);
    }

    public function sacramentos(): BelongsToMany
    {
        return $this->belongsToMany(Sacramento::class, 'turma_sacramento')->withTimestamps();
    }

    public function catequistas(): BelongsToMany
    {
        return $this->belongsToMany(Catequista::class, 'turma_catequista')
            ->using(TurmaCatequista::class)
            ->withPivot(['papel', 'data_inicio', 'data_fim'])
            ->withTimestamps();
    }

    public function inscricaoTurmas(): HasMany
    {
        return $this->hasMany(InscricaoTurma::class);
    }

    public function inscricoes(): BelongsToMany
    {
        return $this->belongsToMany(Inscricao::class, 'inscricao_turma')
            ->using(InscricaoTurma::class)
            ->withPivot(['status', 'data_inicio', 'data_fim', 'motivo'])
            ->withTimestamps();
    }

    public function vagasOcupadas(): int
    {
        return $this->inscricoes()->wherePivot('status', 'ativo')->count();
    }

    /**
     * Cheia so quando vagas_maximo esta definido. Nao bloqueia
     * automaticamente novas colocacoes — so informativo, quem gere a turma
     * decide via vagas_bloqueadas (docs/modulos/catequese.md secc. 14).
     */
    public function estaCheia(): bool
    {
        return $this->vagas_maximo !== null && $this->vagasOcupadas() >= $this->vagas_maximo;
    }
}
