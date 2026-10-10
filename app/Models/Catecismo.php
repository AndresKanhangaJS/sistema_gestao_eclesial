<?php

namespace App\Models;

use App\Models\Concerns\TemIdMascarado;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catecismo (livro/programa) usado por uma turma — "Deus Ama-nos", "Creio em
 * Jesus Salvador"... Tabela partilhada entre todas as paróquias (sem
 * paroquia_id, sem ParoquiaScope), mesmo molde de AnoCatequetico e Sacramento.
 */
class Catecismo extends Model
{
    use HasFactory;
    use TemIdMascarado;

    protected $table = 'catecismos';

    protected $fillable = [
        'ordem',
        'nome',
        'status',
    ];

    public function turmas(): HasMany
    {
        return $this->hasMany(Turma::class);
    }
}
