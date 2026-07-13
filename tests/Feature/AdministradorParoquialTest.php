<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Models\Banco;
use App\Models\CategoriaDespesa;
use App\Models\Centro;
use App\Models\Fiel;
use App\Models\Movimento;
use App\Models\Paroquia;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * administrador_paroquial: paridade financeira com tesoureiro_paroquial +
 * gestao de utilizadores da propria paroquia, sem depender do admin_geral.
 */
class AdministradorParoquialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function administradorDe(Paroquia $paroquia): User
    {
        $user = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $user->assignRole('administrador_paroquial');

        return $user;
    }

    public function test_tem_paridade_financeira_com_tesoureiro_paroquial(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $movimento = Movimento::factory()->create([
            'paroquia_id' => $paroquia->id,
            'centro_id' => $centro->id,
        ]);

        $administrador = $this->administradorDe($paroquia);

        $this->assertTrue($administrador->can('aprovar', $movimento));
        $this->assertTrue($administrador->can('create', Movimento::class));
        $this->assertTrue($administrador->can('create', Centro::class));
        $this->assertTrue($administrador->can('create', Fiel::class));
        $this->assertTrue($administrador->can('create', Banco::class));
        $this->assertTrue($administrador->can('create', CategoriaDespesa::class));
    }

    public function test_pode_criar_tesoureiro_centro_na_propria_paroquia(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $administrador = $this->administradorDe($paroquia);

        $this->actingAs($administrador);

        $novo = User::create([
            'name' => 'Novo Tesoureiro',
            'email' => 'novo.tesoureiro@sge.local',
            'password' => 'password',
            'centro_id' => $centro->id,
        ]);
        $novo->assignRole('tesoureiro_centro');

        $this->assertSame($paroquia->id, $novo->fresh()->paroquia_id);
        $this->assertTrue($novo->fresh()->hasRole('tesoureiro_centro'));
    }

    /**
     * Regressao: o select de Centro no formulario ficava vazio porque a
     * query da relationship filtrava por $get('paroquia_id') mesmo quando
     * esse valor ainda nao tinha resolvido (null), e where('paroquia_id',
     * null) nunca devolve nada.
     */
    public function test_select_de_centro_mostra_os_centros_da_propria_paroquia_ao_criar_tesoureiro_centro(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $administrador = $this->administradorDe($paroquia);

        $this->actingAs($administrador);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Novo Tesoureiro Centro',
                'email' => 'novo.tesoureiro.centro@sge.local',
                'password' => 'password',
                'role' => 'tesoureiro_centro',
                'centro_id' => $centro->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $novo = User::where('email', 'novo.tesoureiro.centro@sge.local')->firstOrFail();
        $this->assertSame($centro->id, $novo->centro_id);
        $this->assertSame($paroquia->id, $novo->paroquia_id);
    }

    public function test_paroquia_id_do_novo_utilizador_ignora_adulteracao_do_cliente(): void
    {
        $paroquiaPropria = Paroquia::factory()->create();
        $paroquiaAlheia = Paroquia::factory()->create();
        $administrador = $this->administradorDe($paroquiaPropria);

        $this->actingAs($administrador);

        $novo = User::create([
            'name' => 'Utilizador Teste',
            'email' => 'utilizador.teste@sge.local',
            'password' => 'password',
            'paroquia_id' => $paroquiaAlheia->id,
        ]);

        $this->assertSame($paroquiaPropria->id, $novo->fresh()->paroquia_id);
    }

    public function test_nao_pode_atribuir_papel_admin_geral(): void
    {
        $paroquia = Paroquia::factory()->create();
        $this->actingAs($this->administradorDe($paroquia));

        $this->expectException(HttpException::class);

        UserResource::papelPermitido('admin_geral');
    }

    public function test_nao_pode_atribuir_papel_consultor(): void
    {
        $paroquia = Paroquia::factory()->create();
        $this->actingAs($this->administradorDe($paroquia));

        $this->expectException(HttpException::class);

        UserResource::papelPermitido('consultor');
    }

    public function test_nao_pode_atribuir_outro_administrador_paroquial(): void
    {
        $paroquia = Paroquia::factory()->create();
        $this->actingAs($this->administradorDe($paroquia));

        $this->expectException(HttpException::class);

        UserResource::papelPermitido('administrador_paroquial');
    }

    public function test_admin_geral_pode_atribuir_qualquer_papel_incluindo_administrador_paroquial(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin_geral');
        $this->actingAs($admin);

        $this->assertSame('administrador_paroquial', UserResource::papelPermitido('administrador_paroquial'));
    }

    public function test_nao_ve_nem_edita_utilizadores_de_outra_paroquia(): void
    {
        $paroquiaPropria = Paroquia::factory()->create();
        $paroquiaAlheia = Paroquia::factory()->create();

        $administrador = $this->administradorDe($paroquiaPropria);
        $utilizadorAlheio = User::factory()->create(['paroquia_id' => $paroquiaAlheia->id]);
        $utilizadorAlheio->assignRole('tesoureiro_paroquial');

        $this->actingAs($administrador);

        $this->assertFalse($administrador->can('view', $utilizadorAlheio));
        $this->assertFalse($administrador->can('update', $utilizadorAlheio));

        $this->get("/admin/users/{$utilizadorAlheio->id}/edit")->assertNotFound();
    }

    public function test_nao_pode_editar_conta_admin_geral_ou_consultor_mesmo_que_leve_a_mesma_paroquia(): void
    {
        $paroquia = Paroquia::factory()->create();
        $administrador = $this->administradorDe($paroquia);

        // Caso hipotetico/defensivo: mesmo que um admin_geral tivesse
        // paroquia_id preenchido, o administrador_paroquial nao pode geri-lo.
        $adminComParoquia = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $adminComParoquia->assignRole('admin_geral');

        $this->assertFalse($administrador->can('update', $adminComParoquia));
    }
}
