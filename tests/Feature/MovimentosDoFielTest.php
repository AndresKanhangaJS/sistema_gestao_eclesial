<?php

namespace Tests\Feature;

use App\Filament\Resources\FielResource\Pages\EditFiel;
use App\Filament\Resources\FielResource\RelationManagers\MovimentosRelationManager;
use App\Models\Centro;
use App\Models\Fiel;
use App\Models\Movimento;
use App\Models\Paroquia;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Extracto de movimentos do fiel (separador "Movimentos" no FielResource):
 * responde a "este dizimo de Janeiro foi pago por qual fiel". tesoureiro_centro
 * so ve os movimentos lancados no seu proprio centro, mesmo que o fiel tenha
 * historico noutro centro da mesma paroquia.
 */
class MovimentosDoFielTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function criarCenario(): array
    {
        $paroquia = Paroquia::factory()->create();
        $centroA = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $centroB = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $fiel = Fiel::factory()->create(['paroquia_id' => $paroquia->id]);
        $fiel->centros()->attach($centroA->id, ['data_inicio' => now()->subYear(), 'data_fim' => now()->subMonths(6)]);
        $fiel->centros()->attach($centroB->id, ['data_inicio' => now()->subMonths(6)]);

        $movimentoA = Movimento::factory()->dizimo()->aprovado()->create([
            'paroquia_id' => $paroquia->id,
            'centro_id' => $centroA->id,
            'fiel_id' => $fiel->id,
            'mes_competencia' => 1,
        ]);

        $movimentoB = Movimento::factory()->dizimo()->aprovado()->create([
            'paroquia_id' => $paroquia->id,
            'centro_id' => $centroB->id,
            'fiel_id' => $fiel->id,
            'mes_competencia' => 7,
        ]);

        return compact('paroquia', 'centroA', 'centroB', 'fiel', 'movimentoA', 'movimentoB');
    }

    public function test_administrador_paroquial_ve_movimentos_do_fiel_em_ambos_os_centros(): void
    {
        ['paroquia' => $paroquia, 'fiel' => $fiel, 'movimentoA' => $movimentoA, 'movimentoB' => $movimentoB] = $this->criarCenario();

        $administrador = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $administrador->assignRole('administrador_paroquial');
        $this->actingAs($administrador);

        $test = Livewire::test(MovimentosRelationManager::class, [
            'ownerRecord' => $fiel,
            'pageClass' => EditFiel::class,
        ]);

        $test->assertCanSeeTableRecords([$movimentoA, $movimentoB]);
    }

    public function test_tesoureiro_centro_so_ve_movimentos_do_seu_proprio_centro(): void
    {
        ['paroquia' => $paroquia, 'centroB' => $centroB, 'fiel' => $fiel, 'movimentoA' => $movimentoA, 'movimentoB' => $movimentoB] = $this->criarCenario();

        $tesoureiro = User::factory()->create(['paroquia_id' => $paroquia->id, 'centro_id' => $centroB->id]);
        $tesoureiro->assignRole('tesoureiro_centro');
        $this->actingAs($tesoureiro);

        $test = Livewire::test(MovimentosRelationManager::class, [
            'ownerRecord' => $fiel,
            'pageClass' => EditFiel::class,
        ]);

        $test->assertCanSeeTableRecords([$movimentoB]);
        $test->assertCanNotSeeTableRecords([$movimentoA]);
    }
}
