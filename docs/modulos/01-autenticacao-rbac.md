# Módulo 1 — Autenticação + RBAC (Filament Shield)

> Estado: **implementado**. Este documento descreve o código tal como existe hoje (as-built), para referência — não é um plano.

## 1. Visão geral

Login no painel Filament (`/admin`) e controlo de acesso baseado em papéis (RBAC), usando `spatie/laravel-permission` (Roles/Permissions) por baixo do Filament Shield. Define quem pode entrar no sistema, com que papel, e serve de base a todas as Policies dos restantes módulos. Usado por todos os utilizadores do sistema — desde `admin_geral` até aos papéis de centro.

Existem hoje **9 papéis**: os 5 papéis financeiros descritos no `CLAUDE.md` (`admin_geral`, `administrador_paroquial`, `tesoureiro_paroquial`, `tesoureiro_centro`, `consultor`) e mais 4 papéis do módulo Catequese (`coordenador_catequese_paroquia`, `coordenador_catequese_centro`, `secretario_catequese`, `tesoureiro_catequese` — ver `docs/modulos/catequese.md`). Os dois grupos são independentes: nenhum herda acesso do outro.

## 2. Tabelas de base de dados

### `users`
| Campo | Tipo | Notas |
|---|---|---|
| `name`, `email` (unique), `password` | string | |
| `status` | enum(`ativo`,`inativo`) default `ativo` | permite desactivar sem apagar; migration `2026_07_13_160029` |
| `paroquia_id` | FK → paroquias, nullable, **nullOnDelete** | nulo para papéis globais (`admin_geral`, `consultor`); preenchido para os restantes papéis, usado pela `ParoquiaScope` |
| `centro_id` | FK → centros, nullable, **nullOnDelete** | só preenchido para `tesoureiro_centro` (e equivalentes de centro) |
| `remember_token`, `email_verified_at` | | padrão Laravel |
| timestamps | | **sem soft delete** — `users` não é tabela financeira; ver secção 5 sobre porque nunca é apagado pela UI |

Nota: `paroquia_id`/`centro_id` usam `nullOnDelete`, ao contrário do padrão `restrictOnDelete` das FKs financeiras — aceitável porque não são dados financeiros em si.

### `password_reset_tokens`, `sessions`
Tabelas padrão do scaffolding de autenticação do Laravel, sem alterações.

### Tabelas do `spatie/laravel-permission` (migration `2026_07_12_074052_create_permission_tables`)
`permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions` — geradas pelo pacote, sem `teams` activo. `roles`/`permissions` têm unique(`name`,`guard_name`).

## 3. Models e relações

- `App\Models\User` — implementa `FilamentUser`; usa traits `HasFactory`, `HasRoles` (Spatie), `Notifiable`.
  - `paroquia(): BelongsTo`, `centro(): BelongsTo`, `catequista(): HasOne`.
  - Atributo por omissão `status = 'ativo'` declarado explicitamente no model (não só na coluna BD), para que uma instância recém-criada em memória já reflicta o valor sem precisar de `refresh()`.
- Roles/Permissions são os models nativos do pacote `spatie/laravel-permission` (`Spatie\Permission\Models\Role`/`Permission`), sem overrides.

## 4. RBAC

### Controlo de acesso ao painel
`User::canAccessPanel()` exige `status === 'ativo'` **e** um dos 9 papéis (ver secção 1). Sem isto, `Filament\Http\Middleware\Authenticate` cairia no fallback de bloquear qualquer acesso fora do ambiente `local`.

### Login de contas inactivas
`App\Filament\Pages\Auth\Login` sobrepõe `getCredentialsFromFormData()` para injectar `'status' => 'ativo'` nas credenciais verificadas por `Auth::attempt()` — uma conta inactiva falha logo na autenticação, com a mensagem genérica de "credenciais inválidas" (não revela se a conta existe). `canAccessPanel()` é o reforço para sessões já autenticadas quando a conta é desactivada a meio da sessão.

### `Gate::before` (acesso total do admin_geral)
Em `App\Providers\AppServiceProvider::boot()`:
```php
Gate::before(fn ($user, string $ability) => $user->hasRole('admin_geral') ? true : null);
```
`admin_geral` passa em qualquer autorização sem depender de permissions individuais. Isto implica que qualquer Policy que devolva `false` para todos (ex.: `delete` em `MovimentoPolicy`) tem de ser reforçada noutro sítio para bloquear mesmo o `admin_geral` quando a regra é absoluta (ver `EditMovimento::getHeaderActions()` no Módulo 4).

### `RolePolicy`
Autoriza CRUD sobre `Role` (ecrã `/admin/shield/roles`) via permissions `view_role`, `create_role`, etc. — gerado pelo Filament Shield, sem lógica de negócio própria.

### `UserPolicy`
- `viewAny`/`create`: só `administrador_paroquial` (além de `admin_geral`, via `Gate::before`).
- `view`/`update`: `administrador_paroquial` só sobre utilizadores da própria paróquia **e** com papel `tesoureiro_paroquial` ou `tesoureiro_centro` (constante `PAPEIS_GERIVEIS`) — nunca `admin_geral`, `consultor` ou outro `administrador_paroquial`.
- `delete`/`deleteAny`/`restore`/`forceDelete`: sempre `false` para todos. Motivo documentado no código: `movimentos.usuario_id` é `restrictOnDelete`, e como `users` não tem soft delete, um `forceDelete` quebraria essa FK.

### `ForcaParoquiaUtilizadorObserver`
Observer `saving()` que ignora o `paroquia_id` submetido no formulário e força o valor a partir do `paroquia_id` do utilizador autenticado — excepto para `admin_geral`, que escolhe livremente. Aplicado (via `AppServiceProvider::boot()`) a `Centro`, `Fiel`, `CategoriaDespesa`, `Banco` **e `User`**. É a defesa contra adulteração do estado Livewire no cliente (`->visible(false)` no form não impede alterar o valor pela consola do browser).

## 5. Filament

- **`UserResource`** (`navigationGroup: Acessos`):
  - `papeisAtribuiveis()`: `admin_geral` escolhe entre os 5 papéis financeiros; qualquer outro utilizador (na prática, `administrador_paroquial`) só pode atribuir `tesoureiro_paroquial`/`tesoureiro_centro`.
  - `papelPermitido(string $role)`: valida server-side o papel submetido contra `papeisAtribuiveis()`, `abort`(403) se não permitido — defesa contra adulteração do `Select` no cliente, no mesmo espírito do Observer.
  - Formulário em 2 tabs (Dados de Acesso / Atribuição); campo `password` usa `dehydrated(fn (?string $state) => filled($state))` porque o model já faz cast `'hashed'` (evita cifrar duas vezes).
  - `getEloquentQuery()`: filtra por `paroquia_id` do utilizador quando `administrador_paroquial` — **não** usa `ParoquiaScope` no model `User` (para não afectar outros pontos do sistema que consultam `User`, ex. login, comando de notificações); o isolamento é feito só nesta Resource.
  - Sem `DeleteAction`/bulk delete — coerente com `UserPolicy::delete()` sempre `false`.
- Sem Resource dedicado para `Role`/`Permission` além do ecrã nativo do Filament Shield (`/admin/shield/roles`).

## 6. Seeders

- **`RoleSeeder`**: `Role::firstOrCreate()` para os 9 papéis (idempotente).
- **`PermissionSeeder`**: corre `shield:generate --option=permissions` (nunca `policies_and_permissions`, para nunca sobrescrever ficheiros de Policy), cria 2 permissions customizadas (`aprovar_movimento`, `rejeitar_movimento` — abilities que não são CRUD standard), e faz `syncPermissions()` por papel, espelhando exactamente o que cada Policy já concede. O comentário no código é explícito: **isto não substitui as Policies** — a autorização real continua a ser feita por elas; o seeder só preenche a tabela de permissions para o ecrã de gestão de Roles do Shield ficar coerente.
- Papéis da Catequese ainda **não** têm permissions atribuídas pelo `PermissionSeeder` (nota registada em `docs/modulos/catequese.md`, secção 9).

## 7. Regras de negócio não óbvias

- `codigo_dizimista`, `paroquia_id`, `centro_id` nunca são de confiança vindos do cliente — sempre recalculados/validados no servidor (Observer + `papelPermitido()`), um padrão repetido em vários módulos.
- `admin_geral` contorna todas as Policies via `Gate::before`; qualquer regra "nunca X" (ex. apagar Movimento) tem de ser reforçada explicitamente numa Page/Action, não apenas na Policy.
- Sem soft delete em `users`: desactivação é feita via `status = 'inativo'`, nunca remoção.
