<?php

namespace Tests\Feature;

use App\Filament\Resources\CatequizandoResource\Pages\CreateCatequizando;
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
 * Fluxo "Inscrever" (docs/modulos/catequese.md secc. 27): um so formulario
 * grava Catequizando + Inscricao de uma vez, eliminando as duas voltas de
 * antes (criar o Catequizando, depois ir a Inscricoes criar a ficha).
 */
class InscreverCatequizandoTest extends TestCase
{
    use RefreshDatabase;

    private Paroquia $paroquia;

    private Centro $centro;

    private AnoLetivo $anoLetivo;

    private AnoCatequetico $anoCatequetico;

    private Sacramento $sacramento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(CatequeseSeeder::class);

        $this->paroquia = Paroquia::factory()->create();
        $this->centro = Centro::factory()->create(['paroquia_id' => $this->paroquia->id]);
        $this->anoLetivo = AnoLetivo::create([
            'paroquia_id' => $this->paroquia->id,
            'nome' => '2026/2027',
            'data_inicio' => '2026-01-01',
            'data_fim' => '2026-12-15',
            'status' => 'em_curso',
        ]);
        $this->anoCatequetico = AnoCatequetico::where('ordem', 1)->firstOrFail();
        $this->sacramento = Sacramento::where('ordem', 1)->firstOrFail();

        $coordenador = User::factory()->create(['paroquia_id' => $this->paroquia->id]);
        $coordenador->assignRole('coordenador_catequese_paroquia');
        $this->actingAs($coordenador);
    }

    public function test_grava_catequizando_e_inscricao_numa_so_operacao(): void
    {
        Livewire::test(CreateCatequizando::class)
            ->fillForm([
                'centro_id' => $this->centro->id,
                'nome_completo' => 'Ana Cliente',
                'data_nascimento' => '2016-05-10',
                'sexo' => 'F',
                'status' => 'ativo',
                'inscricao' => [
                    'ano_letivo_id' => $this->anoLetivo->id,
                    'ano_catequetico_id' => $this->anoCatequetico->id,
                    'sacramentos' => [$this->sacramento->id],
                    'data_atendimento' => now()->format('Y-m-d'),
                    'estado' => 'inscrito',
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $catequizando = Catequizando::where('nome_completo', 'Ana Cliente')->firstOrFail();

        $this->assertSame($this->centro->id, $catequizando->centro_id);
        $this->assertTrue(
            $catequizando->centros()->where('centros.id', $this->centro->id)->exists(),
            'catequizando_centros deveria ter o registo do centro inicial.'
        );

        $inscricao = Inscricao::where('catequizando_id', $catequizando->id)->firstOrFail();

        $this->assertSame($this->anoLetivo->id, $inscricao->ano_letivo_id);
        $this->assertSame('nova', $inscricao->tipo->value);
        $this->assertSame('F0001', $inscricao->numero_ficha);
        $this->assertSame($this->centro->id, $inscricao->centro_id);
        $this->assertTrue($inscricao->sacramentos()->where('sacramentos.id', $this->sacramento->id)->exists());
    }

    public function test_falha_ao_gravar_a_inscricao_reverte_tambem_o_catequizando(): void
    {
        // ano_letivo_id inexistente -> viola a foreign key ao gravar a
        // Inscricao em afterCreate(); com hasDatabaseTransactions() a true,
        // isto tem de reverter tambem o Catequizando ja "criado" antes.
        try {
            Livewire::test(CreateCatequizando::class)
                ->fillForm([
                    'centro_id' => $this->centro->id,
                    'nome_completo' => 'Falha Proposital',
                    'data_nascimento' => '2015-01-01',
                    'sexo' => 'M',
                    'status' => 'ativo',
                    'inscricao' => [
                        'ano_letivo_id' => 999999,
                        'ano_catequetico_id' => $this->anoCatequetico->id,
                        'sacramentos' => [$this->sacramento->id],
                        'data_atendimento' => now()->format('Y-m-d'),
                        'estado' => 'inscrito',
                    ],
                ])
                ->call('create');
        } catch (\Throwable) {
            // esperado: violacao de foreign key
        }

        $this->assertSame(0, Catequizando::count());
        $this->assertSame(0, Inscricao::count());
    }
}
