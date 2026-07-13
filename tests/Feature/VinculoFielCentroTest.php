<?php

namespace Tests\Feature;

use App\Filament\Resources\FielResource\RelationManagers\CentrosRelationManager;
use App\Models\Centro;
use App\Models\Fiel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

/**
 * Regressao: vincular/pesquisar um centro no perfil do Fiel rebentava com
 * "BadMethodCallException: Call to undefined method App\Models\Centro::fiels()".
 * O AttachAction do Filament, quando nao configuramos inverseRelationship
 * explicitamente, adivinha o nome do relacionamento inverso pluralizando
 * "Fiel" a moda inglesa ("fiels") em vez de usar o metodo real
 * Centro::fieis() — falha sempre que verifica quais centros ja estao
 * vinculados ao fiel (incl. ao escrever no campo de pesquisa do select).
 */
class VinculoFielCentroTest extends TestCase
{
    use RefreshDatabase;

    public function test_centro_tem_o_metodo_fieis_e_nao_fiels(): void
    {
        $this->assertTrue(method_exists(Centro::class, 'fieis'));
        $this->assertFalse(method_exists(Centro::class, 'fiels'));
    }

    public function test_relation_manager_declara_o_relacionamento_inverso_correcto(): void
    {
        $property = (new ReflectionClass(CentrosRelationManager::class))->getProperty('inverseRelationship');
        $property->setAccessible(true);

        $this->assertSame('fieis', $property->getValue());
    }

    /**
     * Mesma query que o AttachAction corre para excluir centros ja
     * vinculados ao fiel (whereDoesntHave($table->getInverseRelationship(), ...)).
     * Com o relacionamento errado ("fiels") isto lancava BadMethodCallException.
     */
    public function test_query_where_doesnt_have_fieis_nao_rebenta(): void
    {
        $fiel = Fiel::factory()->create();
        $centro = Centro::factory()->create(['paroquia_id' => $fiel->paroquia_id]);

        $resultado = Centro::query()
            ->whereDoesntHave('fieis', fn ($q) => $q->whereKey($fiel->id))
            ->get();

        $this->assertTrue($resultado->contains($centro));
    }
}
