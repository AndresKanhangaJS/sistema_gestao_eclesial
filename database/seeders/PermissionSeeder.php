<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Gera as permissions do Filament Shield (uma por Resource/Page/Widget) e
 * associa-as aos 5 roles do CLAUDE.md, espelhando exactamente o que cada
 * Policy ja concede (ParoquiaPolicy, CentroPolicy, FielPolicy, BancoPolicy,
 * MovimentoPolicy, CategoriaDespesaPolicy, UserPolicy).
 *
 * Hierarquia (reconciliada com o CLAUDE.md):
 * - admin_geral: acesso total — CRUD de Paroquias e regista qualquer papel,
 *   incl. administrador_paroquial.
 * - administrador_paroquial: tudo o que o tesoureiro_paroquial faz (financeiro
 *   completo + conciliacao) MAIS gestao de utilizadores da propria paroquia
 *   (regista tesoureiro_paroquial/tesoureiro_centro e vincula-os a centros).
 * - tesoureiro_paroquial: financeiro completo + conciliacao bancaria.
 * - tesoureiro_centro: apenas o seu centro, sem conciliacao nem gestao de utilizadores.
 * - consultor: so leitura, global.
 *
 * IMPORTANTE: isto NAO substitui as Policies - a autorizacao real continua a
 * ser feita por elas (hasRole() + regras de negocio). Isto so preenche a
 * tabela permissions/role_has_permissions para o ecra de gestao de Roles do
 * Shield (/admin/shield/roles) ficar correcto.
 */
class PermissionSeeder extends Seeder
{
    /**
     * Paginas/widgets de relatorio comuns a quem tem financeiro completo
     * (administrador_paroquial e tesoureiro_paroquial).
     */
    private const PAGINAS_FINANCEIRO_COMPLETO = [
        'page_MatrizDizimos', 'page_MatrizAssiduidadeReport',
        'page_DemonstrativoArrecadacao', 'page_RastreabilidadeBancaria',
        'page_AuditoriaRepassesInterCentro', 'page_BalancoReceitasDespesas',
        'page_FieisPorSituacao',
        'widget_ArrecadacaoBarChart', 'widget_ArrecadacaoPieChart', 'widget_EstatisticasGeraisWidget',
    ];

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
            'view_banco', 'view_any_banco',
            'view_role', 'view_any_role',
            'page_DemonstrativoArrecadacao',
            'page_RastreabilidadeBancaria',
            'page_AuditoriaRepassesInterCentro',
            'page_BalancoReceitasDespesas',
            'page_FieisPorSituacao',
            'widget_ArrecadacaoBarChart', 'widget_ArrecadacaoPieChart', 'widget_EstatisticasGeraisWidget',
        ]);

        // administrador_paroquial: mesmo alcance financeiro/estrutural do
        // tesoureiro_paroquial (ver GESTORES_PAROQUIA nas Policies) mais a
        // gestao de utilizadores da propria paroquia (UserPolicy).
        $this->assignPermissions('administrador_paroquial', [
            'view_centro', 'view_any_centro', 'create_centro', 'update_centro',
            'view_fiel', 'view_any_fiel', 'create_fiel', 'update_fiel',
            'delete_fiel', 'delete_any_fiel', 'restore_fiel',
            'view_movimento', 'view_any_movimento', 'create_movimento', 'update_movimento',
            'aprovar_movimento', 'rejeitar_movimento',
            'view_categoria::despesa', 'view_any_categoria::despesa',
            'create_categoria::despesa', 'update_categoria::despesa',
            'delete_categoria::despesa', 'delete_any_categoria::despesa',
            'view_banco', 'view_any_banco', 'create_banco', 'update_banco',
            'delete_banco', 'delete_any_banco',
            'view_user', 'view_any_user', 'create_user', 'update_user',
            ...self::PAGINAS_FINANCEIRO_COMPLETO,
        ]);

        $this->assignPermissions('tesoureiro_paroquial', [
            // Centro: CentroPolicy nunca permite delete a este role.
            'view_centro', 'view_any_centro', 'create_centro', 'update_centro',
            // Fiel: FielPolicy permite tudo excepto forceDelete.
            'view_fiel', 'view_any_fiel', 'create_fiel', 'update_fiel',
            'delete_fiel', 'delete_any_fiel', 'restore_fiel',
            // Movimento: conciliacao bancaria e exclusiva deste role (e do administrador_paroquial).
            'view_movimento', 'view_any_movimento', 'create_movimento', 'update_movimento',
            'aprovar_movimento', 'rejeitar_movimento',
            // CategoriaDespesa: CategoriaDespesaPolicy permite tudo excepto restore/forceDelete.
            'view_categoria::despesa', 'view_any_categoria::despesa',
            'create_categoria::despesa', 'update_categoria::despesa',
            'delete_categoria::despesa', 'delete_any_categoria::despesa',
            // Banco: BancoPolicy permite tudo excepto restore/forceDelete.
            'view_banco', 'view_any_banco', 'create_banco', 'update_banco',
            'delete_banco', 'delete_any_banco',
            ...self::PAGINAS_FINANCEIRO_COMPLETO,
        ]);

        $this->assignPermissions('tesoureiro_centro', [
            // Centro: so leitura (CentroPolicy nao da create/update/delete a este role).
            'view_centro', 'view_any_centro',
            // Fiel: so leitura (FielPolicy restringe create/update/delete aos gestores da paroquia).
            'view_fiel', 'view_any_fiel',
            // Movimento: cria/edita no seu centro, mas nunca aprova/rejeita.
            'view_movimento', 'view_any_movimento', 'create_movimento', 'update_movimento',
            // Paginas restritas ao seu centro (sem Rastreabilidade Bancaria,
            // Repasses Inter-Centro nem Log de Auditoria).
            'page_MatrizDizimos', 'page_MatrizAssiduidadeReport',
            'page_DemonstrativoArrecadacao', 'page_BalancoReceitasDespesas',
            'page_FieisPorSituacao',
            'widget_ArrecadacaoBarChart', 'widget_ArrecadacaoPieChart', 'widget_EstatisticasGeraisWidget',
        ]);
    }

    private function assignPermissions(string $roleName, array $permissions): void
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $role->syncPermissions($permissions);
    }
}
