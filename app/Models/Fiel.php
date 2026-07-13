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

        static::creating(function (self $fiel): void {
            if (blank($fiel->codigo_dizimista)) {
                $fiel->codigo_dizimista = self::proximoCodigoDizimista();
            }
        });
    }

    /**
     * Gerado automaticamente (nunca digitado a mao no formulario) para nao
     * haver choques de codigos duplicados — codigo_dizimista e unico
     * globalmente, nao so por paroquia. Formato F0001, F0002, ...
     * (cresce naturalmente para mais digitos se ultrapassar F9999).
     *
     * Uma colisao rara por concorrencia (dois registos em simultaneo a
     * calcular o mesmo proximo numero) e apanhada pela unique key da BD e
     * tratada em CreateFiel::handleRecordCreation() com nova tentativa.
     */
    public static function proximoCodigoDizimista(): string
    {
        $ultimo = static::withTrashed()
            ->withoutGlobalScopes()
            ->where('codigo_dizimista', 'like', 'F%')
            ->get(['codigo_dizimista'])
            ->map(fn (self $fiel) => (int) substr($fiel->codigo_dizimista, 1))
            ->max() ?? 0;

        return 'F'.str_pad((string) ($ultimo + 1), 4, '0', STR_PAD_LEFT);
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
