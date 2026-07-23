<?php

namespace App\Models;

use App\Enums\EstadoInscricao;
use App\Enums\TipoInscricao;
use App\Scopes\ParoquiaScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inscricao extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'inscricoes';

    protected $fillable = [
        'paroquia_id',
        'centro_id',
        'catequizando_id',
        'ano_letivo_id',
        'ano_catequetico_id',
        'catequista_id',
        'inscricao_anterior_id',
        'tipo',
        'numero_ficha',
        'data_atendimento',
        'estado',
        'observacoes',
    ];

    protected $casts = [
        'data_atendimento' => 'date',
        'tipo' => TipoInscricao::class,
        'estado' => EstadoInscricao::class,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ParoquiaScope);

        static::creating(function (self $inscricao): void {
            if (blank($inscricao->numero_ficha) && $inscricao->paroquia_id && $inscricao->ano_letivo_id) {
                $inscricao->numero_ficha = self::proximoNumeroFicha($inscricao->paroquia_id, $inscricao->ano_letivo_id);
            }
        });
    }

    /**
     * Gerado automaticamente (nunca digitado a mao no formulario), no
     * formato "F0001", reiniciando por (paroquia_id, ano_letivo_id) — mesmo
     * espirito do codigo_dizimista em Fiel::proximoCodigoDizimista(). Ignora
     * valores antigos que nao sigam o formato "F"+digitos (ex.: dados de
     * demonstracao antigos no formato "F0001/26") ao calcular o proximo
     * numero, para nao quebrar com dados legados.
     */
    public static function proximoNumeroFicha(int $paroquiaId, int $anoLetivoId): string
    {
        $ultimo = static::withTrashed()
            ->withoutGlobalScopes()
            ->where('paroquia_id', $paroquiaId)
            ->where('ano_letivo_id', $anoLetivoId)
            ->get(['numero_ficha'])
            ->map(function (self $inscricao) {
                $semPrefixo = str_starts_with($inscricao->numero_ficha, 'F')
                    ? substr($inscricao->numero_ficha, 1)
                    : $inscricao->numero_ficha;

                return ctype_digit($semPrefixo) ? (int) $semPrefixo : 0;
            })
            ->max() ?? 0;

        return 'F'.str_pad((string) ($ultimo + 1), 4, '0', STR_PAD_LEFT);
    }

    public function paroquia(): BelongsTo
    {
        return $this->belongsTo(Paroquia::class);
    }

    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    public function catequizando(): BelongsTo
    {
        return $this->belongsTo(Catequizando::class);
    }

    public function anoLetivo(): BelongsTo
    {
        return $this->belongsTo(AnoLetivo::class);
    }

    public function anoCatequetico(): BelongsTo
    {
        return $this->belongsTo(AnoCatequetico::class);
    }

    /**
     * Sacramento(s) perseguidos nesta inscricao — usado para filtrar as
     * turmas compativeis ao colocar/trocar de turma (docs/modulos/
     * catequese.md secc. 12): o conjunto tem de bater certo por completo com
     * turma.sacramentos, nao so parcialmente.
     */
    public function sacramentos(): BelongsToMany
    {
        return $this->belongsToMany(Sacramento::class, 'inscricao_sacramento')->withTimestamps();
    }

    public function catequista(): BelongsTo
    {
        return $this->belongsTo(Catequista::class);
    }

    public function inscricaoAnterior(): BelongsTo
    {
        return $this->belongsTo(self::class, 'inscricao_anterior_id');
    }

    public function inscricaoSeguinte(): HasOne
    {
        return $this->hasOne(self::class, 'inscricao_anterior_id');
    }

    public function inscricaoTurmas(): HasMany
    {
        return $this->hasMany(InscricaoTurma::class);
    }

    public function turmaAtiva(): HasOne
    {
        return $this->hasOne(InscricaoTurma::class)->where('status', 'ativo');
    }

    public function turmas(): BelongsToMany
    {
        return $this->belongsToMany(Turma::class, 'inscricao_turma')
            ->using(InscricaoTurma::class)
            ->withPivot(['status', 'data_inicio', 'data_fim', 'motivo'])
            ->withTimestamps();
    }
}
