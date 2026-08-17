<?php

namespace Tests\Feature;

use App\Filament\Resources\FielResource;
use App\Filament\Resources\FielResource\Pages\CreateFiel;
use App\Filament\Resources\FielResource\RelationManagers\MovimentosRelationManager;
use App\Models\Centro;
use App\Models\Fiel;
use App\Models\Movimento;
use App\Models\Paroquia;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * coordenador_centro e secretario_centro (2026-08-17): dois papeis novos,
 * presos ao seu proprio centro. coordenador_centro tem paridade financeira
 * alargada com tesoureiro_centro (CRUD de Movimentos, sem conciliacao) MAIS
 * CRUD completo de Fieis; secretario_centro so tem o CRUD de Fieis, sem
 * qualquer acesso a Movimentos.
 */
class CoordenadorSecretarioCentroTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function utilizadorDeCentro(string $papel, Centro $centro): User
    {
        $user = User::factory()->create([
            'paroquia_id' => $centro->paroquia_id,
            'centro_id' => $centro->id,
        ]);
        $user->assignRole($papel);

        return $user;
    }

    public function test_coordenador_centro_tem_crud_completo_de_fieis_do_proprio_centro(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $coordenador = $this->utilizadorDeCentro('coordenador_centro', $centro);

        $fiel = Fiel::factory()->create(['paroquia_id' => $paroquia->id]);
        $fiel->centros()->attach($centro->id, ['data_inicio' => now(), 'principal' => true]);

        $this->assertTrue($coordenador->can('viewAny', Fiel::class));
        $this->assertTrue($coordenador->can('create', Fiel::class));
        $this->assertTrue($coordenador->can('view', $fiel));
        $this->assertTrue($coordenador->can('update', $fiel));
        $this->assertTrue($coordenador->can('delete', $fiel));
    }

    public function test_secretario_centro_tem_crud_completo_de_fieis_mas_zero_acesso_a_movimentos(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $secretario = $this->utilizadorDeCentro('secretario_centro', $centro);

        $fiel = Fiel::factory()->create(['paroquia_id' => $paroquia->id]);
        $fiel->centros()->attach($centro->id, ['data_inicio' => now(), 'principal' => true]);

        $this->assertTrue($secretario->can('create', Fiel::class));
        $this->assertTrue($secretario->can('update', $fiel));

        $this->assertFalse($secretario->can('viewAny', Movimento::class));
        $this->assertFalse($secretario->can('create', Movimento::class));
    }

    public function test_coordenador_centro_gere_movimentos_do_proprio_centro_mas_nunca_concilia(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $coordenador = $this->utilizadorDeCentro('coordenador_centro', $centro);

        $movimento = Movimento::factory()->create(['paroquia_id' => $paroquia->id, 'centro_id' => $centro->id]);

        $this->assertTrue($coordenador->can('viewAny', Movimento::class));
        $this->assertTrue($coordenador->can('create', Movimento::class));
        $this->assertTrue($coordenador->can('update', $movimento));
        $this->assertFalse($coordenador->can('aprovar', $movimento));
        $this->assertFalse($coordenador->can('rejeitar', $movimento));
    }

    public function test_coordenador_secretario_centro_nao_veem_nem_gerem_fieis_de_outro_centro(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centroProprio = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $centroAlheio = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $fielAlheio = Fiel::factory()->create(['paroquia_id' => $paroquia->id]);
        $fielAlheio->centros()->attach($centroAlheio->id, ['data_inicio' => now(), 'principal' => true]);

        foreach (['coordenador_centro', 'secretario_centro'] as $papel) {
            $user = $this->utilizadorDeCentro($papel, $centroProprio);

            $this->assertFalse($user->can('view', $fielAlheio), "$papel nao devia ver fiel de outro centro");
            $this->assertFalse($user->can('update', $fielAlheio), "$papel nao devia editar fiel de outro centro");
        }
    }

    public function test_eloquent_query_do_fiel_resource_restringe_ao_proprio_centro(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centroProprio = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $centroAlheio = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $fielProprio = Fiel::factory()->create(['paroquia_id' => $paroquia->id]);
        $fielProprio->centros()->attach($centroProprio->id, ['data_inicio' => now(), 'principal' => true]);

        $fielAlheio = Fiel::factory()->create(['paroquia_id' => $paroquia->id]);
        $fielAlheio->centros()->attach($centroAlheio->id, ['data_inicio' => now(), 'principal' => true]);

        $secretario = $this->utilizadorDeCentro('secretario_centro', $centroProprio);
        $this->actingAs($secretario);

        $ids = FielResource::getEloquentQuery()->pluck('id');

        $this->assertContains($fielProprio->id, $ids);
        $this->assertNotContains($fielAlheio->id, $ids);
    }

    public function test_criar_fiel_como_secretario_centro_vincula_automaticamente_ao_seu_centro(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);
        $secretario = $this->utilizadorDeCentro('secretario_centro', $centro);

        $this->actingAs($secretario);

        Livewire::test(CreateFiel::class)
            ->fillForm([
                'nome' => 'Fiel Novo',
                'status' => 'ativo',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $fiel = Fiel::where('nome', 'Fiel Novo')->firstOrFail();

        $this->assertTrue(
            $fiel->centros()->wherePivotNull('data_fim')->where('centros.id', $centro->id)->exists()
        );
    }

    public function test_separador_de_movimentos_do_fiel_fica_escondido_para_secretario_centro(): void
    {
        $paroquia = Paroquia::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $paroquia->id]);

        $fiel = Fiel::factory()->create(['paroquia_id' => $paroquia->id]);
        $fiel->centros()->attach($centro->id, ['data_inicio' => now(), 'principal' => true]);

        $secretario = $this->utilizadorDeCentro('secretario_centro', $centro);
        $this->actingAs($secretario);
        $this->assertFalse(MovimentosRelationManager::canViewForRecord($fiel, 'edit'));

        $coordenador = $this->utilizadorDeCentro('coordenador_centro', $centro);
        $this->actingAs($coordenador);
        $this->assertTrue(MovimentosRelationManager::canViewForRecord($fiel, 'edit'));
    }
}
