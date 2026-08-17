<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logotipo da paróquia, carregado pelo admin_geral (único papel com CRUD de
 * Paróquias — CLAUDE.md) e reaproveitado no cabeçalho dos PDFs exportados
 * (ver App\Models\Paroquia::logoBase64(), resources/views/pdfs/layout.blade.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paroquias', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('paroquias', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
