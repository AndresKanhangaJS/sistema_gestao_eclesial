<?php

namespace Tests\Feature;

use App\Models\CategoriaDespesa;
use App\Models\Centro;
use App\Models\Fiel;
use App\Models\Movimento;
use App\Models\Paroquia;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Isolamento multi-tenant: um tesoureiro_paroquial nunca ve nem escreve dados
 * de outra paroquia (CLAUDE.md, regra ABSOLUTA nº3). Cobre tambem a correcao
 * do ForcaParoquiaUtilizadorObserver — antes desta correcao, o campo
 * paroquia_id escondido nestes formularios podia ser adulterado no cliente.
 */
class IsolamentoMultiTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function tesoureiroDe(Paroquia $paroquia): User
    {
        $user = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $user->assignRole('tesoureiro_paroquial');

        return $user;
    }

    public function test_tesoureiro_paroquial_nao_ve_fieis_de_outra_paroquia(): void
    {
        $paroquiaA = Paroquia::factory()->create();
        $paroquiaB = Paroquia::factory()->create();

        Fiel::factory()->create(['paroquia_id' => $paroquiaA->id]);
        $fielB = Fiel::factory()->create(['paroquia_id' => $paroquiaB->id]);

        $this->actingAs($this->tesoureiroDe($paroquiaA));

        $ids = Fiel::pluck('id');

        $this->assertCount(1, $ids);
        $this->assertNotContains($fielB->id, $ids);
    }

    public function test_tesoureiro_paroquial_nao_ve_movimentos_de_outra_paroquia(): void
    {
        $paroquiaA = Paroquia::factory()->create();
        $paroquiaB = Paroquia::factory()->create();

        $centroA = Centro::factory()->create(['paroquia_id' => $paroquiaA->id]);
        $centroB = Centro::factory()->create(['paroquia_id' => $paroquiaB->id]);

        Movimento::factory()->create(['paroquia_id' => $paroquiaA->id, 'centro_id' => $centroA->id]);
        $movimentoB = Movimento::factory()->create(['paroquia_id' => $paroquiaB->id, 'centro_id' => $centroB->id]);

        $this->actingAs($this->tesoureiroDe($paroquiaA));

        $ids = Movimento::pluck('id');

        $this->assertCount(1, $ids);
        $this->assertNotContains($movimentoB->id, $ids);
    }

    public function test_centro_criado_por_tesoureiro_paroquial_ignora_paroquia_id_adulterado(): void
    {
        $paroquiaPropria = Paroquia::factory()->create();
        $paroquiaAlheia = Paroquia::factory()->create();

        $this->actingAs($this->tesoureiroDe($paroquiaPropria));

        // Simula o payload que um cliente adulterado enviaria: paroquia_id
        // de outra paroquia, apesar do campo estar escondido no formulario.
        $centro = Centro::create([
            'paroquia_id' => $paroquiaAlheia->id,
            'nome' => 'Centro Teste',
            'status' => 'ativo',
        ]);

        $this->assertSame($paroquiaPropria->id, $centro->fresh()->paroquia_id);
    }

    public function test_fiel_criado_por_tesoureiro_paroquial_ignora_paroquia_id_adulterado(): void
    {
        $paroquiaPropria = Paroquia::factory()->create();
        $paroquiaAlheia = Paroquia::factory()->create();

        $this->actingAs($this->tesoureiroDe($paroquiaPropria));

        $fiel = Fiel::create([
            'paroquia_id' => $paroquiaAlheia->id,
            'nome' => 'Fiel Teste',
            'codigo_dizimista' => 'DZ-TESTE-1',
            'status' => 'ativo',
        ]);

        $this->assertSame($paroquiaPropria->id, $fiel->fresh()->paroquia_id);
    }

    public function test_categoria_despesa_criada_por_tesoureiro_paroquial_ignora_paroquia_id_adulterado(): void
    {
        $paroquiaPropria = Paroquia::factory()->create();
        $paroquiaAlheia = Paroquia::factory()->create();

        $this->actingAs($this->tesoureiroDe($paroquiaPropria));

        $categoria = CategoriaDespesa::create([
            'paroquia_id' => $paroquiaAlheia->id,
            'nome' => 'Categoria Teste',
            'status' => 'ativo',
        ]);

        $this->assertSame($paroquiaPropria->id, $categoria->fresh()->paroquia_id);
    }

    public function test_admin_geral_pode_escolher_livremente_a_paroquia(): void
    {
        $paroquiaEscolhida = Paroquia::factory()->create();

        $admin = User::factory()->create();
        $admin->assignRole('admin_geral');

        $this->actingAs($admin);

        $centro = Centro::create([
            'paroquia_id' => $paroquiaEscolhida->id,
            'nome' => 'Centro Admin',
            'status' => 'ativo',
        ]);

        $this->assertSame($paroquiaEscolhida->id, $centro->fresh()->paroquia_id);
    }
}
