<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela padrao do Laravel para notificacoes persistidas em BD (canal
 * "database") — nunca tinha sido criada porque a unica notificacao do
 * projecto ate agora (ComprovativoPendenteNotification) so usa o canal
 * "mail". O Filament usa o canal "database" internamente para notificacoes
 * como a de conclusao de importacao (ImportAction) e o sino de notificacoes
 * do painel — sem esta tabela, esses jobs falham em silencio (ficam em
 * failed_jobs) e o utilizador nunca ve a confirmacao.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
