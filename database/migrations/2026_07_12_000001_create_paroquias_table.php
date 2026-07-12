<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paroquias', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            $table->string('diocese');
            $table->string('morada');
            $table->string('responsavel');
            $table->string('email_contato');
            $table->string('telefone');
            $table->enum('status', ['ativo', 'inativo'])->default('ativo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paroquias');
    }
};
