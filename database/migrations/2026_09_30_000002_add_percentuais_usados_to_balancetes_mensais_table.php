<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Congela as percentagens de Bispado/Dizimo usadas no momento do fecho
 * (App\Services\BalanceteService::fechar()) — sem isto, reabrir o relatorio
 * de um mes ja fechado recalculava os contributos com a percentagem ACTUAL
 * da paroquia, divergindo do Movimento de repasse real que ja foi gerado e
 * possivelmente ja transferido ao banco. Bug reportado pelo cliente
 * (2026-09-30): "se um relatorio sair com 60% e depois eu editar a
 * percentagem, esse relatorio vai mudar?".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('balancetes_mensais', function (Blueprint $table) {
            $table->decimal('percentual_bispado_usado', 5, 2)->after('saldo_mes');
            $table->decimal('percentual_dizimo_usado', 5, 2)->after('percentual_bispado_usado');
        });
    }

    public function down(): void
    {
        Schema::table('balancetes_mensais', function (Blueprint $table) {
            $table->dropColumn(['percentual_bispado_usado', 'percentual_dizimo_usado']);
        });
    }
};
