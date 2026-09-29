<?php

namespace Tests\Unit;

use App\Enums\StatusConciliacao;
use App\Enums\TipoMovimento;
use App\Models\BalanceteMensal;
use App\Models\CategoriaDespesa;
use App\Models\CategoriaReceita;
use App\Models\Centro;
use App\Models\MetodoPagamento;
use App\Models\Movimento;
use App\Models\Paroquia;
use App\Models\User;
use App\Services\BalanceteService;
use App\Support\LinhasBalancete;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Balancete no modelo oficial da Arquidiocese (Modulo 7, pedido do cliente
 * 2026-09-29): linhas fixas taguedas por CategoriaReceita/CategoriaDespesa,
 * residuo para categorias sem tag, saldo transitado do mes anterior, e os
 * 2 contributos calculados (Bispado 20%, Dizimo configuravel por paroquia).
 */
class BalanceteServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calcula_receitas_e_despesas_por_linha_fixa_e_residuo(): void
    {
        $centro = Centro::factory()->create();
        $paroquiaId = $centro->paroquia_id;
        $ano = 2026;
        $mes = 3;
        $metodoPagamento = MetodoPagamento::factory()->create();

        Movimento::factory()->aprovado()->create([
            'paroquia_id' => $paroquiaId,
            'centro_id' => $centro->id,
            'metodo_pagamento_id' => $metodoPagamento->id,
            'tipo' => TipoMovimento::Ofertorio,
            'valor' => 200,
            'data_movimento' => "{$ano}-{$mes}-05",
        ]);

        Movimento::factory()->aprovado()->create([
            'paroquia_id' => $paroquiaId,
            'centro_id' => $centro->id,
            'metodo_pagamento_id' => $metodoPagamento->id,
            'tipo' => TipoMovimento::Dizimo,
            'valor' => 1000,
            'ano_competencia' => $ano,
            'mes_competencia' => $mes,
            'data_movimento' => "{$ano}-{$mes}-10",
        ]);

        $categoriaSubsidios = CategoriaReceita::create([
            'paroquia_id' => $paroquiaId,
            'nome' => 'Subsídios',
            'linha_balancete' => '1.1.1',
            'status' => 'ativo',
        ]);
        $categoriaDiversa = CategoriaReceita::create([
            'paroquia_id' => $paroquiaId,
            'nome' => 'Aluguer de Salão',
            'status' => 'ativo',
        ]);

        Movimento::factory()->aprovado()->create([
            'paroquia_id' => $paroquiaId,
            'centro_id' => $centro->id,
            'metodo_pagamento_id' => $metodoPagamento->id,
            'tipo' => TipoMovimento::Campanha,
            'categoria_receita_id' => $categoriaSubsidios->id,
            'valor' => 300,
            'data_movimento' => "{$ano}-{$mes}-12",
        ]);
        Movimento::factory()->aprovado()->create([
            'paroquia_id' => $paroquiaId,
            'centro_id' => $centro->id,
            'metodo_pagamento_id' => $metodoPagamento->id,
            'tipo' => TipoMovimento::Campanha,
            'categoria_receita_id' => $categoriaDiversa->id,
            'valor' => 150,
            'data_movimento' => "{$ano}-{$mes}-14",
        ]);

        $categoriaEnergia = CategoriaDespesa::create([
            'paroquia_id' => $paroquiaId,
            'nome' => 'Energia',
            'linha_balancete' => '2.2.4',
            'status' => 'ativo',
        ]);
        $categoriaOutra = CategoriaDespesa::create([
            'paroquia_id' => $paroquiaId,
            'nome' => 'Manutenção de Viatura Extra',
            'status' => 'ativo',
        ]);

        Movimento::factory()->aprovado()->create([
            'paroquia_id' => $paroquiaId,
            'centro_id' => $centro->id,
            'metodo_pagamento_id' => $metodoPagamento->id,
            'tipo' => TipoMovimento::DespesaCentro,
            'categoria_despesa_id' => $categoriaEnergia->id,
            'valor' => 80,
            'data_movimento' => "{$ano}-{$mes}-15",
        ]);
        Movimento::factory()->aprovado()->create([
            'paroquia_id' => $paroquiaId,
            'centro_id' => $centro->id,
            'metodo_pagamento_id' => $metodoPagamento->id,
            'tipo' => TipoMovimento::DespesaCentro,
            'categoria_despesa_id' => $categoriaOutra->id,
            'valor' => 40,
            'data_movimento' => "{$ano}-{$mes}-16",
        ]);

        $dados = BalanceteService::calcular($ano, $mes, $centro->id, $paroquiaId);

        $this->assertSame(300.0, $dados['receitas']['subsidios']);
        $this->assertSame(200.0, $dados['receitas']['peditorios']);
        $this->assertSame(1000.0, $dados['receitas']['congruas']);
        $this->assertCount(1, $dados['receitas']['diversas']);
        $this->assertSame('Aluguer de Salão', $dados['receitas']['diversas'][0]['nome']);
        $this->assertSame(150.0, $dados['receitas']['diversas'][0]['valor']);
        // 1.1.1 (300) + 1.1.3 peditorios (200) + 1.1.5 diversas (150) = 650
        $this->assertSame(650.0, $dados['receitas']['subtotal_ordinarias']);
        $this->assertSame(0.0, $dados['receitas']['saldo_mes_anterior']);
        $this->assertSame(650.0 + 1000.0, $dados['receitas']['total']);

        $this->assertSame(80.0, $dados['despesas']['energia']);
        $this->assertCount(1, $dados['despesas']['outras_despesas']);
        $this->assertSame('Manutenção de Viatura Extra', $dados['despesas']['outras_despesas'][0]['nome']);
        $this->assertSame(120.0, $dados['despesas']['subtotal_correntes']);

        // Bispado = 20% do total de receitas (1650) = 330
        $this->assertSame(330.0, $dados['despesas']['contributo_bispado']);
        // Dizimo = 60% (default) das Congruas (1000) = 600
        $this->assertSame(600.0, $dados['despesas']['contributo_dizimo']);
    }

    public function test_percentual_do_contributo_dizimo_e_configuravel_por_paroquia(): void
    {
        $paroquia = Paroquia::factory()->create(['percentual_contributo_dizimo' => 50]);
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $ano = 2026;
        $mes = 4;

        Movimento::factory()->aprovado()->create([
            'paroquia_id' => $paroquia->id,
            'centro_id' => $centro->id,
            'tipo' => TipoMovimento::Dizimo,
            'valor' => 2000,
            'ano_competencia' => $ano,
            'mes_competencia' => $mes,
            'data_movimento' => "{$ano}-{$mes}-10",
        ]);

        $dados = BalanceteService::calcular($ano, $mes, $centro->id, $paroquia->id);

        $this->assertSame(1000.0, $dados['despesas']['contributo_dizimo']);
        $this->assertSame(50.0, $dados['despesas']['percentual_dizimo']);
    }

    public function test_movimentos_pendentes_nao_entram_no_balancete(): void
    {
        $centro = Centro::factory()->create();

        Movimento::factory()->create([
            'paroquia_id' => $centro->paroquia_id,
            'centro_id' => $centro->id,
            'tipo' => TipoMovimento::Ofertorio,
            'valor' => 500,
            'status_conciliacao' => StatusConciliacao::Pendente,
            'data_movimento' => '2026-05-10',
        ]);

        $dados = BalanceteService::calcular(2026, 5, $centro->id, $centro->paroquia_id);

        $this->assertSame(0.0, $dados['receitas']['peditorios']);
        $this->assertSame(0.0, $dados['receitas']['total']);
    }

    public function test_saldo_mes_anterior_vem_do_balancete_ja_fechado(): void
    {
        $centro = Centro::factory()->create();

        BalanceteMensal::factory()->create([
            'paroquia_id' => $centro->paroquia_id,
            'centro_id' => $centro->id,
            'ano' => 2026,
            'mes' => 4,
            'saldo_mes' => 750,
        ]);

        $dados = BalanceteService::calcular(2026, 5, $centro->id, $centro->paroquia_id);

        $this->assertSame(750.0, $dados['receitas']['saldo_mes_anterior']);
        $this->assertSame(750.0, $dados['receitas']['total']);
    }

    public function test_saldo_mes_anterior_atravessa_a_virada_do_ano(): void
    {
        $centro = Centro::factory()->create();

        BalanceteMensal::factory()->create([
            'paroquia_id' => $centro->paroquia_id,
            'centro_id' => $centro->id,
            'ano' => 2025,
            'mes' => 12,
            'saldo_mes' => 300,
        ]);

        $dados = BalanceteService::calcular(2026, 1, $centro->id, $centro->paroquia_id);

        $this->assertSame(300.0, $dados['receitas']['saldo_mes_anterior']);
    }

    public function test_fechar_gera_movimentos_de_repasse_e_grava_o_fecho(): void
    {
        $centro = Centro::factory()->create();
        $metodoPagamento = MetodoPagamento::factory()->create();
        $usuario = User::factory()->create();

        Movimento::factory()->aprovado()->create([
            'paroquia_id' => $centro->paroquia_id,
            'centro_id' => $centro->id,
            'tipo' => TipoMovimento::Dizimo,
            'valor' => 1000,
            'ano_competencia' => 2026,
            'mes_competencia' => 6,
            'data_movimento' => '2026-06-10',
        ]);

        $balancete = BalanceteService::fechar(
            2026,
            6,
            $centro->id,
            $usuario->id,
            $metodoPagamento->id,
            null,
            '2026-06-30',
        );

        $this->assertSame(0.0, (float) $balancete->saldo_mes_anterior);
        $this->assertNotNull($balancete->movimento_bispado_id);
        $this->assertNotNull($balancete->movimento_dizimo_id);

        $movimentoBispado = Movimento::find($balancete->movimento_bispado_id);
        $movimentoDizimo = Movimento::find($balancete->movimento_dizimo_id);

        $this->assertSame(StatusConciliacao::Aprovado, $movimentoBispado->status_conciliacao);
        $this->assertSame(LinhasBalancete::BISPADO, $movimentoBispado->categoriaDespesa->linha_balancete);
        $this->assertSame(LinhasBalancete::CONTRIBUTO_DIZIMO, $movimentoDizimo->categoriaDespesa->linha_balancete);
        $this->assertSame(600.0, (float) $movimentoDizimo->valor);
    }

    public function test_fechar_bloqueia_segundo_fecho_do_mesmo_centro_e_mes(): void
    {
        $centro = Centro::factory()->create();
        $metodoPagamento = MetodoPagamento::factory()->create();
        $usuario = User::factory()->create();

        BalanceteService::fechar(2026, 7, $centro->id, $usuario->id, $metodoPagamento->id, null, '2026-07-31');

        $this->expectException(RuntimeException::class);

        BalanceteService::fechar(2026, 7, $centro->id, $usuario->id, $metodoPagamento->id, null, '2026-07-31');
    }

    /**
     * Regressao: um Balancete ja fechado nao pode "mudar sozinho" quando a
     * paroquia edita a percentagem de contributo depois do fecho (pedido do
     * cliente, 2026-09-30) — o Movimento de repasse ja gerado (e
     * possivelmente ja transferido ao banco) e a fonte da verdade, nao a
     * percentagem actual da paroquia.
     */
    public function test_editar_a_percentagem_depois_do_fecho_nao_altera_o_balancete_ja_fechado(): void
    {
        $paroquia = Paroquia::factory()->create(['percentual_contributo_dizimo' => 60]);
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $metodoPagamento = MetodoPagamento::factory()->create();
        $usuario = User::factory()->create();

        Movimento::factory()->aprovado()->create([
            'paroquia_id' => $paroquia->id,
            'centro_id' => $centro->id,
            'metodo_pagamento_id' => $metodoPagamento->id,
            'tipo' => TipoMovimento::Dizimo,
            'valor' => 1000,
            'ano_competencia' => 2026,
            'mes_competencia' => 8,
            'data_movimento' => '2026-08-10',
        ]);

        BalanceteService::fechar(2026, 8, $centro->id, $usuario->id, $metodoPagamento->id, null, '2026-08-31');

        $dadosAntes = BalanceteService::calcular(2026, 8, $centro->id, $paroquia->id);
        $this->assertSame(600.0, $dadosAntes['despesas']['contributo_dizimo']);
        $this->assertTrue($dadosAntes['fechado']);

        // O administrador edita a percentagem da paroquia DEPOIS do fecho.
        $paroquia->update(['percentual_contributo_dizimo' => 30]);

        $dadosDepois = BalanceteService::calcular(2026, 8, $centro->id, $paroquia->id);

        $this->assertSame(600.0, $dadosDepois['despesas']['contributo_dizimo']);
        $this->assertSame(60.0, $dadosDepois['despesas']['percentual_dizimo']);

        // Um mes novo (ainda por fechar) ja usa a percentagem actual (30%).
        Movimento::factory()->aprovado()->create([
            'paroquia_id' => $paroquia->id,
            'centro_id' => $centro->id,
            'metodo_pagamento_id' => $metodoPagamento->id,
            'tipo' => TipoMovimento::Dizimo,
            'valor' => 1000,
            'ano_competencia' => 2026,
            'mes_competencia' => 9,
            'data_movimento' => '2026-09-10',
        ]);

        $dadosMesNovo = BalanceteService::calcular(2026, 9, $centro->id, $paroquia->id);

        $this->assertSame(300.0, $dadosMesNovo['despesas']['contributo_dizimo']);
        $this->assertFalse($dadosMesNovo['fechado']);
    }
}
