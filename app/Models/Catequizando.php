<?php

namespace App\Models;

use App\Enums\EstadoInscricao;
use App\Models\Concerns\TemIdMascarado;
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
    use TemIdMascarado;

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
        'data_nascimento_aproximada',
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
        'data_nascimento_aproximada' => 'boolean',
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

    /**
     * Inscricao nao-cancelada do catequizando num ano lectivo concreto —
     * reaproveitado pelas colunas "Ano de Catequese"/"Estado" da lista, pelo
     * modal "Dados de Catequese" e pela accao "Confirmar para o Próximo Ano
     * Lectivo" (docs/modulos/catequese.md secc. 27), para nao repetir a
     * mesma query em quatro sitios.
     */
    public function inscricaoNoAnoLetivo(?int $anoLetivoId): ?Inscricao
    {
        if (blank($anoLetivoId)) {
            return null;
        }

        return $this->inscricoes
            ->where('ano_letivo_id', $anoLetivoId)
            ->where('estado', '!=', EstadoInscricao::Cancelado)
            ->first();
    }
}
