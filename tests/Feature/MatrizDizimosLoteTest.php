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
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Regressao do bug corrigido em MatrizDizimos::processarLancamentoLote():
 * $centroId e propriedade publica Livewire (adulteravel no cliente) e era
 * usada directamente para escrever movimentos financeiros, sem confirmar que
 * pertencia ao utilizador autenticado.
 */
class MatrizDizimosLoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_tesoureiro_paroquial_nao_consegue_lancar_dizimo_num_centro_de_outra_paroquia(): void
    {
        $paroquiaPropria = Paroquia::factory()->create();
        $centroProprio = Centro::factory()->create(['paroquia_id' => $paroquiaPropria->id]);

        $paroquiaAlheia = Paroquia::factory()->create();
        $centroAlheio = Centro::factory()->create(['paroquia_id' => $paroquiaAlheia->id]);
        $fielAlheio = Fiel::factory()->create(['paroquia_id' => $paroquiaAlheia->id]);
        $fielAlheio->centros()->attach($centroAlheio->id, ['data_inicio' => now()->subYear()]);

        $tesoureiro = User::factory()->create(['paroquia_id' => $paroquiaPropria->id]);
        $tesoureiro->assignRole('tesoureiro_paroquial');
        $this->actingAs($tesoureiro);

        $metodo = MetodoPagamento::factory()->create();

        $page = new MatrizDizimos;
        // Simula adulteracao do cliente: o dropdown so oferece $centroProprio,
        // mas a propriedade publica e escrita directamente aqui, como um
        // $wire.set('centroId', ...) faria no browser.
        $page->centroId = $centroAlheio->id;
        $page->ano = (int) now()->year;

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Centro inválido.');

        $page->processarLancamentoLote($fielAlheio->id, [
            'meses' => [1],
            'valor' => 1000,
            'metodo_pagamento_id' => $metodo->id,
            'banco_id' => null,
            'data_movimento' => now()->toDateString(),
        ]);

        $this->assertSame(0, Movimento::count());
    }

    public function test_tesoureiro_paroquial_consegue_lancar_dizimo_no_seu_proprio_centro(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $fiel = Fiel::factory()->create(['paroquia_id' => $paroquia->id]);
        $fiel->centros()->attach($centro->id, ['data_inicio' => now()->subYear()]);

        $tesoureiro = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $tesoureiro->assignRole('tesoureiro_paroquial');
        $this->actingAs($tesoureiro);

        $metodo = MetodoPagamento::factory()->create();

        $page = new MatrizDizimos;
        $page->centroId = $centro->id;
        $page->ano = (int) now()->year;

        $page->processarLancamentoLote($fiel->id, [
            'meses' => [1, 2],
            'valor' => 1000,
            'metodo_pagamento_id' => $metodo->id,
            'banco_id' => null,
            'data_movimento' => now()->toDateString(),
        ]);

        $this->assertSame(2, Movimento::count());
        $this->assertSame($paroquia->id, Movimento::first()->paroquia_id);
    }
}
