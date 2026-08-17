<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentos', function (Blueprint $table) {
            $table->foreignId('categoria_receita_id')->nullable()->after('categoria_despesa_id')
                ->constrained('categorias_receita')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('categoria_receita_id');
        });
    }
};
