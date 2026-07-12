<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nulo para papeis globais (admin_geral, consultor); preenchido para
            // tesoureiro_paroquial/tesoureiro_centro, usado pela ParoquiaScope.
            $table->foreignId('paroquia_id')
                ->nullable()
                ->after('id')
                ->constrained('paroquias')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paroquia_id');
        });
    }
};
