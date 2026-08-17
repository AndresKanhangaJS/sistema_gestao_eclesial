<?php

namespace Tests\Feature;

use App\Filament\Resources\CatequistaResource\Pages\CreateCatequista;
use App\Filament\Resources\CatequizandoResource\Pages\CreateCatequizando;
use App\Filament\Resources\InscricaoResource\Pages\CreateInscricao;
use App\Models\Catequista;
use App\Models\Centro;
use App\Models\Fiel;
use App\Models\Paroquia;
use App\Models\User;
use Database\Seeders\CatequeseSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Auditoria pedida pelo utilizador: sempre que se escolhe um Centro num
 * formulário, os campos dependentes (Fiel, Catequista, ...) devem obedecer
 * a esse filtro — mesmo bug já corrigido em MovimentoResource::fiel_id
 * (ver MovimentoFielPorCentroTest), reproduzido aqui nos Resources da
 * Catequese.
 */
class CatequeseFiltroPorCentroTest extends TestCase
{
    use RefreshDatabase;

    private Paroquia $paroquia;

    private Centro $centroA;

    private Centro $centroB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(CatequeseSeeder::class);

        $this->paroquia = Paroquia::factory()->create();
        $this->centroA = Centro::factory()->create(['paroquia_id' => $this->paroquia->id]);
        $this->centroB = Centro::factory()->create(['paroquia_id' => $this->paroquia->id]);

        $coordenador = User::factory()->create(['paroquia_id' => $this->paroquia->id]);
        $coordenador->assignRole('coordenador_catequese_paroquia');
        $this->actingAs($coordenador);
    }

    public function test_fiel_id_do_catequizando_so_mostra_fieis_activos_do_centro_escolhido(): void
    {
        $fielCentroA = Fiel::factory()->create(['paroquia_id' => $this->paroquia->id, 'status' => 'ativo']);
        $fielCentroA->centros()->attach($this->centroA->id, ['data_inicio' => now()->subYear()]);

        $fielCentroB = Fiel::factory()->create(['paroquia_id' => $this->paroquia->id, 'status' => 'ativo']);
        $fielCentroB->centros()->attach($this->centroB->id, ['data_inicio' => now()->subYear()]);

        $opcoes = null;

        Livewire::test(CreateCatequizando::class)
            ->fillForm(['centro_id' => $this->centroA->id])
            ->assertFormFieldExists('fiel_id', function (Select $field) use (&$opcoes) {
                $opcoes = $field->getOptions();

                return true;
            });

        $this->assertArrayHasKey($fielCentroA->id, $opcoes);
        $this->assertArrayNotHasKey($fielCentroB->id, $opcoes);
    }

    public function test_fiel_id_do_catequista_so_mostra_fieis_activos_do_centro_escolhido(): void
    {
        $fielCentroA = Fiel::factory()->create(['paroquia_id' => $this->paroquia->id, 'status' => 'ativo']);
        $fielCentroA->centros()->attach($this->centroA->id, ['data_inicio' => now()->subYear()]);

        $fielCentroB = Fiel::factory()->create(['paroquia_id' => $this->paroquia->id, 'status' => 'ativo']);
        $fielCentroB->centros()->attach($this->centroB->id, ['data_inicio' => now()->subYear()]);

        $opcoes = null;

        Livewire::test(CreateCatequista::class)
            ->fillForm(['paroquia_id' => $this->paroquia->id, 'centro_id' => $this->centroA->id])
            ->assertFormFieldExists('fiel_id', function (Select $field) use (&$opcoes) {
                $opcoes = $field->getOptions();

                return true;
            });

        $this->assertArrayHasKey($fielCentroA->id, $opcoes);
        $this->assertArrayNotHasKey($fielCentroB->id, $opcoes);
    }

    public function test_catequista_id_da_inscricao_so_mostra_catequistas_do_centro_escolhido(): void
    {
        $catequistaCentroA = Catequista::create([
            'paroquia_id' => $this->paroquia->id,
            'centro_id' => $this->centroA->id,
            'nome_completo' => 'Catequista do Centro A',
            'ativo' => true,
        ]);

        $catequistaCentroB = Catequista::create([
            'paroquia_id' => $this->paroquia->id,
            'centro_id' => $this->centroB->id,
            'nome_completo' => 'Catequista do Centro B',
            'ativo' => true,
        ]);

        $opcoes = null;

        Livewire::test(CreateInscricao::class)
            ->fillForm(['centro_id' => $this->centroA->id])
            ->assertFormFieldExists('catequista_id', function (Select $field) use (&$opcoes) {
                $opcoes = $field->getOptions();

                return true;
            });

        $this->assertArrayHasKey($catequistaCentroA->id, $opcoes);
        $this->assertArrayNotHasKey($catequistaCentroB->id, $opcoes);
    }
}
