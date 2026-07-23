<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Cria os 5 perfis RBAC financeiros definidos no CLAUDE.md, por ordem
     * hierarquica, mais os 4 perfis do modulo Catequese (ver
     * docs/modulos/catequese.md, seccao 2) — estes ultimos nao herdam
     * nenhum acesso dos perfis financeiros nem vice-versa.
     *
     * - admin_geral: acesso total ao sistema. CRUD de Paroquias e regista
     *   qualquer papel, incl. administrador_paroquial (ver PermissionSeeder,
     *   UserPolicy e UserResource::papeisAtribuiveis()).
     * - administrador_paroquial: faz tudo o que esta ligado a sua propria
     *   paroquia — financeiro completo + conciliacao bancaria (paridade com
     *   tesoureiro_paroquial) e regista utilizadores (tesoureiro_paroquial/
     *   tesoureiro_centro), vinculando-os aos centros da paroquia.
     * - tesoureiro_paroquial: financeiro completo + conciliacao bancaria da
     *   sua paroquia, sem gerir utilizadores.
     * - tesoureiro_centro: apenas o seu centro, sem conciliacao.
     * - consultor: so leitura, global (todas as paroquias).
     *
     * - coordenador_catequese_paroquia: gere turmas/catequistas/catequizandos/
     *   inscricoes de todos os centros da paroquia (paridade com
     *   administrador_paroquial, mas so no modulo Catequese).
     * - coordenador_catequese_centro: idem, mas apenas do seu centro
     *   (paridade com tesoureiro_centro, mas para catequese).
     * - secretario_catequese: CRUD de catequizandos e inscricoes/matriculas
     *   do seu centro — sem acesso financeiro.
     * - tesoureiro_catequese: financeiro isolado da catequese (propinas,
     *   materiais) do seu centro — schema ainda por desenhar.
     */
    public function run(): void
    {
        $roles = [
            'admin_geral',
            'administrador_paroquial',
            'tesoureiro_paroquial',
            'tesoureiro_centro',
            'consultor',
            'coordenador_catequese_paroquia',
            'coordenador_catequese_centro',
            'secretario_catequese',
            'tesoureiro_catequese',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
