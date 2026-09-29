<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tagueia CategoriaReceita/CategoriaDespesa a uma linha fixa do relatorio
 * "Balancete" (docs/modulos/07-relatorios-exportacao.md) — uma categoria
 * tagueada cai sempre nessa linha; sem tag, cai no residuo (Receitas
 * Diversas/Outras Despesas). Ver App\Support\LinhasBalancete para o catalogo
 * de codigos validos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorias_receita', function (Blueprint $table) {
            $table->string('linha_balancete', 10)->nullable()->after('nome');
        });

        Schema::table('categorias_despesa', function (Blueprint $table) {
            $table->string('linha_balancete', 10)->nullable()->after('nome');
        });
    }

    public function down(): void
    {
        Schema::table('categorias_receita', function (Blueprint $table) {
            $table->dropColumn('linha_balancete');
        });

        Schema::table('categorias_despesa', function (Blueprint $table) {
            $table->dropColumn('linha_balancete');
        });
    }
};
