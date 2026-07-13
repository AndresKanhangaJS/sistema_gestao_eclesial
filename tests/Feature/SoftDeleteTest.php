<?php

namespace Tests\Feature;

use App\Models\Banco;
use App\Models\CategoriaDespesa;
use App\Models\Fiel;
use App\Models\Movimento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CLAUDE.md, regras ABSOLUTAS 1 e 2: soft delete obrigatorio em tabelas
 * financeiras, nunca DELETE fisico. categorias_despesa e bancos ganharam
 * soft delete nesta ronda de correcoes (antes so tinham DeleteAction "fisico"
 * porque a coluna deleted_at nem existia).
 */
class SoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_movimento_apagado_nao_aparece_em_queries_normais_mas_continua_na_bd(): void
    {
        $movimento = Movimento::factory()->create();

        $movimento->delete();

        $this->assertNull(Movimento::find($movimento->id));
        $this->assertNotNull(Movimento::withTrashed()->find($movimento->id));
        $this->assertDatabaseHas('movimentos', ['id' => $movimento->id]);
    }

    public function test_fiel_apagado_e_soft_delete(): void
    {
        $fiel = Fiel::factory()->create();

        $fiel->delete();

        $this->assertNull(Fiel::find($fiel->id));
        $this->assertNotNull(Fiel::withTrashed()->find($fiel->id));
    }

    public function test_categoria_despesa_apagada_e_soft_delete(): void
    {
        $categoria = CategoriaDespesa::factory()->create();

        $categoria->delete();

        $this->assertNull(CategoriaDespesa::find($categoria->id));
        $this->assertNotNull(CategoriaDespesa::withTrashed()->find($categoria->id));
        $this->assertDatabaseHas('categorias_despesa', ['id' => $categoria->id]);
    }

    public function test_banco_apagado_e_soft_delete(): void
    {
        $banco = Banco::factory()->create();

        $banco->delete();

        $this->assertNull(Banco::find($banco->id));
        $this->assertNotNull(Banco::withTrashed()->find($banco->id));
        $this->assertDatabaseHas('bancos', ['id' => $banco->id]);
    }
}
