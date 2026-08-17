<?php

namespace Tests\Feature;

use App\Filament\Imports\CatequizandoImporter;
use App\Models\Catequizando;
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
 * Importacao em massa de Catequizandos: mesma regra do FielImporterTest —
 * centro (escolhido no modal) e paroquia (do utilizador que importa) nunca
 * vem do ficheiro.
 */
class CatequizandoImporterTest extends TestCase
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
            'file_name' => 'catequizandos.csv',
            'file_path' => 'catequizandos.csv',
            'importer' => CatequizandoImporter::class,
            'total_rows' => 1,
            'user_id' => $user->id,
        ]);
    }

    private function colunas(): array
    {
        return [
            'nome_completo' => 'nome_completo',
            'data_nascimento' => 'data_nascimento',
            'sexo' => 'sexo',
            'nome_pai' => 'nome_pai',
            'nome_mae' => 'nome_mae',
            'numero_identificacao' => 'numero_identificacao',
            'telefone' => 'telefone',
            'email' => 'email',
            'residencia' => 'residencia',
            'status' => 'status',
        ];
    }

    private function linhaBase(array $overrides = []): array
    {
        return array_merge([
            'nome_completo' => 'João Manuel dos Santos',
            'data_nascimento' => '2014-06-15',
            'sexo' => 'M',
            'nome_pai' => '',
            'nome_mae' => '',
            'numero_identificacao' => '',
            'telefone' => '',
            'email' => '',
            'residencia' => '',
            'status' => '',
        ], $overrides);
    }

    public function test_linha_importada_assume_o_centro_escolhido_e_a_paroquia_de_quem_importa(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $coordenador = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $coordenador->assignRole('coordenador_catequese_paroquia');

        $import = $this->criarImport($coordenador);
        $importer = new CatequizandoImporter($import, columnMap: $this->colunas(), options: ['centro_id' => $centro->id]);

        $importer($this->linhaBase());

        $catequizando = Catequizando::where('nome_completo', 'João Manuel dos Santos')->first();

        $this->assertNotNull($catequizando);
        $this->assertSame($paroquia->id, $catequizando->paroquia_id);
        $this->assertSame($centro->id, $catequizando->centro_id);
        $this->assertTrue($catequizando->centros()->where('centros.id', $centro->id)->exists());
    }

    public function test_estado_em_branco_no_ficheiro_assume_ativo_por_defeito(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $coordenador = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $coordenador->assignRole('coordenador_catequese_paroquia');

        $import = $this->criarImport($coordenador);
        $importer = new CatequizandoImporter($import, columnMap: $this->colunas(), options: ['centro_id' => $centro->id]);

        $importer($this->linhaBase(['nome_completo' => 'Pedro António']));

        $catequizando = Catequizando::where('nome_completo', 'Pedro António')->first();

        $this->assertNotNull($catequizando);
        $this->assertSame('ativo', $catequizando->status);
    }

    public function test_nao_permite_importar_para_centro_de_outra_paroquia(): void
    {
        $paroquiaPropria = Paroquia::factory()->create();
        $paroquiaAlheia = Paroquia::factory()->create();
        $centroAlheio = Centro::factory()->create(['paroquia_id' => $paroquiaAlheia->id]);

        $coordenador = User::factory()->create(['paroquia_id' => $paroquiaPropria->id]);
        $coordenador->assignRole('coordenador_catequese_paroquia');

        $import = $this->criarImport($coordenador);
        $importer = new CatequizandoImporter($import, columnMap: $this->colunas(), options: ['centro_id' => $centroAlheio->id]);

        $this->expectException(RowImportFailedException::class);

        try {
            $importer($this->linhaBase(['nome_completo' => 'Catequizando Intruso']));
        } finally {
            $this->assertSame(0, Catequizando::withoutGlobalScopes()->where('nome_completo', 'Catequizando Intruso')->count());
        }
    }

    public function test_nome_em_falta_falha_a_validacao_sem_criar_registo(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $coordenador = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $coordenador->assignRole('coordenador_catequese_paroquia');

        $import = $this->criarImport($coordenador);
        $importer = new CatequizandoImporter($import, columnMap: $this->colunas(), options: ['centro_id' => $centro->id]);

        $this->expectException(ValidationException::class);

        $importer($this->linhaBase(['nome_completo' => '']));
    }

    public function test_data_nascimento_em_falta_falha_a_validacao(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $coordenador = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $coordenador->assignRole('coordenador_catequese_paroquia');

        $import = $this->criarImport($coordenador);
        $importer = new CatequizandoImporter($import, columnMap: $this->colunas(), options: ['centro_id' => $centro->id]);

        $this->expectException(ValidationException::class);

        $importer($this->linhaBase(['data_nascimento' => '']));
    }
}
