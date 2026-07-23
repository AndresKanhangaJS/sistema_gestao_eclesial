<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabela partilhada entre todas as paróquias (programa oficial da
        // Arquidiocese) — sem paroquia_id, gerida por admin_geral.
        Schema::create('anos_catequeticos', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('ordem')->unique();
            // "1º Ano", "2º Ano"...
            $table->string('nome');
            $table->enum('status', ['ativo', 'inativo'])->default('ativo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anos_catequeticos');
    }
};
