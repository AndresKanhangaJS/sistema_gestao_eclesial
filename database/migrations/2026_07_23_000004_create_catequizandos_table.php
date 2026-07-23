<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catequizandos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paroquia_id')->constrained('paroquias')->restrictOnDelete();
            // Centro actual do catequizando — histórico completo em catequizando_centros
            $table->foreignId('centro_id')->constrained('centros')->restrictOnDelete();
            // Vínculo opcional: catequizando não precisa de ser um Fiel já cadastrado
            $table->foreignId('fiel_id')->nullable()->constrained('fieis')->restrictOnDelete();
            $table->string('nome_completo', 200);
            $table->string('nome_pai', 150)->nullable();
            $table->string('nome_mae', 150)->nullable();
            $table->string('profissao', 100)->nullable();
            $table->string('municipio_nascimento', 100)->nullable();
            $table->string('provincia_nascimento', 100)->nullable();
            $table->string('pais_nascimento', 80)->default('Angola');
            $table->date('data_nascimento');
            $table->enum('sexo', ['M', 'F']);
            $table->string('residencia', 150)->nullable();
            $table->string('rua_numero', 20)->nullable();
            $table->string('edificio', 80)->nullable();
            $table->string('casa_ap', 20)->nullable();
            // Nº de identificação (BI angolano) — único quando preenchido
            $table->string('numero_identificacao', 30)->nullable()->unique();
            $table->string('telefone', 20)->nullable();
            $table->string('telefone_casa', 20)->nullable();
            $table->string('email', 100)->nullable();
            $table->enum('status', ['ativo', 'inativo'])->default('ativo');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catequizandos');
    }
};
