<?php

namespace Tests\Feature;

use App\Filament\Pages\AlterarSenhaObrigatoria;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Centro;
use App\Models\Paroquia;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Um utilizador novo (ou com a senha redefinida por um administrador_paroquial/
 * coordenador_centro) nunca escolheu a propria senha — deve_alterar_senha
 * obriga-o a trocar por uma pessoal antes de conseguir usar qualquer outra
 * pagina do painel (ForcarAlteracaoSenha + AlterarSenhaObrigatoria).
 */
class AlterarSenhaObrigatoriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_utilizador_criado_pela_resource_fica_marcado_para_trocar_senha(): void
    {
        $paroquia = Paroquia::factory()->create();
        $admin = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $admin->assignRole('administrador_paroquial');
        $this->actingAs($admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Novo Tesoureiro',
                'email' => 'novo.tesoureiro@sge.local',
                'password' => 'password',
                'role' => 'tesoureiro_paroquial',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $novo = User::where('email', 'novo.tesoureiro@sge.local')->firstOrFail();

        $this->assertTrue($novo->deve_alterar_senha);
    }

    public function test_utilizador_com_deve_alterar_senha_e_reencaminhado_para_a_pagina_de_troca(): void
    {
        $user = User::factory()->create(['status' => 'ativo', 'deve_alterar_senha' => true]);
        $user->assignRole('admin_geral');

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect(AlterarSenhaObrigatoria::getUrl());
    }

    /**
     * A pagina usa o layout "simple" do Filament (o mesmo do Login) — cartao
     * centrado com fundo, sem a sidebar do painel normal.
     */
    public function test_pagina_usa_o_layout_centrado_com_fundo_tal_como_o_login(): void
    {
        $user = User::factory()->create(['status' => 'ativo', 'deve_alterar_senha' => true]);
        $user->assignRole('admin_geral');

        $response = $this->actingAs($user)->get(AlterarSenhaObrigatoria::getUrl());

        $response->assertOk();
        $response->assertSee('fi-simple-layout', false);
        $response->assertSee('fi-simple-main', false);
        $response->assertDontSee('fi-sidebar', false);
    }

    /**
     * Pedido do cliente: antes de orientar a troca de senha, tem de ficar
     * evidente que o login teve sucesso — um alerta fixo na página (não um
     * toast que desaparece sozinho), com o nome de quem entrou.
     */
    public function test_pagina_mostra_alerta_de_login_bem_sucedido_antes_de_orientar_a_troca(): void
    {
        $user = User::factory()->create(['name' => 'Maria dos Santos', 'status' => 'ativo', 'deve_alterar_senha' => true]);
        $user->assignRole('admin_geral');

        $response = $this->actingAs($user)->get(AlterarSenhaObrigatoria::getUrl());

        $response->assertOk();
        $response->assertSeeInOrder([
            'Login efetuado com sucesso',
            'Maria dos Santos',
            'Por segurança, precisa de escolher uma senha pessoal',
        ]);
    }

    public function test_utilizador_sem_deve_alterar_senha_acede_normalmente(): void
    {
        $user = User::factory()->create(['status' => 'ativo', 'deve_alterar_senha' => false]);
        $user->assignRole('admin_geral');

        $this->actingAs($user)->get('/admin')->assertOk();
    }

    public function test_submeter_a_pagina_troca_a_senha_e_liberta_o_acesso(): void
    {
        $user = User::factory()->create([
            'status' => 'ativo',
            'password' => Hash::make('senha-temporaria'),
            'deve_alterar_senha' => true,
        ]);
        $user->assignRole('admin_geral');

        $this->actingAs($user);

        Livewire::test(AlterarSenhaObrigatoria::class)
            ->fillForm([
                'nova_senha' => 'senha-pessoal-nova',
                'nova_senha_confirmation' => 'senha-pessoal-nova',
            ])
            ->call('guardar')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertFalse($user->deve_alterar_senha);
        $this->assertTrue(Hash::check('senha-pessoal-nova', $user->password));

        $this->actingAs($user)->get('/admin')->assertOk();
    }

    /**
     * Regressao: AuthenticateSession compara, em cada pedido de pagina
     * normal, o hash da senha guardado na sessao com o hash actual do
     * utilizador — e desliga a sessao sozinho se nao baterem certo. Como a
     * troca de senha acontece dentro de um pedido Livewire (AJAX) que nao
     * passa por esse middleware, sem o fix a sessao ficava com o hash
     * antigo e o pedido de pagina seguinte era tratado como uma sessao
     * "roubada", mandando sempre de volta para o login — o "loop" reportado
     * pelo utilizador (login -> trocar senha -> login -> trocar senha...).
     */
    public function test_trocar_a_senha_nao_desliga_a_sessao_no_pedido_de_pagina_seguinte(): void
    {
        $user = User::factory()->create([
            'status' => 'ativo',
            'password' => Hash::make('senha-temporaria'),
            'deve_alterar_senha' => true,
        ]);
        $user->assignRole('admin_geral');

        $this->actingAs($user);

        // Estabelece o marcador de sessao do AuthenticateSession com o hash
        // da senha ANTIGA, tal como um login real faria antes desta troca.
        $this->get(AlterarSenhaObrigatoria::getUrl())->assertOk();

        Livewire::test(AlterarSenhaObrigatoria::class)
            ->fillForm([
                'nova_senha' => 'senha-pessoal-nova',
                'nova_senha_confirmation' => 'senha-pessoal-nova',
            ])
            ->call('guardar')
            ->assertHasNoFormErrors();

        // Sem o fix, este pedido seria apanhado pelo AuthenticateSession e
        // reencaminhado para o login em vez de mostrar o dashboard.
        $this->get('/admin')->assertOk();
    }

    public function test_pode_fazer_logout_mesmo_com_deve_alterar_senha_pendente(): void
    {
        $user = User::factory()->create(['status' => 'ativo', 'deve_alterar_senha' => true]);
        $user->assignRole('admin_geral');

        $this->actingAs($user)
            ->post('/admin/logout')
            ->assertRedirect();

        $this->assertGuest();
    }

    public function test_administrador_paroquial_pode_redefinir_senha_de_tesoureiro_da_sua_paroquia(): void
    {
        $paroquia = Paroquia::factory()->create();
        $admin = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $admin->assignRole('administrador_paroquial');

        $tesoureiro = User::factory()->create([
            'paroquia_id' => $paroquia->id,
            'password' => Hash::make('senha-antiga'),
            'deve_alterar_senha' => false,
        ]);
        $tesoureiro->assignRole('tesoureiro_paroquial');

        $this->assertTrue($admin->can('resetPassword', $tesoureiro));

        $hashAntigo = $tesoureiro->password;

        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->callTableAction('redefinirSenha', $tesoureiro);

        $tesoureiro->refresh();

        $this->assertNotSame($hashAntigo, $tesoureiro->password);
        $this->assertTrue($tesoureiro->deve_alterar_senha);
    }

    public function test_coordenador_centro_gere_apenas_utilizadores_do_proprio_centro(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centroProprio = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $centroAlheio = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $coordenador = User::factory()->create(['paroquia_id' => $paroquia->id, 'centro_id' => $centroProprio->id]);
        $coordenador->assignRole('coordenador_centro');

        $secretarioProprio = User::factory()->create(['paroquia_id' => $paroquia->id, 'centro_id' => $centroProprio->id]);
        $secretarioProprio->assignRole('secretario_centro');

        $secretarioAlheio = User::factory()->create(['paroquia_id' => $paroquia->id, 'centro_id' => $centroAlheio->id]);
        $secretarioAlheio->assignRole('secretario_centro');

        $outroCoordenador = User::factory()->create(['paroquia_id' => $paroquia->id, 'centro_id' => $centroProprio->id]);
        $outroCoordenador->assignRole('coordenador_centro');

        $this->assertTrue($coordenador->can('update', $secretarioProprio));
        $this->assertFalse($coordenador->can('update', $secretarioAlheio));
        $this->assertFalse($coordenador->can('update', $outroCoordenador));
        $this->assertFalse($coordenador->can('resetPassword', $secretarioAlheio));
        $this->assertTrue($coordenador->can('resetPassword', $secretarioProprio));
    }

    public function test_coordenador_centro_cria_utilizador_preso_ao_seu_proprio_centro(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $outroCentro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $coordenador = User::factory()->create(['paroquia_id' => $paroquia->id, 'centro_id' => $centro->id]);
        $coordenador->assignRole('coordenador_centro');
        $this->actingAs($coordenador);

        // Mesmo tentando adulterar o centro_id no payload directo (fora do
        // form), o observer tem de o corrigir para o do coordenador.
        $novo = User::create([
            'name' => 'Novo Secretário',
            'email' => 'novo.secretario@sge.local',
            'password' => 'password',
            'centro_id' => $outroCentro->id,
        ]);
        $novo->assignRole('secretario_centro');

        $this->assertSame($centro->id, $novo->fresh()->centro_id);
        $this->assertSame($paroquia->id, $novo->fresh()->paroquia_id);
    }
}
