<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catequizandos', function (Blueprint $table) {
            // As listas reais so trazem o ano de nascimento ou a idade, nao a data completa
            $table->date('data_nascimento')->nullable()->change();
            // true = data estimada (ex.: AAAA-07-01 a partir do ano, ou 2026 - idade)
            $table->boolean('data_nascimento_aproximada')->default(false)->after('data_nascimento');
        });
    }

    public function down(): void
    {
        // Para voltar a NOT NULL, as datas em falta recebem um valor
        // sentinela (nao se apaga nenhum catequizando).
        DB::table('catequizandos')->whereNull('data_nascimento')->update(['data_nascimento' => '1900-01-01']);

        Schema::table('catequizandos', function (Blueprint $table) {
            $table->dropColumn('data_nascimento_aproximada');
            $table->date('data_nascimento')->nullable(false)->change();
        });
    }
};
