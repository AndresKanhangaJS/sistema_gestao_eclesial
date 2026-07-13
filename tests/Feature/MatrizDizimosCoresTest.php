<?php

namespace Tests\Feature;

use App\Filament\Pages\MatrizDizimos;
use App\Models\Centro;
use App\Models\Fiel;
use App\Models\Movimento;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regressao: os pontos de "Pago" e "Em aberto" na Matriz de Dizimos usavam
 * classes Tailwind (bg-success-500, bg-warning-400, depois bg-green-500,
 * bg-amber-400) que nunca existiram no CSS compilado do Filament neste
 * projecto (sem tema Vite proprio, so classes bg-gray, bg-primary e
 * bg-custom vêm compiladas) — os circulos ficavam invisiveis, so o
 * cinzento ("nao vinculado") aparecia. Passou a usar as variaveis CSS que
 * o Filament ja define em cada pagina.
 */
class MatrizDizimosCoresTest extends TestCase
{
    use RefreshDatabase;

    public function test_celula_paga_usa_a_variavel_css_success_em_vez_de_uma_classe_inexistente(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin_geral');
        $this->actingAs($admin);

        $centro = Centro::factory()->create();
        $fiel = Fiel::factory()->create(['paroquia_id' => $centro->paroquia_id]);
        $fiel->centros()->attach($centro->id, ['data_inicio' => now()->startOfYear()]);

        Movimento::factory()->dizimo()->aprovado()->create([
            'paroquia_id' => $centro->paroquia_id,
            'centro_id' => $centro->id,
            'fiel_id' => $fiel->id,
            'ano_competencia' => (int) now()->year,
            'mes_competencia' => 1,
        ]);

        $test = Livewire::test(MatrizDizimos::class);
        $test->set('centroId', $centro->id);

        $html = $test->html();

        $this->assertStringContainsString('background-color: rgba(var(--success-500)', $html);
        $this->assertStringNotContainsString('bg-success-500', $html);
        $this->assertStringNotContainsString('bg-green-500', $html);
    }
}
