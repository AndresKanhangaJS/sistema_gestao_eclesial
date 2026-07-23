<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anos_letivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paroquia_id')->constrained('paroquias')->restrictOnDelete();
            // Ciclo anual da catequese, ex: "2026/2027" — não confundir com ano_catequetico (progressão)
            $table->string('nome');
            $table->date('data_inicio');
            $table->date('data_fim');
            // Só deve existir um 'em_curso' por paróquia de cada vez — validado na aplicação
            $table->enum('status', ['em_curso', 'encerrado'])->default('em_curso');
            $table->timestamps();

            $table->unique(['paroquia_id', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anos_letivos');
    }
};
