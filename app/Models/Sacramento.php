<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Tabela partilhada entre todas as paróquias, mesmo espírito de AnoCatequetico.
 */
class Sacramento extends Model
{
    use HasFactory;

    protected $fillable = [
        'ordem',
        'nome',
        'status',
    ];

    public function turmas(): BelongsToMany
    {
        return $this->belongsToMany(Turma::class, 'turma_sacramento')->withTimestamps();
    }

    public function inscricoes(): BelongsToMany
    {
        return $this->belongsToMany(Inscricao::class, 'inscricao_sacramento')->withTimestamps();
    }
}
