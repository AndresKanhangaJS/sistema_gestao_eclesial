<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regista o fecho mensal do Balancete por centro (cada Movimento pertence a
 * 1 so centro, Modulo 4 — nao ha "fecho agregado de paroquia" no schema).
 * Guarda os totais oficiais do mes e o saldo transitado, e impede fechar o
 * mesmo mes duas vezes (unique paroquia_id+centro_id+ano+mes) — evita
 * duplicar os Movimentos de repasse (Bispado/Dizimo) gerados pelo
 * BalanceteService::fechar().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balancetes_mensais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paroquia_id')->constrained('paroquias')->restrictOnDelete();
            $table->foreignId('centro_id')->constrained('centros')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('ano');
            $table->unsignedTinyInteger('mes');
            $table->decimal('saldo_mes_anterior', 12, 2)->default(0);
            $table->decimal('total_receitas', 12, 2)->default(0);
            $table->decimal('total_despesas', 12, 2)->default(0);
            $table->decimal('saldo_mes', 12, 2)->default(0);
            $table->foreignId('movimento_bispado_id')->nullable()->constrained('movimentos')->restrictOnDelete();
            $table->foreignId('movimento_dizimo_id')->nullable()->constrained('movimentos')->restrictOnDelete();
            $table->timestamp('fechado_em');
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['paroquia_id', 'centro_id', 'ano', 'mes'], 'balancetes_mensais_unico_por_centro_mes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balancetes_mensais');
    }
};
