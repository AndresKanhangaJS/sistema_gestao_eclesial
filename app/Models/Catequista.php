<?php

namespace App\Models;

use App\Scopes\ParoquiaScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dados mínimos — a expandir quando as especificações completas de
 * Catequista forem enviadas (ver docs/modulos/catequese.md, secção 6).
 */
class Catequista extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'paroquia_id',
        'centro_id',
        'fiel_id',
        'user_id',
        'nome_completo',
        'telefone',
        'email',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
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

    public function fiel(): BelongsTo
    {
        return $this->belongsTo(Fiel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function turmas(): BelongsToMany
    {
        return $this->belongsToMany(Turma::class, 'turma_catequista')
            ->using(TurmaCatequista::class)
            ->withPivot(['papel', 'data_inicio', 'data_fim'])
            ->withTimestamps();
    }

    public function inscricoes(): HasMany
    {
        return $this->hasMany(Inscricao::class);
    }
}
