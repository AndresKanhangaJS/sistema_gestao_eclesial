<?php

namespace Tests\Unit;

use App\Enums\StatusConciliacao;
use App\Enums\TipoMovimento;
use App\Models\Centro;
use App\Models\Movimento;
use App\Services\BalancoReceitasDespesasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Balanco de Receitas vs Despesas: Receitas - Despesas = Saldo, so
 * considerando movimentos aprovados (Modulo 7, CLAUDE.md).
 */
class BalancoReceitasDespesasServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calcula_saldo_como_receitas_menos_despesas(): void
    {
        $centro = Centro::factory()->create();
        $ano = 2026;

        Movimento::factory()->aprovado()->create([
            'paroquia_id' => $centro->paroquia_id,
            'centro_id' => $centro->id,
            'tipo' => TipoMovimento::Dizimo,
            'valor' => 1000,
            'data_movimento' => "{$ano}-01-15",
        ]);
        Movimento::factory()->aprovado()->create([
            'paroquia_id' => $centro->paroquia_id,
            'centro_id' => $centro->id,
            'tipo' => TipoMovimento::Ofertorio,
            'valor' => 500,
            'data_movimento' => "{$ano}-01-20",
        ]);
        Movimento::factory()->aprovado()->create([
            'paroquia_id' => $centro->paroquia_id,
            'centro_id' => $centro->id,
            'tipo' => TipoMovimento::DespesaCentro,
            'valor' => 300,
            'data_movimento' => "{$ano}-01-25",
        ]);

        $dados = BalancoReceitasDespesasService::calcular($ano, $centro->id);

        $this->assertSame(1500.0, $dados['total_receitas']);
        $this->assertSame(300.0, $dados['total_despesas']);
        $this->assertSame(1200.0, $dados['saldo']);
        $this->assertSame(1200.0, $dados['por_mes'][1]['saldo']);
    }

    public function test_movimentos_pendentes_nao_entram_no_balanco(): void
    {
        $centro = Centro::factory()->create();
        $ano = 2026;

        Movimento::factory()->create([
            'paroquia_id' => $centro->paroquia_id,
            'centro_id' => $centro->id,
            'tipo' => TipoMovimento::Dizimo,
            'valor' => 1000,
            'status_conciliacao' => StatusConciliacao::Pendente,
            'data_movimento' => "{$ano}-02-10",
        ]);

        $dados = BalancoReceitasDespesasService::calcular($ano, $centro->id);

        $this->assertSame(0.0, $dados['total_receitas']);
        $this->assertSame(0.0, $dados['saldo']);
    }
}
