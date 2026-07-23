<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Paroquia extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'diocese',
        'morada',
        'responsavel',
        'email_contato',
        'telefone',
        'status',
    ];

    public function centros(): HasMany
    {
        return $this->hasMany(Centro::class);
    }

    public function bancos(): HasMany
    {
        return $this->hasMany(Banco::class);
    }

    public function fieis(): HasMany
    {
        return $this->hasMany(Fiel::class);
    }

    public function categoriasDespesa(): HasMany
    {
        return $this->hasMany(CategoriaDespesa::class);
    }

    public function movimentos(): HasMany
    {
        return $this->hasMany(Movimento::class);
    }

    public function anosLetivos(): HasMany
    {
        return $this->hasMany(AnoLetivo::class);
    }

    public function catequizandos(): HasMany
    {
        return $this->hasMany(Catequizando::class);
    }

    public function catequistas(): HasMany
    {
        return $this->hasMany(Catequista::class);
    }

    public function turmas(): HasMany
    {
        return $this->hasMany(Turma::class);
    }

    public function inscricoes(): HasMany
    {
        return $this->hasMany(Inscricao::class);
    }
}
