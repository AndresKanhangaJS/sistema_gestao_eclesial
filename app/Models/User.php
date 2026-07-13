<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'paroquia_id', 'centro_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function paroquia(): BelongsTo
    {
        return $this->belongsTo(Paroquia::class);
    }

    public function centro(): BelongsTo
    {
        return $this->belongsTo(Centro::class);
    }

    /**
     * Sem esta implementacao, Filament\Http\Middleware\Authenticate cai no
     * fallback "config('app.env') !== 'local'" — ou seja, fora do ambiente
     * local (testing, production) TODOS os utilizadores, incluindo
     * admin_geral, ficavam bloqueados com 403 ao aceder a /admin.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole([
            'admin_geral',
            'administrador_paroquial',
            'tesoureiro_paroquial',
            'tesoureiro_centro',
            'consultor',
        ]);
    }
}
