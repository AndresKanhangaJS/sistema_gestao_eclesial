<?php

namespace Tests\Unit;

use App\Models\Centro;
use App\Models\Fiel;
use App\Models\MetodoPagamento;
use App\Models\Movimento;
use App\Services\FieisPorSituacaoService;
use App\Services\MatrizDizimosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regressao: um fiel com 7 a 11 dizimos pagos no ano nao caia em nenhum dos
 * 3 segmentos definidos (Assiduo 12/12, Irregular 1-6, Inactivo 0) e ficava
 * sem classificacao (segmento null) — os dois servicos de segmentacao agora
 * cobrem esse intervalo com "Regular".
 */
class SegmentacaoAssiduidadeTest extends TestCase
{
    use RefreshDatabase;

    private ?MetodoPagamento $metodo = null;

    private function pagarMeses(Centro $centro, Fiel $fiel, array $meses, int $ano): void
    {
        $metodo = $this->metodo ??= MetodoPagamento::factory()->create();

        foreach ($meses as $mes) {
            Movimento::factory()->dizimo()->aprovado()->create([
                'paroquia_id' => $centro->paroquia_id,
                'centro_id' => $centro->id,
                'fiel_id' => $fiel->id,
                'metodo_pagamento_id' => $metodo->id,
                'ano_competencia' => $ano,
                'mes_competencia' => $mes,
            ]);
        }
    }

    public function test_matriz_dizimos_classifica_7_a_11_pagamentos_como_regular(): void
    {
        $centro = Centro::factory()->create();
        $fiel = Fiel::factory()->create(['paroquia_id' => $centro->paroquia_id]);
        $fiel->centros()->attach($centro->id, ['data_inicio' => now()->startOfYear()]);

        $ano = (int) now()->year;
        $this->pagarMeses($centro, $fiel, range(1, 8), $ano);

        $linha = collect(MatrizDizimosService::calcular([$centro->id], $ano))->firstWhere('fiel.id', $fiel->id);

        $this->assertSame(8, $linha['total_pagos']);
        $this->assertSame('Regular', $linha['segmento']);
    }

    public function test_fieis_por_situacao_classifica_7_a_11_pagamentos_como_regular(): void
    {
        $centro = Centro::factory()->create();
        $fiel = Fiel::factory()->create(['paroquia_id' => $centro->paroquia_id]);
        $fiel->centros()->attach($centro->id, ['data_inicio' => now()->startOfYear()]);

        $ano = (int) now()->year;
        $this->pagarMeses($centro, $fiel, range(1, 11), $ano);

        $linha = collect(FieisPorSituacaoService::calcular($ano))->firstWhere('fiel.id', $fiel->id);

        $this->assertSame(11, $linha['total_pagos']);
        $this->assertSame('Regular', $linha['segmento']);
    }

    public function test_segmentos_cobrem_todo_o_intervalo_0_a_12(): void
    {
        $centro = Centro::factory()->create();
        $ano = (int) now()->year;

        $casos = [0 => 'Inactivo', 3 => 'Irregular', 6 => 'Irregular', 7 => 'Regular', 11 => 'Regular', 12 => 'Assíduo'];

        foreach ($casos as $totalPagos => $segmentoEsperado) {
            $fiel = Fiel::factory()->create(['paroquia_id' => $centro->paroquia_id]);
            $fiel->centros()->attach($centro->id, ['data_inicio' => now()->startOfYear()]);
            $this->pagarMeses($centro, $fiel, $totalPagos > 0 ? range(1, $totalPagos) : [], $ano);

            $linha = collect(MatrizDizimosService::calcular([$centro->id], $ano))->firstWhere('fiel.id', $fiel->id);

            $this->assertSame($totalPagos, $linha['total_pagos'], "total_pagos={$totalPagos}");
            $this->assertSame($segmentoEsperado, $linha['segmento'], "total_pagos={$totalPagos} deveria ser {$segmentoEsperado}");
        }
    }
}
