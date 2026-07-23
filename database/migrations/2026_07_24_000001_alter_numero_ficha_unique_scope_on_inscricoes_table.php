<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // numero_ficha passa a ser gerado automaticamente a partir de 0001,
        // reiniciando por (paroquia_id, ano_letivo_id) — mesmo espirito das
        // fichas de papel originais ("Ficha nº ___/20__"). O unique global
        // original impediria duas paroquias/anos lectivos diferentes de
        // terem ambos o "0001".
        Schema::table('inscricoes', function (Blueprint $table) {
            $table->dropUnique(['numero_ficha']);
            $table->unique(['paroquia_id', 'ano_letivo_id', 'numero_ficha'], 'inscricoes_numero_ficha_por_ano_letivo');
        });
    }

    public function down(): void
    {
        Schema::table('inscricoes', function (Blueprint $table) {
            $table->dropUnique('inscricoes_numero_ficha_por_ano_letivo');
            $table->unique(['numero_ficha']);
        });
    }
};
