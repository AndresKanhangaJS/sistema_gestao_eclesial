<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bancos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paroquia_id')->constrained('paroquias')->restrictOnDelete();
            $table->string('nome_banco');
            // Sigla do banco, ex: BFA, BAI, BIC
            $table->string('sigla')->nullable();
            $table->string('numero_conta');
            $table->string('iban')->nullable();
            $table->enum('status', ['ativo', 'inativo'])->default('ativo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bancos');
    }
};
