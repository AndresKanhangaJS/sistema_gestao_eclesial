<?php

namespace Tests\Feature;

use App\Filament\Resources\MovimentoResource\Pages\CreateMovimento;
use App\Models\Centro;
use App\Models\Fiel;
use App\Models\Paroquia;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Bug reportado pelo utilizador: como administrador_paroquial, ao lançar um
 * Movimento, o Select de Fiel mostrava sempre todos os fiéis da paróquia,
 * mesmo depois de escolher um Centro — devia mostrar só os fiéis activos
 * vinculados a esse centro (mesmo critério de EstatisticasGeraisWidget).
 */
class MovimentoFielPorCentroTest extends TestCase
{
    use RefreshDatabase;

    public function test_select_de_fiel_so_mostra_fieis_activos_do_centro_escolhido(): void
    {
        $this->seed(RoleSeeder::class);

        $paroquia = Paroquia::factory()->create();
        $admin = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $admin->assignRole('administrador_paroquial');
        $this->actingAs($admin);

        $centroA = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $centroB = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $fielCentroA = Fiel::factory()->create(['paroquia_id' => $paroquia->id, 'status' => 'ativo']);
        $fielCentroA->centros()->attach($centroA->id, ['data_inicio' => now()->subYear()]);

        $fielCentroB = Fiel::factory()->create(['paroquia_id' => $paroquia->id, 'status' => 'ativo']);
        $fielCentroB->centros()->attach($centroB->id, ['data_inicio' => now()->subYear()]);

        $fielInativoCentroA = Fiel::factory()->create(['paroquia_id' => $paroquia->id, 'status' => 'inativo']);
        $fielInativoCentroA->centros()->attach($centroA->id, ['data_inicio' => now()->subYear()]);

        $fielTransferidoDeA = Fiel::factory()->create(['paroquia_id' => $paroquia->id, 'status' => 'ativo']);
        $fielTransferidoDeA->centros()->attach($centroA->id, [
            'data_inicio' => now()->subYears(2),
            'data_fim' => now()->subYear(),
        ]);

        $opcoes = null;

        Livewire::test(CreateMovimento::class)
            ->fillForm([
                'natureza' => 'receita',
                'categoria_movimento' => 'dizimo',
                'centro_id' => $centroA->id,
            ])
            ->assertFormFieldExists('fiel_id', function (Select $field) use (&$opcoes) {
                $opcoes = $field->getOptions();

                return true;
            });

        $this->assertArrayHasKey($fielCentroA->id, $opcoes);
        $this->assertArrayNotHasKey($fielCentroB->id, $opcoes);
        $this->assertArrayNotHasKey($fielInativoCentroA->id, $opcoes);
        $this->assertArrayNotHasKey($fielTransferidoDeA->id, $opcoes);
    }
}
