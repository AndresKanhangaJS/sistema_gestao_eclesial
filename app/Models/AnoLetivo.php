<?php

namespace App\Models;

use App\Models\Concerns\TemIdMascarado;
use App\Scopes\ParoquiaScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AnoLetivo extends Model
{
    use HasFactory;
    use TemIdMascarado;

    protected $table = 'anos_letivos';

    protected $fillable = [
        'paroquia_id',
        'nome',
        'data_inicio',
        'data_fim',
        'status',
    ];

    protected $casts = [
        'data_inicio' => 'date',
        'data_fim' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ParoquiaScope);
    }

    public function paroquia(): BelongsTo
    {
        return $this->belongsTo(Paroquia::class);
    }

    public function turmas(): HasMany
    {
        return $this->hasMany(Turma::class);
    }

    public function inscricoes(): HasMany
    {
        return $this->hasMany(Inscricao::class);
    }

    /**
     * Trecho para nomes de ficheiro de exportacao (ex.: "2026_2027"),
     * a partir do valor bruto do query param ?ano_letivo= ("todos" ou um id).
     */
    public static function slugParaExportacao(string $anoLetivo): string
    {
        if ($anoLetivo === 'todos') {
            return 'todos';
        }

        $nome = static::find($anoLetivo)?->nome ?? $anoLetivo;

        // Str::slug() engole "/" sem o trocar por separador (fica "20262027",
        // ilegivel) — a barra so vira separador se antes for um espaco (unico
        // caracter que o Str::slug reconhece sempre como fronteira de palavra,
        // seja qual for o separador pedido).
        return Str::slug(str_replace('/', ' ', $nome), '_');
    }
}
