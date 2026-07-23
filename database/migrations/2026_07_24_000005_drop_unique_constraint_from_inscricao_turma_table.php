<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A unique (inscricao_id, turma_id, data_inicio) partia do princípio
        // de que a data (sem hora) já distinguia episódios de colocação
        // diferentes — falso quando se remove e reactiva na mesma turma no
        // mesmo dia (bug real, ao reactivar). A regra "só uma linha
        // status=ativo por inscricao" já é garantida pela aplicação
        // (fecha-se sempre a anterior antes de criar a nova), não depende
        // desta unique key — troca-se por um índice simples para as
        // queries por (inscricao_id, turma_id) continuarem rápidas.
        // MySQL recusa apagar um index usado por uma foreign key (erro 1553)
        // sem primeiro existir outro index que a cubra — por isso o novo
        // index tem de ser criado ANTES de apagar o antigo, nunca na mesma
        // ordem inversa.
        Schema::table('inscricao_turma', function (Blueprint $table) {
            $table->index(['inscricao_id', 'turma_id']);
        });

        Schema::table('inscricao_turma', function (Blueprint $table) {
            $table->dropUnique(['inscricao_id', 'turma_id', 'data_inicio']);
        });
    }

    public function down(): void
    {
        Schema::table('inscricao_turma', function (Blueprint $table) {
            $table->unique(['inscricao_id', 'turma_id', 'data_inicio']);
        });

        Schema::table('inscricao_turma', function (Blueprint $table) {
            $table->dropIndex(['inscricao_id', 'turma_id']);
        });
    }
};
