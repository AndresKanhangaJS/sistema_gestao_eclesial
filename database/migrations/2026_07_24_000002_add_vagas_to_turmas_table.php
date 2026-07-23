<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turmas', function (Blueprint $table) {
            // Nao ha ainda bloqueio automatico ao atingir vagas_maximo (pedido
            // explicito do utilizador para "mais para a frente") — por agora
            // sao so campos informativos/de planeamento, mostrados na lista.
            $table->unsignedSmallInteger('vagas_minimo')->nullable()->after('tipo');
            $table->unsignedSmallInteger('vagas_maximo')->nullable()->after('vagas_minimo');
        });
    }

    public function down(): void
    {
        Schema::table('turmas', function (Blueprint $table) {
            $table->dropColumn(['vagas_minimo', 'vagas_maximo']);
        });
    }
};
