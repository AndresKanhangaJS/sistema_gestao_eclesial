<?php

namespace Tests\Feature;

use App\Filament\Resources\ParoquiaResource\Pages\CreateParoquia;
use App\Models\Centro;
use App\Models\Paroquia;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Logotipo da paróquia (2026-08-17): só o admin_geral tem CRUD de Paróquias
 * (ParoquiaPolicy) e é quem o carrega; reaproveitado no cabeçalho dos PDFs
 * exportados via Paroquia::logoBase64() (embutido em base64, para não
 * depender do disco activo estar publicamente acessível ao Chromium
 * headless que gera os PDFs — ver App\Support\RelatorioPdf).
 */
class LogotipoParoquiaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_logo_base64_e_nulo_quando_nao_ha_logotipo(): void
    {
        $paroquia = Paroquia::factory()->create(['logo_path' => null]);

        $this->assertNull($paroquia->logoBase64());
    }

    public function test_logo_base64_devolve_data_uri_valida_quando_ha_logotipo(): void
    {
        Storage::fake(config('filesystems.default'));

        $conteudo = UploadedFile::fake()->image('logo.png', 50, 50)->get();
        Storage::disk(config('filesystems.default'))->put('logos-paroquia/teste.png', $conteudo);

        $paroquia = Paroquia::factory()->create(['logo_path' => 'logos-paroquia/teste.png']);

        $dataUri = $paroquia->logoBase64();

        $this->assertNotNull($dataUri);
        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    public function test_admin_geral_carrega_logotipo_da_paroquia(): void
    {
        Storage::fake(config('filesystems.default'));

        $admin = User::factory()->create();
        $admin->assignRole('admin_geral');
        $this->actingAs($admin);

        $ficheiro = UploadedFile::fake()->image('logo.png', 100, 100);

        Livewire::test(CreateParoquia::class)
            ->fillForm([
                'nome' => 'Paróquia de Teste',
                'diocese' => 'Diocese de Teste',
                'morada' => 'Rua Teste, 1',
                'responsavel' => 'Padre Teste',
                'email_contato' => 'paroquia@teste.local',
                'telefone' => '923000000',
                'status' => 'ativo',
                'logo_path' => $ficheiro,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $paroquia = Paroquia::where('nome', 'Paróquia de Teste')->firstOrFail();

        $this->assertNotNull($paroquia->logo_path);
        Storage::disk(config('filesystems.default'))->assertExists($paroquia->logo_path);
    }

    public function test_administrador_paroquial_nao_pode_editar_a_paroquia(): void
    {
        $paroquia = Paroquia::factory()->create();
        $admin = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $admin->assignRole('administrador_paroquial');

        $this->assertFalse($admin->can('update', $paroquia));

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.paroquias.edit', $paroquia))
            ->assertForbidden();
    }

    public function test_cabecalho_do_pdf_mostra_o_logotipo_quando_a_paroquia_tem_um(): void
    {
        Storage::fake(config('filesystems.default'));

        $conteudo = UploadedFile::fake()->image('logo.png', 50, 50)->get();
        Storage::disk(config('filesystems.default'))->put('logos-paroquia/teste.png', $conteudo);

        $paroquia = Paroquia::factory()->create(['logo_path' => 'logos-paroquia/teste.png']);

        $html = view('pdfs.layout', ['titulo' => 'Relatório de Teste', 'paroquia' => $paroquia])
            ->render();

        $this->assertStringContainsString('class="logo"', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
    }

    public function test_cabecalho_do_pdf_nao_mostra_logotipo_quando_a_paroquia_nao_tem_um(): void
    {
        $paroquia = Paroquia::factory()->create(['logo_path' => null]);

        $html = view('pdfs.layout', ['titulo' => 'Relatório de Teste', 'paroquia' => $paroquia])
            ->render();

        $this->assertStringNotContainsString('class="logo"', $html);
    }

    /**
     * Regressao: admin_geral nao tem paroquia_id (papel global) — antes
     * deste fallback, `'paroquia' => $user->paroquia` resolvia sempre null
     * nas rotas de exportacao, e o cabecalho do PDF nunca mostrava o
     * logotipo para este papel, mesmo com uma paroquia configurada.
     */
    public function test_admin_geral_sem_paroquia_propria_usa_a_primeira_paroquia_do_sistema(): void
    {
        $paroquia = Paroquia::factory()->create();
        $admin = User::factory()->create(['paroquia_id' => null]);
        $admin->assignRole('admin_geral');

        $this->assertSame($paroquia->id, $admin->paroquiaParaExportacao()?->id);
    }

    public function test_paroquia_do_centro_escolhido_tem_prioridade_sobre_a_do_utilizador(): void
    {
        $paroquiaPropria = Paroquia::factory()->create();
        $paroquiaDoCentro = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquiaDoCentro->id]);

        $user = User::factory()->create(['paroquia_id' => $paroquiaPropria->id]);
        $user->assignRole('administrador_paroquial');

        $this->assertSame($paroquiaDoCentro->id, $user->paroquiaParaExportacao($centro)?->id);
    }

    public function test_utilizador_com_paroquia_propria_nunca_cai_no_fallback(): void
    {
        Paroquia::factory()->count(2)->create();
        $paroquiaPropria = Paroquia::factory()->create();

        $user = User::factory()->create(['paroquia_id' => $paroquiaPropria->id]);
        $user->assignRole('administrador_paroquial');

        $this->assertSame($paroquiaPropria->id, $user->paroquiaParaExportacao()?->id);
    }
}
