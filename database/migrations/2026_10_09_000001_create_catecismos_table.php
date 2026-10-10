<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabela GLOBAL (sem paroquia_id), mesmo molde de anos_catequeticos e
        // sacramentos: o catecismo (livro/programa) e definido pela
        // Arquidiocese e partilhado por todas as paroquias. Sem softDeletes,
        // tal como as tabelas irmas; desactiva-se via status = 'inativo'.
        Schema::create('catecismos', function (Blueprint $table) {
            $table->id();
            // Posicao na sequencia dos catecismos (1 = Deus Chama-nos ... 10 = Catecismo de Adulto)
            $table->unsignedTinyInteger('ordem')->unique();
            // "Deus Ama-nos", "Creio em Jesus Salvador"... — nao se deduz do ano catequetico
            $table->string('nome');
            $table->enum('status', ['ativo', 'inativo'])->default('ativo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // turmas.catecismo_id (restrictOnDelete) e removida pela migration
        // seguinte, que ja foi revertida antes desta.
        Schema::dropIfExists('catecismos');
    }
};
