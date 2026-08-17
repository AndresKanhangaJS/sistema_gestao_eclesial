<?php

namespace Tests\Feature;

use App\Enums\TipoMovimento;
use App\Filament\Resources\MovimentoResource\Pages\CreateMovimento;
use App\Filament\Resources\MovimentoResource\Pages\EditMovimento;
use App\Models\CategoriaDespesa;
use App\Models\CategoriaReceita;
use App\Models\Centro;
use App\Models\Fiel;
use App\Models\MetodoPagamento;
use App\Models\Movimento;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cobre os campos virtuais "Natureza" (Receita/Despesa) e "Categoria" do
 * formulário de Movimento — pedido do cliente ("tudo é receita"): dízimo,
 * ofertório e as CategoriaReceita registadas aparecem juntos, ao mesmo
 * nível, quando se escolhe "Receita". Estes dois campos não existem na BD
 * (`dehydrated(false)`) — só derivam `tipo`/`categoria_receita_id` reais
 * via `afterStateUpdated`, e têm de voltar a hidratar correctamente ao
 * editar (`afterStateHydrated`), o que é fácil de partir em silêncio.
 */
class MovimentoNaturezaCategoriaTest extends TestCase
{
    use RefreshDatabase;

    private Centro $centro;

    private MetodoPagamento $metodoPagamento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin_geral');
        $this->actingAs($admin);

        $this->centro = Centro::factory()->create();
        $this->metodoPagamento = MetodoPagamento::factory()->create(['exige_comprovativo' => false]);
    }

    public function test_receita_com_categoria_registada_grava_tipo_campanha_e_categoria_receita_id(): void
    {
        $categoria = CategoriaReceita::create([
            'paroquia_id' => $this->centro->paroquia_id,
            'nome' => 'Casamentos',
            'status' => 'ativo',
        ]);

        Livewire::test(CreateMovimento::class)
            ->fillForm([
                'natureza' => 'receita',
                'categoria_movimento' => "cr_{$categoria->id}",
                'centro_id' => $this->centro->id,
                'valor' => 15000,
                'data_movimento' => now()->toDateString(),
                'metodo_pagamento_id' => $this->metodoPagamento->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $movimento = Movimento::withoutGlobalScopes()->latest('id')->first();

        $this->assertSame(TipoMovimento::Campanha, $movimento->tipo);
        $this->assertSame($categoria->id, $movimento->categoria_receita_id);
    }

    public function test_receita_dizimo_grava_tipo_dizimo_sem_categoria_receita(): void
    {
        $fiel = Fiel::factory()->create(['paroquia_id' => $this->centro->paroquia_id]);

        Livewire::test(CreateMovimento::class)
            ->fillForm([
                'natureza' => 'receita',
                'categoria_movimento' => 'dizimo',
                'centro_id' => $this->centro->id,
                'fiel_id' => $fiel->id,
                'valor' => 5000,
                'data_movimento' => now()->toDateString(),
                'ano_competencia' => now()->year,
                'mes_competencia' => 3,
                'metodo_pagamento_id' => $this->metodoPagamento->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $movimento = Movimento::withoutGlobalScopes()->latest('id')->first();

        $this->assertSame(TipoMovimento::Dizimo, $movimento->tipo);
        $this->assertNull($movimento->categoria_receita_id);
    }

    public function test_despesa_grava_tipo_despesa_centro_com_categoria_despesa(): void
    {
        $categoriaDespesa = CategoriaDespesa::create([
            'paroquia_id' => $this->centro->paroquia_id,
            'nome' => 'Manutenção',
            'status' => 'ativo',
        ]);

        Livewire::test(CreateMovimento::class)
            ->fillForm([
                'natureza' => 'despesa',
                'categoria_despesa_id' => $categoriaDespesa->id,
                'centro_id' => $this->centro->id,
                'valor' => 8000,
                'data_movimento' => now()->toDateString(),
                'metodo_pagamento_id' => $this->metodoPagamento->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $movimento = Movimento::withoutGlobalScopes()->latest('id')->first();

        $this->assertSame(TipoMovimento::DespesaCentro, $movimento->tipo);
        $this->assertSame($categoriaDespesa->id, $movimento->categoria_despesa_id);
    }

    public function test_editar_movimento_de_categoria_receita_re_hidrata_natureza_e_categoria(): void
    {
        $categoria = CategoriaReceita::create([
            'paroquia_id' => $this->centro->paroquia_id,
            'nome' => 'Baptizados',
            'status' => 'ativo',
        ]);

        $movimento = Movimento::factory()->create([
            'paroquia_id' => $this->centro->paroquia_id,
            'centro_id' => $this->centro->id,
            'tipo' => TipoMovimento::Campanha,
            'categoria_receita_id' => $categoria->id,
        ]);

        Livewire::test(EditMovimento::class, ['record' => $movimento->getRouteKey()])
            ->assertFormSet([
                'natureza' => 'receita',
                'categoria_movimento' => "cr_{$categoria->id}",
            ]);
    }

    public function test_editar_despesa_re_hidrata_natureza_despesa(): void
    {
        $movimento = Movimento::factory()->create([
            'paroquia_id' => $this->centro->paroquia_id,
            'centro_id' => $this->centro->id,
            'tipo' => TipoMovimento::DespesaCentro,
        ]);

        Livewire::test(EditMovimento::class, ['record' => $movimento->getRouteKey()])
            ->assertFormSet([
                'natureza' => 'despesa',
            ]);
    }
}
