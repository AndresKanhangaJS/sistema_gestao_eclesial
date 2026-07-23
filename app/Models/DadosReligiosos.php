<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DadosReligiosos extends Model
{
    use HasFactory;

    protected $table = 'dados_religiosos';

    protected $fillable = [
        'catequizando_id',
        'paroquia_baptismo',
        'data_baptismo',
        'pais_baptismo',
        'paroquia_comunhao',
        'data_comunhao',
        'pais_comunhao',
        'padrinho_nome',
        'padrinho_telefone',
        'madrinha_nome',
        'madrinha_telefone',
        'paroquia_transferencia',
        'ano_transferencia',
        'pertence_grupo',
    ];

    protected $casts = [
        'data_baptismo' => 'date',
        'data_comunhao' => 'date',
        'pertence_grupo' => 'boolean',
    ];

    public function catequizando(): BelongsTo
    {
        return $this->belongsTo(Catequizando::class);
    }
}
