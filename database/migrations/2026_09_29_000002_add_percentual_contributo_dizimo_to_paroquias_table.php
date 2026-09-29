<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Percentagem do "Contributo do Dizimo" (linha 2.3.2 do Balancete) que a
 * paroquia repassa a Arquidiocese sobre a receita de Congruas/Dizimo do mes
 * — configuravel por paroquia porque o modelo oficial da Arquidiocese
 * mostra "(?%)" sem valor fixo (pedido do cliente, 2026-09-29).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paroquias', function (Blueprint $table) {
            $table->decimal('percentual_contributo_dizimo', 5, 2)->default(60)->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('paroquias', function (Blueprint $table) {
            $table->dropColumn('percentual_contributo_dizimo');
        });
    }
};
