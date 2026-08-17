<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca utilizadores que devem trocar a senha no proximo login — sempre
 * verdadeiro em contas novas (a senha inicial e escolhida por quem regista,
 * nunca pelo proprio utilizador) e sempre que a senha e redefinida por um
 * administrador_paroquial/coordenador_centro. Ver ForcarAlteracaoSenha
 * (middleware) e AlterarSenhaObrigatoria (pagina Filament).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('deve_alterar_senha')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deve_alterar_senha');
        });
    }
};
