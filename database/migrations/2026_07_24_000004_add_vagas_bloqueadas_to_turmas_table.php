<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bloqueio manual por vagas — deliberadamente NAO automatico (pedido
        // explicito do utilizador): atingir vagas_maximo so mostra um alerta,
        // quem gere a turma decide se bloqueia ou aumenta o limite.
        Schema::table('turmas', function (Blueprint $table) {
            $table->boolean('vagas_bloqueadas')->default(false)->after('vagas_maximo');
        });
    }

    public function down(): void
    {
        Schema::table('turmas', function (Blueprint $table) {
            $table->dropColumn('vagas_bloqueadas');
        });
    }
};
