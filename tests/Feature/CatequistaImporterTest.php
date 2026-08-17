<?php

namespace Tests\Feature;

use App\Filament\Imports\CatequistaImporter;
use App\Models\Catequista;
use App\Models\Centro;
use App\Models\Paroquia;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Importacao em massa de Catequistas: mesma regra do FielImporterTest —
 * centro (escolhido no modal, como "Centro Principal") e paroquia (do
 * utilizador que importa) nunca vem do ficheiro.
 */
class CatequistaImporterTest extends TestCase
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
            'file_name' => 'catequistas.csv',
            'file_path' => 'catequistas.csv',
            'importer' => CatequistaImporter::class,
            'total_rows' => 1,
            'user_id' => $user->id,
        ]);
    }

    private function colunas(): array
    {
        return [
            'nome_completo' => 'nome_completo',
            'data_nascimento' => 'data_nascimento',
            'telefone' => 'telefone',
            'email' => 'email',
            'ativo' => 'ativo',
        ];
    }

    private function linhaBase(array $overrides = []): array
    {
        return array_merge([
            'nome_completo' => 'Ana Paula Ferreira',
            'data_nascimento' => '',
            'telefone' => '',
            'email' => '',
            'ativo' => '',
        ], $overrides);
    }

    public function test_linha_importada_assume_o_centro_escolhido_e_a_paroquia_de_quem_importa(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $coordenador = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $coordenador->assignRole('coordenador_catequese_paroquia');

        $import = $this->criarImport($coordenador);
        $importer = new CatequistaImporter($import, columnMap: $this->colunas(), options: ['centro_id' => $centro->id]);

        $importer($this->linhaBase());

        $catequista = Catequista::where('nome_completo', 'Ana Paula Ferreira')->first();

        $this->assertNotNull($catequista);
        $this->assertSame($paroquia->id, $catequista->paroquia_id);
        $this->assertSame($centro->id, $catequista->centro_id);
    }

    public function test_estado_em_branco_no_ficheiro_assume_activo_por_defeito(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $coordenador = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $coordenador->assignRole('coordenador_catequese_paroquia');

        $import = $this->criarImport($coordenador);
        $importer = new CatequistaImporter($import, columnMap: $this->colunas(), options: ['centro_id' => $centro->id]);

        $importer($this->linhaBase(['nome_completo' => 'Pedro António']));

        $catequista = Catequista::where('nome_completo', 'Pedro António')->first();

        $this->assertNotNull($catequista);
        $this->assertTrue($catequista->ativo);
    }

    public function test_estado_inativo_no_ficheiro_e_respeitado(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $coordenador = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $coordenador->assignRole('coordenador_catequese_paroquia');

        $import = $this->criarImport($coordenador);
        $importer = new CatequistaImporter($import, columnMap: $this->colunas(), options: ['centro_id' => $centro->id]);

        $importer($this->linhaBase(['nome_completo' => 'Catequista Inactivo', 'ativo' => 'inativo']));

        $catequista = Catequista::where('nome_completo', 'Catequista Inactivo')->first();

        $this->assertNotNull($catequista);
        $this->assertFalse($catequista->ativo);
    }

    public function test_nao_permite_importar_para_centro_de_outra_paroquia(): void
    {
        $paroquiaPropria = Paroquia::factory()->create();
        $paroquiaAlheia = Paroquia::factory()->create();
        $centroAlheio = Centro::factory()->create(['paroquia_id' => $paroquiaAlheia->id]);

        $coordenador = User::factory()->create(['paroquia_id' => $paroquiaPropria->id]);
        $coordenador->assignRole('coordenador_catequese_paroquia');

        $import = $this->criarImport($coordenador);
        $importer = new CatequistaImporter($import, columnMap: $this->colunas(), options: ['centro_id' => $centroAlheio->id]);

        $this->expectException(RowImportFailedException::class);

        try {
            $importer($this->linhaBase(['nome_completo' => 'Catequista Intruso']));
        } finally {
            $this->assertSame(0, Catequista::withoutGlobalScopes()->where('nome_completo', 'Catequista Intruso')->count());
        }
    }

    public function test_nome_em_falta_falha_a_validacao_sem_criar_registo(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $coordenador = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $coordenador->assignRole('coordenador_catequese_paroquia');

        $import = $this->criarImport($coordenador);
        $importer = new CatequistaImporter($import, columnMap: $this->colunas(), options: ['centro_id' => $centro->id]);

        $this->expectException(ValidationException::class);

        $importer($this->linhaBase(['nome_completo' => '']));
    }
}
