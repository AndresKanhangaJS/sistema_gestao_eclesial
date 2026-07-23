<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabela partilhada entre todas as paróquias, mesmo espírito de anos_catequeticos.
        Schema::create('sacramentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('ordem')->unique();
            // Baptismo, Comunhão, Crisma
            $table->string('nome')->unique();
            $table->enum('status', ['ativo', 'inativo'])->default('ativo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sacramentos');
    }
};
