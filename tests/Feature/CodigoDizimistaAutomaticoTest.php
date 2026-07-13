<?php

namespace Tests\Feature;

use App\Filament\Resources\FielResource\Pages\CreateFiel;
use App\Models\Fiel;
use App\Models\Paroquia;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * O codigo_dizimista deixou de ser digitado a mao (unique globalmente — nao
 * so por paroquia) e passou a ser gerado automaticamente ao criar um Fiel,
 * para evitar choques de codigos duplicados.
 */
class CodigoDizimistaAutomaticoTest extends TestCase
{
    use RefreshDatabase;

    public function test_gera_primeiro_codigo_quando_nao_ha_fieis(): void
    {
        $this->assertSame('F0001', Fiel::proximoCodigoDizimista());
    }

    public function test_gera_codigo_seguinte_ao_maior_existente_mesmo_que_tenha_sido_atribuido_a_mao(): void
    {
        Fiel::factory()->create(['codigo_dizimista' => 'F0005']);

        $this->assertSame('F0006', Fiel::proximoCodigoDizimista());
    }

    public function test_criar_fiel_sem_codigo_atribui_automaticamente(): void
    {
        $paroquia = Paroquia::factory()->create();

        $fiel = Fiel::create([
            'paroquia_id' => $paroquia->id,
            'nome' => 'Fiel Teste',
            'status' => 'ativo',
        ]);

        $this->assertSame('F0001', $fiel->codigo_dizimista);
    }

    public function test_fieis_criados_em_sequencia_recebem_codigos_diferentes_e_sequenciais(): void
    {
        $paroquia = Paroquia::factory()->create();

        $primeiro = Fiel::create(['paroquia_id' => $paroquia->id, 'nome' => 'Fiel Um', 'status' => 'ativo']);
        $segundo = Fiel::create(['paroquia_id' => $paroquia->id, 'nome' => 'Fiel Dois', 'status' => 'ativo']);

        $this->assertSame('F0001', $primeiro->codigo_dizimista);
        $this->assertSame('F0002', $segundo->codigo_dizimista);
    }

    public function test_formulario_de_criacao_gera_o_codigo_sem_o_utilizador_o_escrever(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin_geral');
        $this->actingAs($admin);

        $paroquia = Paroquia::factory()->create();

        Livewire::test(CreateFiel::class)
            ->fillForm([
                'paroquia_id' => $paroquia->id,
                'nome' => 'Fiel Via Formulário',
                'status' => 'ativo',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $fiel = Fiel::where('nome', 'Fiel Via Formulário')->firstOrFail();

        $this->assertSame('F0001', $fiel->codigo_dizimista);
    }
}
