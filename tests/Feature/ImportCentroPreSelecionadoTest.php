<?php

namespace Tests\Feature;

use App\Filament\Resources\CatequistaResource\Pages\ListCatequistas;
use App\Filament\Resources\CatequizandoResource\Pages\ListCatequizandos;
use App\Models\Centro;
use App\Models\Paroquia;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pedido do utilizador: ao importar, quem já está preso a um centro deve
 * ver esse centro pré-seleccionado e não conseguir escolher outro; só
 * admin_geral/coordenador_catequese_paroquia (paróquia inteira) escolhem
 * livremente.
 */
class ImportCentroPreSelecionadoTest extends TestCase
{
    use RefreshDatabase;

    private Paroquia $paroquia;

    private Centro $centroProprio;

    private Centro $outroCentro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->paroquia = Paroquia::factory()->create();
        $this->centroProprio = Centro::factory()->create(['paroquia_id' => $this->paroquia->id]);
        $this->outroCentro = Centro::factory()->create(['paroquia_id' => $this->paroquia->id]);
    }

    public function test_secretario_catequese_ve_o_seu_centro_pre_seleccionado_ao_importar_catequizandos(): void
    {
        $secretario = User::factory()->create([
            'paroquia_id' => $this->paroquia->id,
            'centro_id' => $this->centroProprio->id,
        ]);
        $secretario->assignRole('secretario_catequese');
        $this->actingAs($secretario);

        Livewire::test(ListCatequizandos::class)
            ->mountAction('import')
            ->assertActionDataSet(['centro_id' => $this->centroProprio->id]);
    }

    public function test_coordenador_centro_ve_o_seu_centro_pre_seleccionado_ao_importar_catequistas(): void
    {
        $coordenador = User::factory()->create([
            'paroquia_id' => $this->paroquia->id,
            'centro_id' => $this->centroProprio->id,
        ]);
        $coordenador->assignRole('coordenador_catequese_centro');
        $this->actingAs($coordenador);

        Livewire::test(ListCatequistas::class)
            ->mountAction('import')
            ->assertActionDataSet(['centro_id' => $this->centroProprio->id]);
    }

    public function test_coordenador_paroquia_nao_tem_centro_pre_seleccionado_e_pode_escolher(): void
    {
        $coordenador = User::factory()->create([
            'paroquia_id' => $this->paroquia->id,
            'centro_id' => null,
        ]);
        $coordenador->assignRole('coordenador_catequese_paroquia');
        $this->actingAs($coordenador);

        Livewire::test(ListCatequizandos::class)
            ->mountAction('import')
            ->assertActionDataSet(['centro_id' => null])
            ->setActionData(['centro_id' => $this->outroCentro->id])
            ->assertActionDataSet(['centro_id' => $this->outroCentro->id]);
    }
}
