<?php

namespace Tests\Feature;

use App\Enums\StatusConciliacao;
use App\Models\Centro;
use App\Models\Movimento;
use App\Models\User;
use App\Policies\MovimentoPolicy;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fluxo de conciliacao bancaria: so o tesoureiro_paroquial pode aprovar ou
 * rejeitar (CLAUDE.md: "tesoureiro_paroquial -> financeiro completo +
 * conciliacao bancaria"; MovimentoPolicy::aprovar/rejeitar).
 */
class ConciliacaoMovimentoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_tesoureiro_paroquial_pode_aprovar_movimento_da_sua_paroquia(): void
    {
        $centro = Centro::factory()->create();
        $movimento = Movimento::factory()->create([
            'paroquia_id' => $centro->paroquia_id,
            'centro_id' => $centro->id,
            'status_conciliacao' => StatusConciliacao::Pendente,
        ]);

        $tesoureiro = User::factory()->create(['paroquia_id' => $centro->paroquia_id]);
        $tesoureiro->assignRole('tesoureiro_paroquial');

        $this->assertTrue($tesoureiro->can('aprovar', $movimento));
    }

    public function test_tesoureiro_paroquial_nao_pode_aprovar_movimento_de_outra_paroquia(): void
    {
        $centro = Centro::factory()->create();
        $movimento = Movimento::factory()->create([
            'paroquia_id' => $centro->paroquia_id,
            'centro_id' => $centro->id,
        ]);

        $tesoureiroOutraParoquia = User::factory()->create();
        $tesoureiroOutraParoquia->assignRole('tesoureiro_paroquial');

        $this->assertFalse($tesoureiroOutraParoquia->can('aprovar', $movimento));
    }

    public function test_tesoureiro_centro_nao_pode_aprovar_nem_rejeitar_movimento(): void
    {
        $centro = Centro::factory()->create();
        $movimento = Movimento::factory()->create([
            'paroquia_id' => $centro->paroquia_id,
            'centro_id' => $centro->id,
        ]);

        $tesoureiroCentro = User::factory()->create([
            'paroquia_id' => $centro->paroquia_id,
            'centro_id' => $centro->id,
        ]);
        $tesoureiroCentro->assignRole('tesoureiro_centro');

        $this->assertFalse($tesoureiroCentro->can('aprovar', $movimento));
        $this->assertFalse($tesoureiroCentro->can('rejeitar', $movimento));
    }

    public function test_consultor_nao_pode_aprovar_movimento(): void
    {
        $movimento = Movimento::factory()->create();

        $consultor = User::factory()->create();
        $consultor->assignRole('consultor');

        $this->assertFalse($consultor->can('aprovar', $movimento));
    }

    public function test_ninguem_consegue_apagar_movimento_pela_policy(): void
    {
        $centro = Centro::factory()->create();
        $movimento = Movimento::factory()->create([
            'paroquia_id' => $centro->paroquia_id,
            'centro_id' => $centro->id,
        ]);

        $tesoureiro = User::factory()->create(['paroquia_id' => $centro->paroquia_id]);
        $tesoureiro->assignRole('tesoureiro_paroquial');

        $this->assertFalse($tesoureiro->can('delete', $movimento));

        $admin = User::factory()->create();
        $admin->assignRole('admin_geral');

        // admin_geral contorna Policies via Gate::before (AppServiceProvider),
        // mas a EditMovimento nao regista nenhuma DeleteAction — aqui
        // confirmamos apenas que a Policy em si nunca autoriza.
        $this->assertFalse((new MovimentoPolicy)->delete($admin, $movimento));
    }
}
