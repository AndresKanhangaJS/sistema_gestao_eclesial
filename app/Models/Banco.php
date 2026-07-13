<?php

namespace App\Models;

use App\Scopes\ParoquiaScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Banco extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'paroquia_id',
        'nome_banco',
        'sigla',
        'numero_conta',
        'iban',
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
