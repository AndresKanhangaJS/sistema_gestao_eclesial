<?php

namespace App\Models;

use App\Scopes\ParoquiaScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Catequizando extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'paroquia_id',
        'centro_id',
        'fiel_id',
        'nome_completo',
        'nome_pai',
        'nome_mae',
        'profissao',
        'municipio_nascimento',
        'provincia_nascimento',
        'pais_nascimento',
        'data_nascimento',
        'sexo',
        'residencia',
        'rua_numero',
        'edificio',
        'casa_ap',
        'numero_identificacao',
        'telefone',
        'telefone_casa',
        'email',
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

    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    public function fiel(): BelongsTo
    {
        return $this->belongsTo(Fiel::class);
    }

    public function dadosReligiosos(): HasOne
    {
        return $this->hasOne(DadosReligiosos::class);
    }

    public function catequizandoCentros(): HasMany
    {
        return $this->hasMany(CatequizandoCentro::class);
    }

    public function centros(): BelongsToMany
    {
        return $this->belongsToMany(Centro::class, 'catequizando_centros')
            ->using(CatequizandoCentro::class)
            ->withPivot(['data_inicio', 'data_fim', 'motivo_transferencia'])
            ->withTimestamps();
    }

    public function inscricoes(): HasMany
    {
        return $this->hasMany(Inscricao::class);
    }
}
