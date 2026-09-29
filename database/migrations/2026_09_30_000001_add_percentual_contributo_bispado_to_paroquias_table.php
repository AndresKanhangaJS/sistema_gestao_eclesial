<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Percentagem do "Contributo ao Bispado" (linha 2.3.1 do Balancete) —
 * deixa de ser fixa em 20% no codigo, passa a ser configuravel por paroquia
 * tal como o percentual_contributo_dizimo (pedido do cliente, 2026-09-30:
 * "deixa que tudo seja configuravel e nada fixo").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paroquias', function (Blueprint $table) {
            $table->decimal('percentual_contributo_bispado', 5, 2)->default(20)->after('percentual_contributo_dizimo');
        });
    }

    public function down(): void
    {
        Schema::table('paroquias', function (Blueprint $table) {
            $table->dropColumn('percentual_contributo_bispado');
        });
    }
};
