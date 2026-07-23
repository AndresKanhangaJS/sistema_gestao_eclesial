<?php

namespace App\Models;

use App\Scopes\ParoquiaScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Centro extends Model
{
    use HasFactory;

    protected $fillable = [
        'paroquia_id',
        'nome',
        'localizacao',
        'responsavel_local',
        'status',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ParoquiaScope);
    }

    public function paroquia(): BelongsTo
    {
        return $this->belongsTo(Paroquia::class);
    }

    public function movimentos(): HasMany
    {
        return $this->hasMany(Movimento::class);
    }

    public function fielCentros(): HasMany
    {
        return $this->hasMany(FielCentro::class);
    }

    public function fieis(): BelongsToMany
    {
        return $this->belongsToMany(Fiel::class, 'fiel_centros')
            ->using(FielCentro::class)
            ->withPivot(['data_inicio', 'data_fim', 'principal', 'motivo_transferencia'])
            ->withTimestamps();
    }

    public function catequizandos(): HasMany
    {
        return $this->hasMany(Catequizando::class);
    }

    public function catequizandoCentros(): HasMany
    {
        return $this->hasMany(CatequizandoCentro::class);
    }

    public function catequistas(): HasMany
    {
        return $this->hasMany(Catequista::class);
    }

    public function turmas(): HasMany
    {
        return $this->hasMany(Turma::class);
    }
}
