<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A ficha de inscrição do catequizando para um ano_letivo — não tem
        // turma_id directo (ver inscricao_turma) para que trocar de turma
        // nunca precise de mexer nesta tabela.
        Schema::create('inscricoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paroquia_id')->constrained('paroquias')->restrictOnDelete();
            // Forçado a partir do centro do utilizador autenticado, nunca escolhido livremente no form
            $table->foreignId('centro_id')->constrained('centros')->restrictOnDelete();
            $table->foreignId('catequizando_id')->constrained('catequizandos')->restrictOnDelete();
            $table->foreignId('ano_letivo_id')->constrained('anos_letivos')->restrictOnDelete();
            // Catequista que atendeu/processou a ficha (não é necessariamente quem lecciona a turma)
            $table->foreignId('catequista_id')->nullable()->constrained('catequistas')->restrictOnDelete();
            // Trilha de progressão entre anos lectivos: aponta para a inscrição do ano anterior
            $table->foreignId('inscricao_anterior_id')->nullable()->constrained('inscricoes')->restrictOnDelete();
            // nova (1ª vez) | confirmacao (progressão de ano lectivo)
            $table->enum('tipo', ['nova', 'confirmacao']);
            $table->string('numero_ficha')->unique();
            $table->date('data_atendimento');
            $table->enum('estado', ['inscrito', 'aprovado', 'reprovado', 'desistente', 'cancelado'])
                ->default('inscrito');
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscricoes');
    }
};
