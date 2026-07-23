<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Combina uma turma com 1+ sacramentos: "1º Baptismo", "1º Baptismo e
        // Comunhão", "1º Comunhão" ficam turmas distintas do mesmo ano_catequetico.
        Schema::create('turma_sacramento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('turmas')->restrictOnDelete();
            $table->foreignId('sacramento_id')->constrained('sacramentos')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['turma_id', 'sacramento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turma_sacramento');
    }
};
