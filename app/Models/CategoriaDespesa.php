<?php

namespace App\Models;

use App\Scopes\ParoquiaScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaDespesa extends Model
{
    use HasFactory;

    protected $table = 'categorias_despesa';

    protected $fillable = [
        'paroquia_id',
        'nome',
        'descricao',
        'status',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ParoquiaScope);
    }

    public function paroquia(): BelongsTo
    {
        return $this->belongsTo(Paroquia::class);
    }

    public function movimentos(): HasMany
    {
        return $this->hasMany(Movimento::class);
    }
}
