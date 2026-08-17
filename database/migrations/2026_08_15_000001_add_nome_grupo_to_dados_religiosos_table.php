<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dados_religiosos', function (Blueprint $table) {
            $table->string('nome_grupo', 150)->nullable()->after('pertence_grupo');
        });
    }

    public function down(): void
    {
        Schema::table('dados_religiosos', function (Blueprint $table) {
            $table->dropColumn('nome_grupo');
        });
    }
};
