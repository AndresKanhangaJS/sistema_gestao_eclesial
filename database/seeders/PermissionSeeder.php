<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Gera as permissions do Filament Shield (uma por Resource/Page/Widget) e
 * associa-as aos 4 roles do CLAUDE.md, espelhando exactamente o que cada
 * Policy ja concede (ParoquiaPolicy, CentroPolicy, FielPolicy,
 * MovimentoPolicy, CategoriaDespesaPolicy).
 *
 * IMPORTANTE: isto NAO substitui as Policies - a autorizacao real continua a
 * ser feita por elas (hasRole() + regras de negocio). Isto so preenche a
 * tabela permissions/role_has_permissions para o ecra de gestao de Roles do
 * Shield (/admin/shield/roles) ficar correcto.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // --option=permissions (nunca 'policies_and_permissions') garante que
        // nenhum ficheiro de Policy e gerado/sobrescrito.
        Artisan::call('shield:generate', [
            '--all' => true,
            '--option' => 'permissions',
            '--panel' => 'admin',
        ]);

        // Abilities customizadas das Policies que o Shield nao gera
        // automaticamente (nao sao CRUD standard).
        Permission::firstOrCreate(['name' => 'aprovar_movimento', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'rejeitar_movimento', 'guard_name' => 'web']);

        // DatabaseSeeder usa WithoutModelEvents, que impede o spatie/permission
        // de invalidar sozinho a sua cache interna quando cria permissions
        // novas. Forcamos a limpeza para o syncPermissions() abaixo ver tudo.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assignPermissions('admin_geral', Permission::pluck('name')->all());

        $this->assignPermissions('consultor', [
            'view_paroquia', 'view_any_paroquia',
            'view_centro', 'view_any_centro',
            'view_fiel', 'view_any_fiel',
            'view_movimento', 'view_any_movimento',
            'view_categoria::despesa', 'view_any_categoria::despesa',
            'view_role', 'view_any_role',
            'page_DemonstrativoArrecadacao',
            'page_RastreabilidadeBancaria',
            'page_AuditoriaRepassesInterCentro',
            'page_BalancoReceitasDespesas',
            'page_FieisPorSituacao',
            'widget_ArrecadacaoBarChart', 'widget_ArrecadacaoPieChart',
        ]);

        $this->assignPermissions('tesoureiro_paroquial', [
            // Centro: CentroPolicy nunca permite delete a este role.
            'view_centro', 'view_any_centro', 'create_centro', 'update_centro',
            // Fiel: FielPolicy permite tudo excepto forceDelete.
            'view_fiel', 'view_any_fiel', 'create_fiel', 'update_fiel',
            'delete_fiel', 'delete_any_fiel', 'restore_fiel',
            // Movimento: conciliacao bancaria e exclusiva deste role.
            'view_movimento', 'view_any_movimento', 'create_movimento', 'update_movimento',
            'aprovar_movimento', 'rejeitar_movimento',
            // CategoriaDespesa: CategoriaDespesaPolicy permite tudo excepto restore/forceDelete.
            'view_categoria::despesa', 'view_any_categoria::despesa',
            'create_categoria::despesa', 'update_categoria::despesa',
            'delete_categoria::despesa', 'delete_any_categoria::despesa',
            // Paginas: todos os relatorios excepto o Log de Auditoria (so admin_geral).
            'page_MatrizDizimos', 'page_MatrizAssiduidadeReport',
            'page_DemonstrativoArrecadacao', 'page_RastreabilidadeBancaria',
            'page_AuditoriaRepassesInterCentro', 'page_BalancoReceitasDespesas',
            'page_FieisPorSituacao',
            'widget_ArrecadacaoBarChart', 'widget_ArrecadacaoPieChart',
        ]);

        $this->assignPermissions('tesoureiro_centro', [
            // Centro: so leitura (CentroPolicy nao da create/update/delete a este role).
            'view_centro', 'view_any_centro',
            // Fiel: so leitura (FielPolicy restringe create/update/delete ao paroquial).
            'view_fiel', 'view_any_fiel',
            // Movimento: cria/edita no seu centro, mas nunca aprova/rejeita.
            'view_movimento', 'view_any_movimento', 'create_movimento', 'update_movimento',
            // Paginas restritas ao seu centro (sem Rastreabilidade Bancaria,
            // Repasses Inter-Centro nem Log de Auditoria).
            'page_MatrizDizimos', 'page_MatrizAssiduidadeReport',
            'page_DemonstrativoArrecadacao', 'page_BalancoReceitasDespesas',
            'page_FieisPorSituacao',
            'widget_ArrecadacaoBarChart', 'widget_ArrecadacaoPieChart',
        ]);
    }

    private function assignPermissions(string $roleName, array $permissions): void
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $role->syncPermissions($permissions);
    }
}
