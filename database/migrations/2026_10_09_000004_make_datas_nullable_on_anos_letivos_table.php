<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anos_letivos', function (Blueprint $table) {
            // Datas do ano lectivo podem ainda nao estar confirmadas na carga inicial
            $table->date('data_inicio')->nullable()->change();
            $table->date('data_fim')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Para voltar a NOT NULL, as datas em falta recebem um valor
        // sentinela (data de criacao do registo), sem apagar anos lectivos.
        foreach (DB::table('anos_letivos')->where(fn ($q) => $q->whereNull('data_inicio')->orWhereNull('data_fim'))->get() as $ano) {
            $inicio = $ano->data_inicio ?? (substr((string) $ano->created_at, 0, 10) ?: date('Y-m-d'));
            DB::table('anos_letivos')->where('id', $ano->id)->update([
                'data_inicio' => $inicio,
                'data_fim' => $ano->data_fim ?? $inicio,
            ]);
        }

        Schema::table('anos_letivos', function (Blueprint $table) {
            $table->date('data_inicio')->nullable(false)->change();
            $table->date('data_fim')->nullable(false)->change();
        });
    }
};
