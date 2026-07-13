<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Apenas usuarios activos devem aceder ao sistema": bloqueado em duas
 * camadas — a autenticacao em si (Login::getCredentialsFromFormData adiciona
 * status=>ativo as credenciais) e, como reforco, User::canAccessPanel() para
 * sessoes ja autenticadas quando a conta e desactivada a meio do caminho.
 */
class UtilizadorInactivoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_utilizador_inactivo_nao_consegue_autenticar_se(): void
    {
        $user = User::factory()->create([
            'email' => 'inactivo@sge.local',
            'password' => Hash::make('password'),
            'status' => 'inativo',
        ]);
        $user->assignRole('admin_geral');

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'inactivo@sge.local',
                'password' => 'password',
            ])
            ->call('authenticate');

        $this->assertGuest();
    }

    public function test_utilizador_activo_consegue_autenticar(): void
    {
        $user = User::factory()->create([
            'email' => 'activo@sge.local',
            'password' => Hash::make('password'),
            'status' => 'ativo',
        ]);
        $user->assignRole('admin_geral');

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'activo@sge.local',
                'password' => 'password',
            ])
            ->call('authenticate');

        $this->assertAuthenticatedAs($user);
    }

    public function test_sessao_ja_autenticada_perde_acesso_ao_painel_se_a_conta_for_desactivada(): void
    {
        $user = User::factory()->create(['status' => 'ativo']);
        $user->assignRole('admin_geral');

        $this->actingAs($user)->get('/admin')->assertOk();

        $user->update(['status' => 'inativo']);

        $this->actingAs($user->fresh())->get('/admin')->assertForbidden();
    }

    public function test_can_access_panel_verifica_o_status(): void
    {
        $ativo = User::factory()->create(['status' => 'ativo']);
        $ativo->assignRole('admin_geral');

        $inativo = User::factory()->create(['status' => 'inativo']);
        $inativo->assignRole('admin_geral');

        $panel = Filament::getPanel('admin');

        $this->assertTrue($ativo->canAccessPanel($panel));
        $this->assertFalse($inativo->canAccessPanel($panel));
    }
}
