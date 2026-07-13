<?php

namespace Tests\Feature;

use App\Enums\TipoMovimento;
use App\Models\Centro;
use App\Models\Fiel;
use App\Models\MetodoPagamento;
use App\Models\Movimento;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Constraint critica do CLAUDE.md: (fiel_id, ano_competencia, mes_competencia)
 * WHERE tipo = 'dizimo' — nunca pode haver dois dizimos do mesmo fiel no
 * mesmo mes/ano.
 */
class DizimoUniqueKeyTest extends TestCase
{
    use RefreshDatabase;

    private function criarMovimentoBase(): array
    {
        $centro = Centro::factory()->create();
        $fiel = Fiel::factory()->create(['paroquia_id' => $centro->paroquia_id]);

        return [
            'paroquia_id' => $centro->paroquia_id,
            'centro_id' => $centro->id,
            'usuario_id' => User::factory()->create()->id,
            'fiel_id' => $fiel->id,
            'metodo_pagamento_id' => MetodoPagamento::factory()->create()->id,
            'tipo' => TipoMovimento::Dizimo,
            'valor' => 5000,
            'ano_competencia' => 2026,
            'mes_competencia' => 3,
            'data_movimento' => now(),
        ];
    }

    public function test_nao_permite_dois_dizimos_do_mesmo_fiel_no_mesmo_mes_e_ano(): void
    {
        $dados = $this->criarMovimentoBase();

        Movimento::create($dados);

        $this->expectException(UniqueConstraintViolationException::class);

        Movimento::create($dados);
    }

    public function test_permite_dizimos_do_mesmo_fiel_em_meses_diferentes(): void
    {
        $dados = $this->criarMovimentoBase();

        Movimento::create($dados);
        $segundo = Movimento::create([...$dados, 'mes_competencia' => 4]);

        $this->assertNotNull($segundo->id);
        $this->assertSame(2, Movimento::count());
    }

    public function test_permite_o_mesmo_mes_e_ano_para_tipos_diferentes_de_dizimo(): void
    {
        $dados = $this->criarMovimentoBase();

        Movimento::create($dados);
        $ofertorio = Movimento::create([
            ...$dados,
            'tipo' => TipoMovimento::Ofertorio,
        ]);

        $this->assertNotNull($ofertorio->id);
        $this->assertSame(2, Movimento::count());
    }
}
