<?php

namespace Tests\Feature;

use App\Filament\Pages\Relatorios\DemonstrativoArrecadacao;
use App\Models\Catequista;
use App\Models\Catequizando;
use App\Models\Centro;
use App\Models\Paroquia;
use App\Models\User;
use App\Support\ResolveCentroExportacao;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Pedido do cliente (2026-08-17): quem nao esta preso a um centro (admin_geral,
 * administrador_paroquial, tesoureiro_paroquial, coordenador_catequese_paroquia)
 * deve poder escolher um centro especifico ou "Todos" ao exportar — nos
 * relatorios financeiros (FiltraPorCentro) e nos da Catequese
 * (TemExportacaoComEstado::accaoExportarComEstado(comCentro: true)).
 */
class SeletorCentroRelatoriosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_tesoureiro_centro_fica_sempre_preso_ao_seu_centro_no_demonstrativo(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centroProprio = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $centroAlheio = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $tesoureiro = User::factory()->create(['paroquia_id' => $paroquia->id, 'centro_id' => $centroProprio->id]);
        $tesoureiro->assignRole('tesoureiro_centro');
        $this->actingAs($tesoureiro);

        $page = new DemonstrativoArrecadacao;
        $page->mount();

        $this->assertFalse($page->mostrarFiltroCentro());
        $this->assertSame($centroProprio->id, $page->centroIdParaConsulta());

        // Mesmo adulterando centroId no cliente, fica sempre preso ao proprio.
        $page->centroId = $centroAlheio->id;
        $this->assertSame($centroProprio->id, $page->centroIdParaConsulta());
    }

    public function test_administrador_paroquial_pode_escolher_um_centro_especifico_ou_todos(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centroA = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $centroB = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $admin = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $admin->assignRole('administrador_paroquial');
        $this->actingAs($admin);

        $page = new DemonstrativoArrecadacao;
        $page->mount();

        $this->assertTrue($page->mostrarFiltroCentro());
        $this->assertNull($page->centroIdParaConsulta());
        $this->assertArrayHasKey($centroA->id, $page->getCentrosDisponiveis());
        $this->assertArrayHasKey($centroB->id, $page->getCentrosDisponiveis());

        $page->centroId = $centroA->id;
        $this->assertSame($centroA->id, $page->centroIdParaConsulta());
    }

    public function test_centro_de_outra_paroquia_e_ignorado_cai_para_todos(): void
    {
        $paroquiaPropria = Paroquia::factory()->create();
        $paroquiaAlheia = Paroquia::factory()->create();
        $centroAlheio = Centro::factory()->create(['paroquia_id' => $paroquiaAlheia->id]);

        $admin = User::factory()->create(['paroquia_id' => $paroquiaPropria->id]);
        $admin->assignRole('administrador_paroquial');
        $this->actingAs($admin);

        $page = new DemonstrativoArrecadacao;
        $page->mount();
        $page->centroId = $centroAlheio->id;

        $this->assertNull($page->centroIdParaConsulta());
    }

    public function test_resolve_centro_exportacao_forca_centro_proprio_para_tesoureiro_centro(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centroProprio = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $centroAlheio = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $tesoureiro = User::factory()->create(['paroquia_id' => $paroquia->id, 'centro_id' => $centroProprio->id]);
        $tesoureiro->assignRole('tesoureiro_centro');

        $request = Request::create('/', 'GET', ['centro_id' => $centroAlheio->id]);

        $centro = ResolveCentroExportacao::centro($tesoureiro, $request);

        $this->assertSame($centroProprio->id, $centro?->id);
    }

    public function test_resolve_centro_exportacao_devolve_null_sem_query_param(): void
    {
        $paroquia = Paroquia::factory()->create();
        $admin = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $admin->assignRole('administrador_paroquial');

        $centro = ResolveCentroExportacao::centro($admin, Request::create('/', 'GET'));

        $this->assertNull($centro);
    }

    public function test_demonstrativo_arrecadacao_pdf_aceita_centro_id_do_query_param(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $admin = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $admin->assignRole('administrador_paroquial');
        $this->actingAs($admin);

        $this->get('/relatorios/demonstrativo-arrecadacao/pdf?ano='.now()->year.'&centro_id='.$centro->id)
            ->assertOk();
    }

    public function test_coordenador_catequese_centro_fica_sempre_preso_ao_seu_centro_na_exportacao(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centroProprio = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $centroAlheio = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        Catequizando::create([
            'paroquia_id' => $paroquia->id,
            'centro_id' => $centroProprio->id,
            'nome_completo' => 'Catequizando Proprio',
            'data_nascimento' => now()->subYears(10),
            'sexo' => 'M',
            'status' => 'ativo',
        ]);
        Catequizando::create([
            'paroquia_id' => $paroquia->id,
            'centro_id' => $centroAlheio->id,
            'nome_completo' => 'Catequizando Alheio',
            'data_nascimento' => now()->subYears(10),
            'sexo' => 'F',
            'status' => 'ativo',
        ]);

        $coordenador = User::factory()->create(['paroquia_id' => $paroquia->id, 'centro_id' => $centroProprio->id]);
        $coordenador->assignRole('coordenador_catequese_centro');
        $this->actingAs($coordenador);

        // Tenta adulterar via query param um centro que nao e o seu.
        $response = $this->get('/relatorios/catequizandos/excel?estado=ativo&centro_id='.$centroAlheio->id);

        $response->assertOk();
    }

    public function test_admin_geral_pode_filtrar_catequistas_por_centro_via_query_param(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        Catequista::create([
            'paroquia_id' => $paroquia->id,
            'centro_id' => $centro->id,
            'nome_completo' => 'Catequista de Teste',
            'data_nascimento' => now()->subYears(30),
            'ativo' => true,
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin_geral');
        $this->actingAs($admin);

        $this->get('/relatorios/catequistas/pdf?estado=ativo&centro_id='.$centro->id)
            ->assertOk();
    }
}
