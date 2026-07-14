<?php

namespace Tests\Feature;

use App\Models\Centro;
use App\Models\Fiel;
use App\Models\Movimento;
use App\Models\Paroquia;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regressao: as rotas de exportacao PDF/Excel da Matriz de Assiduidade
 * recebiam sempre um unico centro_id (int)cast, incluindo 0 quando o
 * parametro vinha vazio (relatorio em modo "Todos os centros") — o que
 * produzia um export sem nenhuma linha em vez do relatorio completo da
 * paroquia.
 */
class MatrizAssiduidadeExportacaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_export_pdf_sem_centro_id_devolve_dados_de_todos_os_centros_da_paroquia(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centroA = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $centroB = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        foreach ([$centroA, $centroB] as $centro) {
            $fiel = Fiel::factory()->create(['paroquia_id' => $paroquia->id]);
            $fiel->centros()->attach($centro->id, ['data_inicio' => now()->startOfYear()]);

            Movimento::factory()->dizimo()->aprovado()->create([
                'paroquia_id' => $paroquia->id,
                'centro_id' => $centro->id,
                'fiel_id' => $fiel->id,
                'ano_competencia' => (int) now()->year,
                'mes_competencia' => 1,
            ]);
        }

        $admin = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $admin->assignRole('administrador_paroquial');
        $this->actingAs($admin);

        $response = $this->get('/relatorios/matriz-assiduidade/pdf?ano='.now()->year);

        $response->assertOk();
    }

    public function test_export_excel_sem_centro_id_devolve_dados_de_todos_os_centros_da_paroquia(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $fiel = Fiel::factory()->create(['paroquia_id' => $paroquia->id]);
        $fiel->centros()->attach($centro->id, ['data_inicio' => now()->startOfYear()]);

        $admin = User::factory()->create(['paroquia_id' => $paroquia->id]);
        $admin->assignRole('administrador_paroquial');
        $this->actingAs($admin);

        $response = $this->get('/relatorios/matriz-assiduidade/excel?ano='.now()->year);

        $response->assertOk();
    }
}
