<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nullable (nao NOT NULL): a inscricao ja pode ter turma activa sem
        // ter estes campos preenchidos (dados anteriores a esta migration).
        // Obrigatorio a partir de agora e reforcado no formulario Filament,
        // nao na BD, para nao arriscar falhar o backfill abaixo em dados que
        // eu nao consiga prever por completo.
        Schema::table('inscricoes', function (Blueprint $table) {
            $table->foreignId('ano_catequetico_id')->nullable()->after('ano_letivo_id')
                ->constrained('anos_catequeticos')->restrictOnDelete();
        });

        // Sacramento(s) que o catequizando persegue nesta inscricao — usado
        // para filtrar as turmas compativeis (turma_sacramento tem de bater
        // certo em conjunto, nao so parcialmente: "1º Baptismo" nao serve
        // para quem quer "1º Baptismo e Comunhão").
        Schema::create('inscricao_sacramento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscricao_id')->constrained('inscricoes')->restrictOnDelete();
            $table->foreignId('sacramento_id')->constrained('sacramentos')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['inscricao_id', 'sacramento_id']);
        });

        // Backfill a partir da turma activa de cada inscricao ja existente —
        // feito em PHP (nao SQL bruto com JOIN em UPDATE) para ser portavel
        // entre MySQL e SQLite (testes).
        DB::table('inscricoes')->orderBy('id')->get(['id'])->each(function ($inscricao) {
            $turmaAtiva = DB::table('inscricao_turma')
                ->where('inscricao_id', $inscricao->id)
                ->where('status', 'ativo')
                ->first();

            if (! $turmaAtiva) {
                return;
            }

            $turma = DB::table('turmas')->where('id', $turmaAtiva->turma_id)->first();

            if (! $turma) {
                return;
            }

            DB::table('inscricoes')->where('id', $inscricao->id)
                ->update(['ano_catequetico_id' => $turma->ano_catequetico_id]);

            $agora = now();

            DB::table('turma_sacramento')->where('turma_id', $turma->id)->get(['sacramento_id'])
                ->each(function ($ts) use ($inscricao, $agora) {
                    DB::table('inscricao_sacramento')->insertOrIgnore([
                        'inscricao_id' => $inscricao->id,
                        'sacramento_id' => $ts->sacramento_id,
                        'created_at' => $agora,
                        'updated_at' => $agora,
                    ]);
                });
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscricao_sacramento');

        Schema::table('inscricoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ano_catequetico_id');
        });
    }
};
