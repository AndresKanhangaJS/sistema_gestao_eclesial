<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Um catequista pode ter várias turmas; uma turma pode ter vários catequistas (titular/auxiliar)
        Schema::create('turma_catequista', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('turmas')->restrictOnDelete();
            $table->foreignId('catequista_id')->constrained('catequistas')->restrictOnDelete();
            $table->enum('papel', ['titular', 'auxiliar'])->default('titular');
            $table->date('data_inicio');
            $table->date('data_fim')->nullable();
            $table->timestamps();

            $table->unique(['turma_id', 'catequista_id', 'data_inicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turma_catequista');
    }
};
