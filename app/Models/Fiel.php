<?php

namespace App\Models;

use App\Scopes\ParoquiaScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fiel extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'fieis';

    protected $fillable = [
        'paroquia_id',
        'nome',
        'codigo_dizimista',
        'telefone',
        'email',
        'data_nascimento',
        'status',
    ];

    protected $casts = [
        'data_nascimento' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ParoquiaScope);
    }

    public function paroquia(): BelongsTo
    {
        return $this->belongsTo(Paroquia::class);
    }

    public function fielCentros(): HasMany
    {
        return $this->hasMany(FielCentro::class);
    }

    public function centros(): BelongsToMany
    {
        return $this->belongsToMany(Centro::class, 'fiel_centros')
            ->using(FielCentro::class)
            ->withPivot(['data_inicio', 'data_fim', 'principal', 'motivo_transferencia'])
            ->withTimestamps();
    }

    public function movimentos(): HasMany
    {
        return $this->hasMany(Movimento::class);
    }
}
