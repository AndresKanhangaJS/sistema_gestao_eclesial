<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * categorias_despesa e bancos sao tabelas financeiras (ligadas a movimentos)
 * e por isso exigem soft delete (CLAUDE.md, regra ABSOLUTA nº1) — faltava
 * desde a criacao inicial das tabelas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorias_despesa', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('bancos', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('categorias_despesa', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('bancos', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
