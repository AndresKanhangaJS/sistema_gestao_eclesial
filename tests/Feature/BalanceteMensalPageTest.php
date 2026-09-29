<?php

namespace Tests\Feature;

use App\Enums\TipoMovimento;
use App\Filament\Pages\Relatorios\BalanceteMensal;
use App\Models\Centro;
use App\Models\MetodoPagamento;
use App\Models\Movimento;
use App\Models\Paroquia;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fumaça da pagina/accao "Fechar Balancete" (Modulo 7, pedido do cliente
 * 2026-09-29) — cobre o que os testes unitarios do BalanceteService nao
 * alcancam: a pagina Livewire renderiza sem erro e a accao de fecho, exposta
 * via UI, produz o mesmo resultado que App\Services\BalanceteService::fechar().
 */
class BalanceteMensalPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_pagina_renderiza_e_mostra_o_total_de_receitas(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        Movimento::factory()->aprovado()->create([
            'paroquia_id' => $paroquia->id,
            'centro_id' => $centro->id,
            'metodo_pagamento_id' => MetodoPagamento::factory()->create()->id,
            'tipo' => TipoMovimento::Ofertorio,
            'valor' => 250,
            'data_movimento' => now()->startOfMonth(),
        ]);

        $administrador = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $administrador->assignRole('administrador_paroquial');
        $this->actingAs($administrador);

        $response = $this->get(BalanceteMensal::getUrl());

        $response->assertOk();
        $response->assertSee('250,00');
    }

    public function test_fechar_action_gera_movimentos_de_repasse(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $metodoPagamento = MetodoPagamento::factory()->create();

        Movimento::factory()->aprovado()->create([
            'paroquia_id' => $paroquia->id,
            'centro_id' => $centro->id,
            'metodo_pagamento_id' => $metodoPagamento->id,
            'tipo' => TipoMovimento::Dizimo,
            'valor' => 1000,
            'ano_competencia' => now()->year,
            'mes_competencia' => now()->month,
            'data_movimento' => now(),
        ]);

        $administrador = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $administrador->assignRole('administrador_paroquial');
        $this->actingAs($administrador);

        Livewire::test(BalanceteMensal::class)
            ->callAction('fechar', data: [
                'metodo_pagamento_id' => $metodoPagamento->id,
                'banco_id' => null,
                'data_movimento' => now()->format('Y-m-d'),
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('balancetes_mensais', [
            'paroquia_id' => $paroquia->id,
            'centro_id' => $centro->id,
        ]);

        // 1 dizimo lancado no setup + 2 gerados pelo fecho (Bispado, Dizimo)
        $this->assertDatabaseCount('movimentos', 3);
    }

    /**
     * Regressao: `<x-filament::button tag="a" href="{{ route(...) }}">`
     * escapa o "&" da query string duas vezes (uma no `{{ }}`, outra dentro
     * do proprio componente Filament via `generate_href_html()`), gerando
     * `&amp;amp;` no HTML — o browser decifra isso como literal "&amp;",
     * corrompendo os nomes dos parametros seguintes (`mes` chega ao servidor
     * como `amp;mes`) e fazendo com que `centro_id` nunca chegue ao pedido de
     * exportacao. A correcao e usar `:href="route(...)"` (sem `{{ }}`), que
     * ja estava a afectar silenciosamente todos os outros relatorios com
     * mais de 1 parametro — so ficou visivel aqui porque esta rota, ao
     * contrario das outras, rejeita explicitamente um centro ausente.
     */
    public function test_link_de_exportacao_nao_escapa_o_e_comercial_duas_vezes(): void
    {
        $paroquia = Paroquia::factory()->create();
        Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $administrador = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $administrador->assignRole('administrador_paroquial');
        $this->actingAs($administrador);

        $html = $this->get(BalanceteMensal::getUrl())->getContent();

        $this->assertStringNotContainsString('amp;amp;', $html);

        preg_match('/href="([^"]*balancete-mensal\/excel[^"]*)"/', $html, $m);
        $href = html_entity_decode($m[1] ?? '');

        $this->assertStringContainsString('&centro_id=', $href);
    }
}
