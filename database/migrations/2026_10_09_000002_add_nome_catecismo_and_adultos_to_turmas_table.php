<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turmas', function (Blueprint $table) {
            // Identificador livre da turma (ex.: "Turma 04-A") — nas listas
            // reais as turmas distinguem-se por numero e ha numeros repetidos
            $table->string('nome', 100)->nullable()->after('ano_catequetico_id');
            // Catecismo usado pela turma; nullable porque turmas antigas nao o tem.
            // restrictOnDelete: nunca apagar um catecismo com turmas associadas.
            $table->foreignId('catecismo_id')->nullable()->after('nome')
                ->constrained('catecismos')->restrictOnDelete();
        });

        // Novo valor 'adultos' no enum publico_alvo
        $this->alterarEnumPublicoAlvo(['criancas', 'pre_adolescentes', 'adolescentes_jovens', 'adultos']);
    }

    public function down(): void
    {
        // O valor 'adultos' deixa de existir no enum: as linhas que o usam
        // passam primeiro para 'adolescentes_jovens' (o mais proximo) para
        // nao falhar o ALTER nem perder a turma.
        DB::table('turmas')->where('publico_alvo', 'adultos')->update(['publico_alvo' => 'adolescentes_jovens']);

        $this->alterarEnumPublicoAlvo(['criancas', 'pre_adolescentes', 'adolescentes_jovens']);

        Schema::table('turmas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('catecismo_id');
            $table->dropColumn('nome');
        });
    }

    /**
     * MySQL: ALTER ... MODIFY directo (sem depender do doctrine/dbal).
     * SQLite (testes): o enum e um varchar com CHECK, por isso o change()
     * do Laravel recria a tabela com o CHECK actualizado.
     *
     * @param  list<string>  $valores
     */
    private function alterarEnumPublicoAlvo(array $valores): void
    {
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            $lista = implode(',', array_map(fn (string $v) => "'{$v}'", $valores));
            DB::statement("ALTER TABLE turmas MODIFY publico_alvo ENUM({$lista}) NOT NULL");

            return;
        }

        Schema::table('turmas', function (Blueprint $table) use ($valores) {
            $table->enum('publico_alvo', $valores)->change();
        });
    }
};
