<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dados_religiosos', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete (não cascade): dado sensível, nunca apagar em cascata — CLAUDE.md
            $table->foreignId('catequizando_id')->unique()->constrained('catequizandos')->restrictOnDelete();
            $table->string('paroquia_baptismo', 150)->nullable();
            $table->date('data_baptismo')->nullable();
            $table->string('pais_baptismo', 80)->nullable();
            $table->string('paroquia_comunhao', 150)->nullable();
            $table->date('data_comunhao')->nullable();
            $table->string('pais_comunhao', 80)->nullable();
            $table->string('padrinho_nome', 150)->nullable();
            $table->string('padrinho_telefone', 20)->nullable();
            $table->string('madrinha_nome', 150)->nullable();
            $table->string('madrinha_telefone', 20)->nullable();
            $table->string('paroquia_transferencia', 150)->nullable();
            $table->year('ano_transferencia')->nullable();
            $table->boolean('pertence_grupo')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dados_religiosos');
    }
};
