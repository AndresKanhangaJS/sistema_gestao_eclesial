<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turmas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paroquia_id')->constrained('paroquias')->restrictOnDelete();
            $table->foreignId('centro_id')->constrained('centros')->restrictOnDelete();
            $table->foreignId('ano_letivo_id')->constrained('anos_letivos')->restrictOnDelete();
            $table->foreignId('ano_catequetico_id')->constrained('anos_catequeticos')->restrictOnDelete();
            $table->enum('publico_alvo', ['criancas', 'pre_adolescentes', 'adolescentes_jovens']);
            $table->enum('periodo', ['manha', 'tarde', 'noite']);
            // Horário fica na própria turma (não numa tabela turnos à parte)
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->enum('tipo', ['normal', 'intensiva'])->default('normal');
            $table->enum('status', ['ativo', 'inativo', 'encerrada'])->default('ativo');
            $table->timestamps();
            // Nunca apagar fisicamente: inscricoes referencia turma_id para histórico permanente
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turmas');
    }
};
