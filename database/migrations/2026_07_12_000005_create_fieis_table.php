<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fieis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paroquia_id')->constrained('paroquias')->restrictOnDelete();
            $table->string('nome');
            // Código único do dizimista, usado para identificação nos lançamentos
            $table->string('codigo_dizimista')->unique();
            $table->string('telefone')->nullable();
            $table->string('email')->nullable();
            $table->date('data_nascimento')->nullable();
            $table->enum('status', ['ativo', 'inativo'])->default('ativo');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fieis');
    }
};
