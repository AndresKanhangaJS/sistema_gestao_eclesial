<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paroquia_id')->constrained('paroquias')->restrictOnDelete();
            $table->foreignId('centro_id')->constrained('centros')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('fiel_id')->nullable()->constrained('fieis')->restrictOnDelete();
            $table->foreignId('metodo_pagamento_id')->constrained('metodos_pagamento')->restrictOnDelete();
            $table->foreignId('banco_id')->nullable()->constrained('bancos')->restrictOnDelete();
            $table->enum('tipo', ['dizimo', 'ofertorio', 'campanha', 'despesa_centro']);
            $table->foreignId('categoria_despesa_id')->nullable()->constrained('categorias_despesa')->restrictOnDelete();
            $table->decimal('valor', 10, 2);
            $table->unsignedSmallInteger('ano_competencia')->nullable();
            // Mês de competência do dízimo (1 a 12), validado por CHECK constraint abaixo
            $table->unsignedTinyInteger('mes_competencia')->nullable();
            $table->date('data_movimento');
            $table->string('comprovativo_path')->nullable();
            $table->string('numero_referencia_bancaria')->nullable()->unique();
            $table->enum('status_conciliacao', ['pendente', 'aprovado', 'rejeitado'])->default('pendente');
            $table->text('motivo_rejeicao')->nullable();
            // Coluna gerada: só recebe valor quando tipo = 'dizimo'. Como o MySQL ignora
            // NULLs numa unique key, isto replica uma unique key parcial
            // (fiel_id, ano_competencia, mes_competencia) apenas para dízimos.
            $table->string('dizimo_unico', 100)->nullable()->storedAs(
                "CASE WHEN tipo = 'dizimo' THEN CONCAT(fiel_id, '-', ano_competencia, '-', mes_competencia) ELSE NULL END"
            );
            $table->timestamps();
            $table->softDeletes();

            $table->unique('dizimo_unico', 'movimentos_dizimo_unico_por_mes');
        });

        // CHECK constraint: mes_competencia só pode estar entre 1 e 12 (ou nulo)
        DB::statement(
            'ALTER TABLE movimentos ADD CONSTRAINT movimentos_mes_competencia_check '
            . 'CHECK (mes_competencia IS NULL OR (mes_competencia BETWEEN 1 AND 12))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentos');
    }
};
