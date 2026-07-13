<?php

namespace Tests\Feature;

use App\Enums\StatusConciliacao;
use App\Enums\TipoMovimento;
use App\Filament\Pages\Relatorios\DemonstrativoArrecadacao;
use App\Models\Centro;
use App\Models\Movimento;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regressao: os graficos (ArrecadacaoBarChart/PieChart) eram montados via
 * <x-filament-widgets::widgets> com uma key Livewire fixa — ao mudar o ano no
 * seletor da pagina, a tabela por baixo actualizava (computed na propria
 * pagina) mas os graficos ficavam presos aos dados do ano do primeiro
 * carregamento, porque uma key fixa impede o Livewire de os remontar.
 */
class DemonstrativoArrecadacaoGraficosTest extends TestCase
{
    use RefreshDatabase;

    public function test_graficos_reflectem_o_ano_seleccionado_no_filtro(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin_geral');
        $this->actingAs($admin);

        $centro = Centro::factory()->create();
        $anoAtual = (int) now()->year;
        $anoAnterior = $anoAtual - 1;

        Movimento::factory()->aprovado()->create([
            'paroquia_id' => $centro->paroquia_id,
            'centro_id' => $centro->id,
            'tipo' => TipoMovimento::Dizimo,
            'valor' => 13579,
            'data_movimento' => "{$anoAtual}-03-10",
            'status_conciliacao' => StatusConciliacao::Aprovado,
        ]);
        Movimento::factory()->aprovado()->create([
            'paroquia_id' => $centro->paroquia_id,
            'centro_id' => $centro->id,
            'tipo' => TipoMovimento::Dizimo,
            'valor' => 24680,
            'data_movimento' => "{$anoAnterior}-03-10",
            'status_conciliacao' => StatusConciliacao::Aprovado,
        ]);

        $test = Livewire::test(DemonstrativoArrecadacao::class);

        $test->assertSee('13579');
        $test->assertDontSee('24680');

        $test->set('ano', $anoAnterior);

        $test->assertSee('24680');
        $test->assertDontSee('13579');
    }
}
