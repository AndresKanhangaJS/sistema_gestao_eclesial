<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabela partilhada entre todas as paróquias (programa oficial da
 * Arquidiocese) — sem paroquia_id, sem ParoquiaScope.
 */
class AnoCatequetico extends Model
{
    use HasFactory;

    protected $table = 'anos_catequeticos';

    protected $fillable = [
        'ordem',
        'nome',
        'status',
    ];

    public function turmas(): HasMany
    {
        return $this->hasMany(Turma::class);
    }

    public function inscricoes(): HasMany
    {
        return $this->hasMany(Inscricao::class);
    }
}
