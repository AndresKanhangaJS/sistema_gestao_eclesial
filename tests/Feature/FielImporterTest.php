<?php

namespace Tests\Feature;

use App\Filament\Imports\FielImporter;
use App\Models\Centro;
use App\Models\Fiel;
use App\Models\Paroquia;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Importacao em massa de Fieis: o ficheiro so traz dados do proprio fiel —
 * centro (escolhido no modal, uma vez para todas as linhas) e paroquia
 * (do utilizador que importa) nunca vem do ficheiro, mesma regra "nunca
 * confiar em dados externos" ja aplicada ao Movimento.
 */
class FielImporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function criarImport(User $user): Import
    {
        return Import::create([
            'file_name' => 'fieis.csv',
            'file_path' => 'fieis.csv',
            'importer' => FielImporter::class,
            'total_rows' => 1,
            'user_id' => $user->id,
        ]);
    }

    private function colunas(): array
    {
        return [
            'nome' => 'nome',
            'telefone' => 'telefone',
            'email' => 'email',
            'data_nascimento' => 'data_nascimento',
            'status' => 'status',
        ];
    }

    public function test_linha_importada_assume_o_centro_escolhido_e_a_paroquia_de_quem_importa(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $admin = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $admin->assignRole('administrador_paroquial');

        $import = $this->criarImport($admin);

        $importer = new FielImporter($import, columnMap: $this->colunas(), options: ['centro_id' => $centro->id]);

        $importer([
            'nome' => 'Maria João dos Santos',
            'telefone' => '923456789',
            'email' => 'maria@example.com',
            'data_nascimento' => '1990-05-10',
            'status' => 'ativo',
        ]);

        $fiel = Fiel::where('nome', 'Maria João dos Santos')->first();

        $this->assertNotNull($fiel);
        $this->assertSame($paroquia->id, $fiel->paroquia_id);
        $this->assertTrue($fiel->centros()->where('centros.id', $centro->id)->exists());
        $this->assertNotNull($fiel->codigo_dizimista);
    }

    public function test_estado_em_branco_no_ficheiro_assume_ativo_por_defeito(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $admin = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $admin->assignRole('administrador_paroquial');

        $import = $this->criarImport($admin);
        $importer = new FielImporter($import, columnMap: $this->colunas(), options: ['centro_id' => $centro->id]);

        $importer([
            'nome' => 'Pedro António',
            'telefone' => '',
            'email' => '',
            'data_nascimento' => '',
            'status' => '',
        ]);

        $fiel = Fiel::where('nome', 'Pedro António')->first();

        $this->assertNotNull($fiel);
        $this->assertSame('ativo', $fiel->status);
    }

    public function test_nao_permite_importar_para_centro_de_outra_paroquia(): void
    {
        $paroquiaPropria = Paroquia::factory()->create();
        $paroquiaAlheia = Paroquia::factory()->create();
        $centroAlheio = Centro::factory()->create(['paroquia_id' => $paroquiaAlheia->id]);

        $admin = User::factory()->create(['paroquia_id' => $paroquiaPropria->id]);
        $admin->assignRole('administrador_paroquial');

        $import = $this->criarImport($admin);
        $importer = new FielImporter($import, columnMap: $this->colunas(), options: ['centro_id' => $centroAlheio->id]);

        $this->expectException(RowImportFailedException::class);

        try {
            $importer(['nome' => 'Fiel Intruso', 'telefone' => '', 'email' => '', 'data_nascimento' => '', 'status' => '']);
        } finally {
            $this->assertSame(0, Fiel::withoutGlobalScopes()->where('nome', 'Fiel Intruso')->count());
        }
    }

    public function test_nome_em_falta_falha_a_validacao_sem_criar_registo(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $admin = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $admin->assignRole('administrador_paroquial');

        $import = $this->criarImport($admin);
        $importer = new FielImporter($import, columnMap: $this->colunas(), options: ['centro_id' => $centro->id]);

        $this->expectException(ValidationException::class);

        $importer(['nome' => '', 'telefone' => '', 'email' => '', 'data_nascimento' => '', 'status' => '']);
    }
}
