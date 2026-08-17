<?php

/**
 * Sobrescreve so o nome do grupo de navegacao do pacote
 * bezhansalleh/filament-shield para pt_PT (locale da app — config/app.php)
 * — pedido do cliente: so o menu lateral, nada mais do ecra do Shield.
 * O pacote so traz traducoes en/pt_BR; sem este ficheiro o Laravel cai no
 * fallback_locale e mostra "Filament Shield" (nome tecnico do pacote, sem
 * significado para quem usa o sistema — o pt_BR do proprio pacote tambem
 * deixa esta chave por traduzir, daí aparecer mesmo com fallback_locale em
 * pt_BR).
 *
 * So esta chave e sobrescrita de proposito — as restantes strings do Shield
 * (colunas, campos, mensagens) continuam a resolver via o fallback_locale
 * automatico do Laravel por chave em falta, tal como pedido.
 *
 * IMPORTANTE: o caminho correcto e lang/vendor/... (nao resources/lang/...).
 * Application::bindPathsInContainer() usa resources/lang em vez de lang/
 * sempre que resources/lang existir como pasta — mesmo vazia. Criar
 * qualquer coisa dentro de resources/lang muda o langPath activo de toda a
 * aplicacao e quebra lang/pt_PT/validation.php (as mensagens de validacao em
 * português deixam de carregar).
 */
return [
    'nav.group' => 'Papéis e Permissões',
];
