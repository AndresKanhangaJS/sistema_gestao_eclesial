<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Cria os 4 perfis RBAC definidos no CLAUDE.md.
     */
    public function run(): void
    {
        $roles = [
            'admin_geral',
            'tesoureiro_paroquial',
            'tesoureiro_centro',
            'consultor',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
