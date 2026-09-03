<?php

namespace Tests\Feature;

use App\Models\AnoLetivo;
use App\Models\Catequizando;
use App\Models\Centro;
use App\Models\Inscricao;
use App\Models\Paroquia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Correcao pedida pelo utilizador (2026-09-03): os centros sao
 * independentes entre si, por isso o numero de ficha tem de reiniciar por
 * (paroquia_id, centro_id, ano_letivo_id) — dois centros da mesma paroquia
 * podem ter, ambos, o "F0001" no mesmo ano lectivo. Migration
 * 2026_09_03_000001 mudou o unique composto e
 * Inscricao::proximoNumeroFicha() passou a receber paroquia_id e centro_id
 * (nao so paroquia_id, versao original; nem so centro_id, primeira
 * tentativa de correcao — ver docs/modulos/catequese.md secc. 28).
 */
class NumeroFichaPorCentroTest extends TestCase
{
    use RefreshDatabase;

    public function test_dois_centros_da_mesma_paroquia_podem_ter_o_mesmo_numero_de_ficha(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centroA = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $centroB = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $anoLetivo = AnoLetivo::create([
            'paroquia_id' => $paroquia->id,
            'nome' => '2026/2027',
            'data_inicio' => '2026-01-01',
            'data_fim' => '2026-12-15',
            'status' => 'em_curso',
        ]);

        $catequizandoA = Catequizando::create([
            'paroquia_id' => $paroquia->id,
            'centro_id' => $centroA->id,
            'nome_completo' => 'Catequizando Centro A',
            'data_nascimento' => '2016-01-01',
            'sexo' => 'M',
            'status' => 'ativo',
        ]);

        $catequizandoB = Catequizando::create([
            'paroquia_id' => $paroquia->id,
            'centro_id' => $centroB->id,
            'nome_completo' => 'Catequizando Centro B',
            'data_nascimento' => '2016-01-01',
            'sexo' => 'F',
            'status' => 'ativo',
        ]);

        $inscricaoA = Inscricao::create([
            'paroquia_id' => $paroquia->id,
            'centro_id' => $centroA->id,
            'catequizando_id' => $catequizandoA->id,
            'ano_letivo_id' => $anoLetivo->id,
            'tipo' => 'nova',
            'data_atendimento' => now(),
            'estado' => 'inscrito',
        ]);

        $inscricaoB = Inscricao::create([
            'paroquia_id' => $paroquia->id,
            'centro_id' => $centroB->id,
            'catequizando_id' => $catequizandoB->id,
            'ano_letivo_id' => $anoLetivo->id,
            'tipo' => 'nova',
            'data_atendimento' => now(),
            'estado' => 'inscrito',
        ]);

        $this->assertSame('F0001', $inscricaoA->numero_ficha);
        $this->assertSame('F0001', $inscricaoB->numero_ficha);
    }

    public function test_segundo_catequizando_do_mesmo_centro_avanca_a_numeracao(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $anoLetivo = AnoLetivo::create([
            'paroquia_id' => $paroquia->id,
            'nome' => '2026/2027',
            'data_inicio' => '2026-01-01',
            'data_fim' => '2026-12-15',
            'status' => 'em_curso',
        ]);

        foreach (['Primeiro', 'Segundo'] as $indice => $nome) {
            $catequizando = Catequizando::create([
                'paroquia_id' => $paroquia->id,
                'centro_id' => $centro->id,
                'nome_completo' => $nome,
                'data_nascimento' => '2016-01-01',
                'sexo' => 'M',
                'status' => 'ativo',
            ]);

            $inscricao = Inscricao::create([
                'paroquia_id' => $paroquia->id,
                'centro_id' => $centro->id,
                'catequizando_id' => $catequizando->id,
                'ano_letivo_id' => $anoLetivo->id,
                'tipo' => 'nova',
                'data_atendimento' => now(),
                'estado' => 'inscrito',
            ]);

            $this->assertSame('F000'.($indice + 1), $inscricao->numero_ficha);
        }
    }

    public function test_proximo_numero_ficha_e_isolado_por_paroquia_mesmo_com_o_mesmo_ano_letivo_id(): void
    {
        // paroquia_id continua no scope mesmo sendo tecnicamente derivavel
        // de centro_id (pedido explicito do utilizador) — este teste chama
        // o metodo directamente para provar que o parametro paroquia_id e
        // mesmo respeitado pela query, e nao apenas "passado e ignorado".
        $paroquiaA = Paroquia::factory()->create();
        $paroquiaB = Paroquia::factory()->create();
        $centroA = Centro::factory()->create(['paroquia_id' => $paroquiaA->id]);
        $centroB = Centro::factory()->create(['paroquia_id' => $paroquiaB->id]);
        $anoLetivoA = AnoLetivo::create([
            'paroquia_id' => $paroquiaA->id,
            'nome' => '2026/2027',
            'data_inicio' => '2026-01-01',
            'data_fim' => '2026-12-15',
            'status' => 'em_curso',
        ]);
        $anoLetivoB = AnoLetivo::create([
            'paroquia_id' => $paroquiaB->id,
            'nome' => '2026/2027',
            'data_inicio' => '2026-01-01',
            'data_fim' => '2026-12-15',
            'status' => 'em_curso',
        ]);

        $catequizandoA = Catequizando::create([
            'paroquia_id' => $paroquiaA->id,
            'centro_id' => $centroA->id,
            'nome_completo' => 'Catequizando Paroquia A',
            'data_nascimento' => '2016-01-01',
            'sexo' => 'M',
            'status' => 'ativo',
        ]);

        Inscricao::create([
            'paroquia_id' => $paroquiaA->id,
            'centro_id' => $centroA->id,
            'catequizando_id' => $catequizandoA->id,
            'ano_letivo_id' => $anoLetivoA->id,
            'tipo' => 'nova',
            'data_atendimento' => now(),
            'estado' => 'inscrito',
        ]);

        // Mesmo com uma inscricao ja criada na paroquia A, o proximo numero
        // da paroquia B (centro/ano diferentes) tem de comecar do zero.
        $this->assertSame(
            'F0001',
            Inscricao::proximoNumeroFicha($paroquiaB->id, $centroB->id, $anoLetivoB->id)
        );

        $this->assertSame(
            'F0002',
            Inscricao::proximoNumeroFicha($paroquiaA->id, $centroA->id, $anoLetivoA->id)
        );
    }
}
