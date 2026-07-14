<?php

namespace Tests\Feature;

use App\Filament\Pages\MatrizDizimos;
use App\Models\Centro;
use App\Models\Fiel;
use App\Models\MetodoPagamento;
use App\Models\Movimento;
use App\Models\Paroquia;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regressao: a Matriz de Dizimos so mostrava um centro de cada vez para
 * qualquer papel — um administrador_paroquial via exactamente o mesmo tipo
 * de vista (um centro isolado, escolhido por defeito por ordem alfabetica)
 * que um tesoureiro_centro, em vez de ver todos os centros da sua paroquia
 * de uma vez. Cobre tambem o novo filtro por nome do fiel.
 */
class MatrizDizimosFiltrosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function criarFielComDizimoPago(Centro $centro, string $nome): Fiel
    {
        $fiel = Fiel::factory()->create(['paroquia_id' => $centro->paroquia_id, 'nome' => $nome]);
        $fiel->centros()->attach($centro->id, ['data_inicio' => now()->startOfYear()]);

        Movimento::factory()->dizimo()->aprovado()->create([
            'paroquia_id' => $centro->paroquia_id,
            'centro_id' => $centro->id,
            'fiel_id' => $fiel->id,
            'metodo_pagamento_id' => MetodoPagamento::factory()->create()->id,
            'ano_competencia' => (int) now()->year,
            'mes_competencia' => 1,
        ]);

        return $fiel;
    }

    public function test_administrador_paroquial_ve_por_defeito_fieis_de_todos_os_centros_da_paroquia(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centroA = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $centroB = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $fielA = $this->criarFielComDizimoPago($centroA, 'Fiel do Centro A');
        $fielB = $this->criarFielComDizimoPago($centroB, 'Fiel do Centro B');

        $administrador = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $administrador->assignRole('administrador_paroquial');
        $this->actingAs($administrador);

        $page = new MatrizDizimos;
        $page->mount();

        $this->assertNull($page->centroId);

        $nomes = collect($page->matriz())->pluck('fiel.nome')->all();

        $this->assertContains($fielA->nome, $nomes);
        $this->assertContains($fielB->nome, $nomes);
    }

    public function test_tesoureiro_centro_fica_sempre_preso_ao_seu_centro_sem_selector(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centroProprio = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $centroAlheio = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $fielProprio = $this->criarFielComDizimoPago($centroProprio, 'Fiel do Meu Centro');
        $fielAlheio = $this->criarFielComDizimoPago($centroAlheio, 'Fiel do Outro Centro');

        $tesoureiro = User::factory()->create(['paroquia_id' => $paroquia->id, 'centro_id' => $centroProprio->id]);
        $tesoureiro->assignRole('tesoureiro_centro');
        $this->actingAs($tesoureiro);

        $page = new MatrizDizimos;
        $page->mount();

        $this->assertSame($centroProprio->id, $page->centroId);
        $this->assertFalse($page->mostrarFiltroCentro());

        $nomes = collect($page->matriz())->pluck('fiel.nome')->all();

        $this->assertContains($fielProprio->nome, $nomes);
        $this->assertNotContains($fielAlheio->nome, $nomes);
    }

    public function test_tesoureiro_centro_nao_consegue_ver_outro_centro_adulterando_centroid(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centroProprio = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $centroAlheio = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $fielAlheio = $this->criarFielComDizimoPago($centroAlheio, 'Fiel do Outro Centro');

        $tesoureiro = User::factory()->create(['paroquia_id' => $paroquia->id, 'centro_id' => $centroProprio->id]);
        $tesoureiro->assignRole('tesoureiro_centro');
        $this->actingAs($tesoureiro);

        $page = new MatrizDizimos;
        $page->mount();
        // Simula wire:set('centroId', ...) adulterado no cliente.
        $page->centroId = $centroAlheio->id;

        $nomes = collect($page->matriz())->pluck('fiel.nome')->all();

        $this->assertNotContains($fielAlheio->nome, $nomes);
    }

    public function test_filtro_por_nome_restringe_a_matriz(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $fielMaria = $this->criarFielComDizimoPago($centro, 'Maria João');
        $fielPedro = $this->criarFielComDizimoPago($centro, 'Pedro António');

        $admin = User::factory()->create();
        $admin->assignRole('admin_geral');
        $this->actingAs($admin);

        $test = Livewire::test(MatrizDizimos::class);
        $test->set('nomeFiel', 'maria');

        $nomes = collect($test->instance()->matriz())->pluck('fiel.nome')->all();

        $this->assertContains($fielMaria->nome, $nomes);
        $this->assertNotContains($fielPedro->nome, $nomes);
    }
}
