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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'status', 'paroquia_id', 'centro_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * O 'ativo' por omissao da coluna existe na BD, mas uma instancia recem
     * criada em memoria (ex.: User::factory()->create() sem 'status'
     * explicito) so o reflectiria depois de um refresh() — declarar aqui
     * tambem evita canAccessPanel() ver null em vez de 'ativo'.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'ativo',
    ];

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

    public function catequista(): HasOne
    {
        return $this->hasOne(Catequista::class);
    }

    /**
     * Sem esta implementacao, Filament\Http\Middleware\Authenticate cai no
     * fallback "config('app.env') !== 'local'" — ou seja, fora do ambiente
     * local (testing, production) TODOS os utilizadores, incluindo
     * admin_geral, ficavam bloqueados com 403 ao aceder a /admin.
     *
     * status === 'ativo' e verificado aqui como reforco (defesa em
     * profundidade): se a conta for desactivada a meio de uma sessao ja
     * autenticada, este middleware corre em cada pedido e corta o acesso de
     * imediato, sem esperar por um novo login. A app\Filament\Pages\Auth\Login
     * ja bloqueia a autenticacao em si para contas inactivas.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->status === 'ativo' && $this->hasAnyRole([
            'admin_geral',
            'administrador_paroquial',
            'tesoureiro_paroquial',
            'tesoureiro_centro',
            'consultor',
            'coordenador_catequese_paroquia',
            'coordenador_catequese_centro',
            'secretario_catequese',
            'tesoureiro_catequese',
        ]);
    }
}
