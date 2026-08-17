<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test das novas Resources (UserResource, BancoResource): confirma que
 * o painel carrega para admin_geral e que fica bloqueado para quem nao deve
 * gerir utilizadores (UserPolicy e sempre false, admin_geral bypassa via
 * Gate::before).
 */
class NovasResourcesAcessoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_admin_geral_acede_a_listagem_de_utilizadores(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin_geral');

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk();
    }

    public function test_tesoureiro_paroquial_nao_acede_a_listagem_de_utilizadores(): void
    {
        $tesoureiro = User::factory()->create();
        $tesoureiro->assignRole('tesoureiro_paroquial');

        $this->actingAs($tesoureiro)
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_admin_geral_acede_a_listagem_de_bancos(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin_geral');

        $this->actingAs($admin)
            ->get('/admin/bancos')
            ->assertOk();
    }

    /**
     * Pedido do cliente: o grupo de navegação do Filament Shield (só o menu
     * lateral) deve dizer "Papéis e Permissões", não o nome técnico do
     * pacote — ver lang/vendor/filament-shield/pt_PT/filament-shield.php.
     */
    public function test_menu_do_shield_mostra_papeis_e_permissoes_em_vez_do_nome_do_pacote(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin_geral');

        $response = $this->actingAs($admin)->get('/admin/shield/roles');

        $response->assertOk();
        $response->assertSee('Papéis e Permissões');
        $response->assertDontSee('Filament Shield');
    }
}
