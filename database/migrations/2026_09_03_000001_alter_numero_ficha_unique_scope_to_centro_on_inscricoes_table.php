<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Correcao de modelo (pedido do utilizador, 2026-09-03): os centros
        // sao independentes entre si — o numero de ficha tinha de reiniciar
        // por (centro_id, ano_letivo_id), nao (so) por
        // (paroquia_id, ano_letivo_id). Com o scope antigo, dois centros da
        // mesma paroquia nunca podiam ter ambos o "F0001" no mesmo ano
        // lectivo — cada centro tem a sua propria numeracao de fichas, tal
        // como as fichas de papel originais.
        //
        // paroquia_id continua no unique (nao so centro_id): tecnicamente
        // redundante — um centro pertence sempre a uma unica paroquia, nunca
        // muda — mas o isolamento entre paroquias diferentes na mesma BD
        // fica explicito na propria constraint, no mesmo espirito de
        // "defesa em profundidade" ja usado no resto do projecto
        // (ParoquiaScope em quase todos os models, mesmo quando derivavel
        // via join). Pedido explicito do utilizador nesta correcao.
        //
        // Armadilha evitada (ja vista ao contrario na primeira tentativa
        // desta migration): com paroquia_id como primeira coluna do unique
        // novo, este continua a cobrir a FK de paroquia_id, e centro_id nao
        // e tocado de todo — mantem o seu indice de FK proprio intacto.
        // Se o unique tivesse centro_id como primeira coluna (como na
        // primeira tentativa), o MySQL absorve silenciosamente o indice
        // dedicado da FK de centro_id, e dropar esse unique mais tarde falha
        // com o erro 1553 sem um indice extra explicito — ver secc. 28 da
        // documentacao do modulo para o relato completo dessa armadilha.
        Schema::table('inscricoes', function (Blueprint $table) {
            $table->unique(['paroquia_id', 'centro_id', 'ano_letivo_id', 'numero_ficha'], 'inscricoes_numero_ficha_por_centro_ano_letivo');
        });

        Schema::table('inscricoes', function (Blueprint $table) {
            $table->dropUnique('inscricoes_numero_ficha_por_ano_letivo');
        });
    }

    public function down(): void
    {
        Schema::table('inscricoes', function (Blueprint $table) {
            $table->unique(['paroquia_id', 'ano_letivo_id', 'numero_ficha'], 'inscricoes_numero_ficha_por_ano_letivo');
        });

        Schema::table('inscricoes', function (Blueprint $table) {
            $table->dropUnique('inscricoes_numero_ficha_por_centro_ano_letivo');
        });
    }
};
