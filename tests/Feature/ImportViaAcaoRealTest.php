<?php

namespace Tests\Feature;

use App\Filament\Resources\CatequistaResource\Pages\ListCatequistas;
use App\Filament\Resources\CatequizandoResource\Pages\ListCatequizandos;
use App\Models\Centro;
use App\Models\Paroquia;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regressao: os testes de CatequistaImporter/CatequizandoImporter chamavam
 * o Importer directamente (new CatequistaImporter(...)($linha)), o que
 * NUNCA passa pelo formulario de opcoes da ImportAction em si — por isso
 * nao apanharam o bug real reportado pelo utilizador: ->visible(false) no
 * campo centro_id fazia o valor desaparecer do array $options que chega ao
 * job da fila (Filament\Actions\Imports\Concerns\CanImportRecords faz
 * array_except($form->getState(), ['file', 'columnMap']), e getState() de
 * uma ImportAction nao inclui campos invisiveis). Todas as linhas falhavam
 * em silencio com "Centro invalido para a paroquia de quem esta a
 * importar." — corrigido para ->disabled()->dehydrated(). Este teste sobe
 * mesmo um ficheiro CSV atraves da acao real (QUEUE_CONNECTION=sync em
 * phpunit.xml corre o job na hora), para nunca mais passar despercebido.
 */
class ImportViaAcaoRealTest extends TestCase
{
    use RefreshDatabase;

    private Paroquia $paroquia;

    private Centro $centro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->paroquia = Paroquia::factory()->create();
        $this->centro = Centro::factory()->create(['paroquia_id' => $this->paroquia->id]);
    }

    public function test_coordenador_de_centro_importa_catequistas_com_sucesso_por_upload_real(): void
    {
        $coordenador = User::factory()->create([
            'paroquia_id' => $this->paroquia->id,
            'centro_id' => $this->centro->id,
        ]);
        $coordenador->assignRole('coordenador_catequese_centro');
        $this->actingAs($coordenador);

        $csv = "nome_completo,data_nascimento,telefone,email,ativo\n"
            ."Ana Paula Ferreira,1990-11-02,923456789,ana@example.com,ativo\n";

        Livewire::test(ListCatequistas::class)
            ->mountAction('import')
            ->setActionData([
                'file' => UploadedFile::fake()->createWithContent('catequistas.csv', $csv),
                'columnMap' => [
                    'nome_completo' => 'nome_completo',
                    'data_nascimento' => 'data_nascimento',
                    'telefone' => 'telefone',
                    'email' => 'email',
                    'ativo' => 'ativo',
                ],
            ])
            ->callMountedAction();

        $this->assertDatabaseHas('catequistas', [
            'nome_completo' => 'Ana Paula Ferreira',
            'paroquia_id' => $this->paroquia->id,
            'centro_id' => $this->centro->id,
        ]);
    }

    public function test_secretario_catequese_importa_catequizandos_com_sucesso_por_upload_real(): void
    {
        $secretario = User::factory()->create([
            'paroquia_id' => $this->paroquia->id,
            'centro_id' => $this->centro->id,
        ]);
        $secretario->assignRole('secretario_catequese');
        $this->actingAs($secretario);

        $csv = "nome_completo,data_nascimento,sexo\n"
            ."João Manuel dos Santos,2014-06-15,M\n";

        Livewire::test(ListCatequizandos::class)
            ->mountAction('import')
            ->setActionData([
                'file' => UploadedFile::fake()->createWithContent('catequizandos.csv', $csv),
                'columnMap' => [
                    'nome_completo' => 'nome_completo',
                    'data_nascimento' => 'data_nascimento',
                    'sexo' => 'sexo',
                ],
            ])
            ->callMountedAction();

        $this->assertDatabaseHas('catequizandos', [
            'nome_completo' => 'João Manuel dos Santos',
            'paroquia_id' => $this->paroquia->id,
            'centro_id' => $this->centro->id,
        ]);
    }
}
