<?php

namespace Tests\Unit;

use App\Models\Centro;
use App\Models\Fiel;
use App\Models\MetodoPagamento;
use App\Models\Movimento;
use App\Services\MatrizDizimosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regressao: um fiel transferido a meio do ano (6 meses pagos no centro A,
 * 6 no centro B) aparecia como "Irregular" em AMBOS os centros, porque a
 * contagem de pagamentos filtrava por centro_id — nunca chegava aos 12
 * pagamentos em nenhum dos dois centros, apesar de ter pago o ano inteiro.
 */
class MatrizDizimosServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fiel_transferido_a_meio_do_ano_conta_os_12_pagamentos_em_qualquer_centro(): void
    {
        $centroA = Centro::factory()->create();
        $centroB = Centro::factory()->create(['paroquia_id' => $centroA->paroquia_id]);

        $fiel = Fiel::factory()->create(['paroquia_id' => $centroA->paroquia_id]);
        $fiel->centros()->attach($centroA->id, [
            'data_inicio' => now()->startOfYear(),
            'data_fim' => now()->startOfYear()->addMonths(6),
        ]);
        $fiel->centros()->attach($centroB->id, [
            'data_inicio' => now()->startOfYear()->addMonths(6),
        ]);

        $ano = (int) now()->year;
        $metodo = MetodoPagamento::factory()->create();

        foreach (range(1, 6) as $mes) {
            Movimento::factory()->dizimo()->aprovado()->create([
                'paroquia_id' => $centroA->paroquia_id,
                'centro_id' => $centroA->id,
                'fiel_id' => $fiel->id,
                'metodo_pagamento_id' => $metodo->id,
                'ano_competencia' => $ano,
                'mes_competencia' => $mes,
            ]);
        }

        foreach (range(7, 12) as $mes) {
            Movimento::factory()->dizimo()->aprovado()->create([
                'paroquia_id' => $centroA->paroquia_id,
                'centro_id' => $centroB->id,
                'fiel_id' => $fiel->id,
                'metodo_pagamento_id' => $metodo->id,
                'ano_competencia' => $ano,
                'mes_competencia' => $mes,
            ]);
        }

        $linhaCentroA = collect(MatrizDizimosService::calcular([$centroA->id], $ano))
            ->firstWhere('fiel.id', $fiel->id);
        $linhaCentroB = collect(MatrizDizimosService::calcular([$centroB->id], $ano))
            ->firstWhere('fiel.id', $fiel->id);

        $this->assertSame(12, $linhaCentroA['total_pagos']);
        $this->assertSame('Assíduo', $linhaCentroA['segmento']);

        $this->assertSame(12, $linhaCentroB['total_pagos']);
        $this->assertSame('Assíduo', $linhaCentroB['segmento']);
    }
}
