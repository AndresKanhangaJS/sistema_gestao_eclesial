<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Histórico de centros do catequizando — mesmo molde de fiel_centros.
        // Mudar de centro implica sempre mudar de turma (ver docs/modulos/catequese.md, secção 7.1).
        Schema::create('catequizando_centros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catequizando_id')->constrained('catequizandos')->restrictOnDelete();
            $table->foreignId('centro_id')->constrained('centros')->restrictOnDelete();
            $table->date('data_inicio');
            $table->date('data_fim')->nullable();
            $table->string('motivo_transferencia')->nullable();
            $table->timestamps();

            // Nome explicito e mais curto: o nome automatico (catequizando_centros_
            // catequizando_id_centro_id_data_inicio_unique, 65 chars) excede o limite
            // de identificador do MySQL (64 chars) e faz a migration falhar.
            $table->unique(['catequizando_id', 'centro_id', 'data_inicio'], 'catequizando_centros_unico_periodo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catequizando_centros');
    }
};
