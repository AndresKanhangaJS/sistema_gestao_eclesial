<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Colocação de uma inscrição numa turma, com histórico próprio — trocar
        // de turma cria uma nova linha aqui (status=ativo) e fecha a anterior
        // (status=transferido/removido), sem tocar em inscricoes nem em turmas.
        Schema::create('inscricao_turma', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscricao_id')->constrained('inscricoes')->restrictOnDelete();
            $table->foreignId('turma_id')->constrained('turmas')->restrictOnDelete();
            $table->enum('status', ['ativo', 'transferido', 'removido'])->default('ativo');
            $table->date('data_inicio');
            $table->date('data_fim')->nullable();
            $table->string('motivo')->nullable();
            $table->timestamps();

            $table->unique(['inscricao_id', 'turma_id', 'data_inicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscricao_turma');
    }
};
