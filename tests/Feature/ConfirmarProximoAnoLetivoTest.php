<?php

namespace Tests\Feature;

use App\Filament\Resources\CatequizandoResource\Pages\ListCatequizandos;
use App\Models\AnoCatequetico;
use App\Models\AnoLetivo;
use App\Models\Catequizando;
use App\Models\Centro;
use App\Models\Inscricao;
use App\Models\Paroquia;
use App\Models\Sacramento;
use App\Models\User;
use Database\Seeders\CatequeseSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Confirmar para o Próximo Ano Lectivo" (docs/modulos/catequese.md secc.
 * 27/29) — progressao ano-a-ano a partir da lista de Catequizandos. Decisao
 * do utilizador: qualquer estado da inscricao actual excepto "cancelado"
 * habilita a confirmacao (desvio consciente da secc. 7, que so falava de
 * "aprovado"). A accao so fica disponivel quando ja existe um ano lectivo
 * seguinte "em_curso" nesta paroquia — sem isso, nao ha para onde confirmar
 * sem risco de cair no mesmo ano lectivo em que o catequizando ja esta.
 */
class ConfirmarProximoAnoLetivoTest extends TestCase
{
    use RefreshDatabase;

    private Paroquia $paroquia;

    private Centro $centro;

    private AnoLetivo $anoAtual;

    private AnoLetivo $anoSeguinte;

    private AnoCatequetico $anoCatequetico1;

    private AnoCatequetico $anoCatequetico2;

    private Sacramento $sacramento;

    private Catequizando $catequizando;

    private Inscricao $inscricaoActual;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(CatequeseSeeder::class);

        $this->paroquia = Paroquia::factory()->create();
        $this->centro = Centro::factory()->create(['paroquia_id' => $this->paroquia->id]);

        $this->anoAtual = AnoLetivo::create([
            'paroquia_id' => $this->paroquia->id,
            'nome' => '2026/2027',
            'data_inicio' => '2026-01-01',
            'data_fim' => '2026-12-15',
            'status' => 'em_curso',
        ]);

        // "em_curso" de proposito: a accao so fica disponivel quando ja
        // existe um ano lectivo seguinte activo nesta paroquia (ver
        // test_bloqueia_quando_nao_ha_ano_lectivo_seguinte_em_curso, que
        // cobre o caso oposto).
        $this->anoSeguinte = AnoLetivo::create([
            'paroquia_id' => $this->paroquia->id,
            'nome' => '2027/2028',
            'data_inicio' => '2027-01-01',
            'data_fim' => '2027-12-15',
            'status' => 'em_curso',
        ]);

        $this->anoCatequetico1 = AnoCatequetico::where('ordem', 1)->firstOrFail();
        $this->anoCatequetico2 = AnoCatequetico::where('ordem', 2)->firstOrFail();
        $this->sacramento = Sacramento::where('ordem', 1)->firstOrFail();

        $this->catequizando = Catequizando::create([
            'paroquia_id' => $this->paroquia->id,
            'centro_id' => $this->centro->id,
            'nome_completo' => 'Catequizando Progressao',
            'data_nascimento' => '2016-03-01',
            'sexo' => 'M',
            'status' => 'ativo',
        ]);

        $this->inscricaoActual = Inscricao::create([
            'paroquia_id' => $this->paroquia->id,
            'centro_id' => $this->centro->id,
            'catequizando_id' => $this->catequizando->id,
            'ano_letivo_id' => $this->anoAtual->id,
            'ano_catequetico_id' => $this->anoCatequetico1->id,
            'tipo' => 'nova',
            'data_atendimento' => now(),
            'estado' => 'inscrito',
        ]);
        $this->inscricaoActual->sacramentos()->sync([$this->sacramento->id]);

        $coordenador = User::factory()->create(['paroquia_id' => $this->paroquia->id]);
        $coordenador->assignRole('coordenador_catequese_paroquia');
        $this->actingAs($coordenador);
    }

    public function test_gera_nova_inscricao_ligada_a_anterior(): void
    {
        Livewire::test(ListCatequizandos::class)
            ->callTableAction('confirmarProximoAno', $this->catequizando)
            ->assertHasNoTableActionErrors();

        $novaInscricao = Inscricao::where('catequizando_id', $this->catequizando->id)
            ->where('id', '!=', $this->inscricaoActual->id)
            ->firstOrFail();

        $this->assertSame($this->anoSeguinte->id, $novaInscricao->ano_letivo_id);
        $this->assertSame($this->anoCatequetico2->id, $novaInscricao->ano_catequetico_id);
        $this->assertSame('confirmacao', $novaInscricao->tipo->value);
        $this->assertSame($this->inscricaoActual->id, $novaInscricao->inscricao_anterior_id);
        $this->assertSame('inscrito', $novaInscricao->estado->value);
        $this->assertTrue($novaInscricao->sacramentos()->where('sacramentos.id', $this->sacramento->id)->exists());
    }

    public function test_bloqueia_confirmacao_repetida_para_o_mesmo_ano_lectivo(): void
    {
        Inscricao::create([
            'paroquia_id' => $this->paroquia->id,
            'centro_id' => $this->centro->id,
            'catequizando_id' => $this->catequizando->id,
            'ano_letivo_id' => $this->anoSeguinte->id,
            'ano_catequetico_id' => $this->anoCatequetico2->id,
            'tipo' => 'confirmacao',
            'inscricao_anterior_id' => $this->inscricaoActual->id,
            'data_atendimento' => now(),
            'estado' => 'inscrito',
        ]);

        Livewire::test(ListCatequizandos::class)
            ->callTableAction('confirmarProximoAno', $this->catequizando, data: [
                'ano_letivo_id' => $this->anoSeguinte->id,
                'ano_catequetico_id' => $this->anoCatequetico2->id,
                'sacramentos' => [$this->sacramento->id],
                'data_atendimento' => now()->format('Y-m-d'),
            ])
            ->assertHasTableActionErrors(['ano_letivo_id']);

        $this->assertSame(
            2,
            Inscricao::where('catequizando_id', $this->catequizando->id)->count(),
            'nao deveria ter criado uma terceira inscricao.'
        );
    }

    public function test_estado_reprovado_ainda_assim_permite_confirmar(): void
    {
        $this->inscricaoActual->update(['estado' => 'reprovado']);

        Livewire::test(ListCatequizandos::class)
            ->callTableAction('confirmarProximoAno', $this->catequizando)
            ->assertHasNoTableActionErrors();

        $this->assertSame(2, Inscricao::where('catequizando_id', $this->catequizando->id)->count());
    }

    public function test_estado_cancelado_bloqueia_a_accao(): void
    {
        $this->inscricaoActual->update(['estado' => 'cancelado']);

        Livewire::test(ListCatequizandos::class)
            ->assertTableActionDisabled('confirmarProximoAno', $this->catequizando);

        $this->assertSame(1, Inscricao::where('catequizando_id', $this->catequizando->id)->count());
    }

    public function test_alerta_em_vez_de_gravar_quando_nao_ha_ano_lectivo_seguinte_em_curso(): void
    {
        // Pedido do utilizador: em vez de um botao simplesmente desactivado
        // (facil de passar despercebido), a accao continua clicavel e o
        // modal mostra um alerta explicativo — sem opcao de submeter, para
        // nunca deixar gravar uma confirmacao para o mesmo ano lectivo em
        // que o catequizando ja esta (fecha o ano seguinte: nao ha nenhum
        // "em_curso" alem do actual, tal como acontece na realidade entre a
        // paroquia fechar o ano lectivo actual e abrir o proximo).
        $this->anoSeguinte->update(['status' => 'encerrado']);

        Livewire::test(ListCatequizandos::class)
            ->assertTableActionEnabled('confirmarProximoAno', $this->catequizando)
            ->callTableAction('confirmarProximoAno', $this->catequizando);

        $this->assertSame(
            1,
            Inscricao::where('catequizando_id', $this->catequizando->id)->count(),
            'o alerta nao deveria ter permitido gravar nenhuma inscricao nova.'
        );
    }

    public function test_alerta_em_vez_de_gravar_quando_nao_existe_nenhum_ano_lectivo_seguinte(): void
    {
        $this->anoSeguinte->delete();

        Livewire::test(ListCatequizandos::class)
            ->assertTableActionEnabled('confirmarProximoAno', $this->catequizando)
            ->callTableAction('confirmarProximoAno', $this->catequizando);

        $this->assertSame(1, Inscricao::where('catequizando_id', $this->catequizando->id)->count());
    }
}
