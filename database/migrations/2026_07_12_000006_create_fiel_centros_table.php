<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiel_centros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiel_id')->constrained('fieis')->restrictOnDelete();
            $table->foreignId('centro_id')->constrained('centros')->restrictOnDelete();
            $table->date('data_inicio');
            $table->date('data_fim')->nullable();
            // Indica se este é o centro principal do fiel no período
            $table->boolean('principal')->default(false);
            $table->string('motivo_transferencia')->nullable();
            $table->timestamps();

            $table->unique(['fiel_id', 'centro_id', 'data_inicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiel_centros');
    }
};
