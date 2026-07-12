<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nulo para papeis que nao se restringem a um centro especifico;
            // preenchido para tesoureiro_centro (CLAUDE.md: "apenas o seu centro").
            $table->foreignId('centro_id')
                ->nullable()
                ->after('paroquia_id')
                ->constrained('centros')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('centro_id');
        });
    }
};
