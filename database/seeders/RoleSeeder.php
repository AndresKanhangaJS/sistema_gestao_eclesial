<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Cria os 5 perfis RBAC definidos no CLAUDE.md, por ordem hierarquica:
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
     */
    public function run(): void
    {
        $roles = [
            'admin_geral',
            'administrador_paroquial',
            'tesoureiro_paroquial',
            'tesoureiro_centro',
            'consultor',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
