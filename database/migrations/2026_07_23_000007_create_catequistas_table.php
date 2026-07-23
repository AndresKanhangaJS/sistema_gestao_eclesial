<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mínimo necessário para o resto do módulo funcionar — a expandir
        // quando as especificações completas de Catequista forem enviadas
        // (ver docs/modulos/catequese.md, secção 6).
        Schema::create('catequistas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paroquia_id')->constrained('paroquias')->restrictOnDelete();
            // Centro principal — catequista pode vir a dar turmas noutros centros via turma_catequista
            $table->foreignId('centro_id')->nullable()->constrained('centros')->restrictOnDelete();
            $table->foreignId('fiel_id')->nullable()->constrained('fieis')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('nome_completo', 150);
            $table->string('telefone', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catequistas');
    }
};
