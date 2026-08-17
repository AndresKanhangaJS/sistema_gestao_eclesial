<?php

namespace App\Models;

use App\Models\Concerns\TemIdMascarado;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Paroquia extends Model
{
    use HasFactory;
    use TemIdMascarado;

    protected $fillable = [
        'nome',
        'diocese',
        'morada',
        'responsavel',
        'email_contato',
        'telefone',
        'status',
        'logo_path',
    ];

    /**
     * Data URI (base64) do logotipo, para embutir directamente no HTML dos
     * PDFs exportados (RelatorioPdf/Browsershot renderiza via Chromium
     * headless) — evita depender do disco activo ('local'/'s3') conseguir
     * resolver uma URL publicamente acessível a partir do processo do
     * Chromium, que não tem sessão de utilizador nem, no caso do disco
     * 'local', necessariamente uma rota pública configurada.
     */
    public function logoBase64(): ?string
    {
        if (blank($this->logo_path)) {
            return null;
        }

        $disk = Storage::disk(config('filesystems.default'));

        if (! $disk->exists($this->logo_path)) {
            return null;
        }

        $mime = $disk->mimeType($this->logo_path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($disk->get($this->logo_path));
    }

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
