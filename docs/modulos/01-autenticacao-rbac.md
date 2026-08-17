# Módulo 1 — Autenticação + RBAC (Filament Shield)

> Estado: **implementado**. Este documento descreve o código tal como existe hoje (as-built), para referência — não é um plano.

## 1. Visão geral

Login no painel Filament (`/admin`) e controlo de acesso baseado em papéis (RBAC), usando `spatie/laravel-permission` (Roles/Permissions) por baixo do Filament Shield. Define quem pode entrar no sistema, com que papel, e serve de base a todas as Policies dos restantes módulos. Usado por todos os utilizadores do sistema — desde `admin_geral` até aos papéis de centro.

Existem hoje **11 papéis**: os 7 papéis financeiros descritos no `CLAUDE.md` (`admin_geral`, `administrador_paroquial`, `tesoureiro_paroquial`, `tesoureiro_centro`, `coordenador_centro`, `secretario_centro`, `consultor`) e mais 4 papéis do módulo Catequese (`coordenador_catequese_paroquia`, `coordenador_catequese_centro`, `secretario_catequese`, `tesoureiro_catequese` — ver `docs/modulos/catequese.md`). Os dois grupos são independentes: nenhum herda acesso do outro.

`coordenador_centro` e `secretario_centro` (2026-08-17) preenchem uma lacuna do `tesoureiro_centro` original: este nunca geria Fiéis (só lançava Movimentos). `coordenador_centro` acumula os dois — mesmo alcance financeiro do `tesoureiro_centro` (sem conciliação) **mais** CRUD completo de Fiéis do seu centro (`FielPolicy`, Módulo 3); `secretario_centro` só tem o CRUD de Fiéis, sem nenhum acesso a Movimentos/relatórios/widgets financeiros (`MovimentoPolicy` exclui-o por completo).

**Gestão de utilizadores por `coordenador_centro`** (2026-08-17): além dos Fiéis, `coordenador_centro` passou também a gerir contas `tesoureiro_centro`/`secretario_centro` do seu **próprio centro** — criar, editar e redefinir senha — nunca outro `coordenador_centro` nem papéis de catequese. Ver secção 8.

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
`User::canAccessPanel()` exige `status === 'ativo'` **e** um dos 11 papéis (ver secção 1). Sem isto, `Filament\Http\Middleware\Authenticate` cairia no fallback de bloquear qualquer acesso fora do ambiente `local`.

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
- `viewAny`/`create`: `administrador_paroquial` **e** `coordenador_centro` (além de `admin_geral`, via `Gate::before`).
- `view`/`update`/`resetPassword`: delegam todos em `podeGerir()` — `administrador_paroquial` sobre utilizadores da própria paróquia **e** com papel `tesoureiro_paroquial`/`tesoureiro_centro`/`coordenador_centro`/`secretario_centro`/`coordenador_catequese_paroquia`/`secretario_catequese` (`PAPEIS_GERIVEIS_ADMIN_PAROQUIAL`); `coordenador_centro` (2026-08-17) sobre utilizadores do seu **próprio `centro_id`** e só com papel `tesoureiro_centro`/`secretario_centro` (`PAPEIS_GERIVEIS_COORDENADOR_CENTRO`) — nunca outro `coordenador_centro`. Nenhum dos dois gere `admin_geral`, `consultor` ou outro papel fora da sua lista.
- `resetPassword` (ability customizada, 2026-08-17): mesma autorização de `update` — usada só pela acção "Redefinir Senha" da tabela (não expõe o resto do formulário).
- `delete`/`deleteAny`/`restore`/`forceDelete`: sempre `false` para todos. Motivo documentado no código: `movimentos.usuario_id` é `restrictOnDelete`, e como `users` não tem soft delete, um `forceDelete` quebraria essa FK.

### `ForcaParoquiaUtilizadorObserver`
Observer `saving()` que ignora o `paroquia_id` submetido no formulário e força o valor a partir do `paroquia_id` do utilizador autenticado — excepto para `admin_geral`, que escolhe livremente. Aplicado (via `AppServiceProvider::boot()`) a `Centro`, `Fiel`, `CategoriaDespesa`, `Banco` **e `User`**. É a defesa contra adulteração do estado Livewire no cliente (`->visible(false)` no form não impede alterar o valor pela consola do browser).

Desde 2026-08-17, quando o `Model` é `User` **e** quem grava é `coordenador_centro`, o mesmo `saving()` força também `centro_id = $user->centro_id` — mesmo princípio, agora para o centro em vez da paróquia (só faz sentido para `User`, daí o `instanceof User` explícito dentro do observer partilhado).

### Alteração de senha obrigatória (`deve_alterar_senha`)
Coluna booleana em `users` (migration `2026_08_17_000001`, default `false`) que marca uma conta cuja senha não foi escolhida pelo próprio utilizador — sempre `true` numa conta nova (`CreateUser::afterCreate()`) e sempre que a senha é redefinida pela acção "Redefinir Senha" (ver secção 8). Nunca faz parte do `#[Fillable(...)]` do `User` nem de nenhum campo de formulário Filament — só é alterada via `forceFill()` em código do servidor, nunca por mass assignment vindo de um form.

- **`ForcarAlteracaoSenha`** (middleware, registado em `AdminPanelProvider::authMiddleware()` depois de `EnsureParoquiaScope`): em qualquer pedido autenticado, se `deve_alterar_senha` for `true`, reencaminha sempre para `AlterarSenhaObrigatoria` — excepto para a própria página (evita loop) e para a rota de logout (`filament.admin.auth.logout`, para não prender ninguém sem conseguir sair).
- **`AlterarSenhaObrigatoria`** (`app/Filament/Pages`, `$shouldRegisterNavigation = false`, slug `alterar-senha`): formulário só com `nova_senha`/`nova_senha_confirmation` (`->confirmed()`); ao gravar, faz `Auth::user()->forceFill(['password' => ..., 'deve_alterar_senha' => false])->save()` e redirecciona para o `Dashboard`. Nunca aparece na navegação — só é alcançada pelo redireccionamento forçado.
  - **Layout centrado, igual ao Login (2026-08-17)**: `protected static string $layout = 'filament-panels::components.layout.simple';` + `hasLogo(): true` + a view a usar `<x-filament-panels::page.simple>` — mesmo cartão com fundo, sombra e logotipo do ecrã de login, em vez do layout normal do painel com sidebar (não fazia sentido navegar para mais lado nenhum aqui). **Continua a estender `Page`, nunca `SimplePage`**: `SimplePage` estende `BasePage` directamente, sem a trait `HasRoutes` (perde-se `getUrl()`), e o `discoverPages()` do painel só regista classes `is_subclass_of(Filament\Pages\Page::class)` — uma `SimplePage` falha esse teste e a rota nem chega a ser criada. `$layout`/`hasLogo()` sozinhos bastam para o visual, sem abdicar do registo automático da rota.
  - **Alerta de login bem-sucedido antes da troca (2026-08-17)**: pedido do cliente — antes de orientar a troca de senha, tem de ficar evidente que o login teve sucesso. `mount()` envia um toast (`Notification::make()->title('Login efetuado com sucesso')`) **e** a view mostra um alerta fixo (verde, `heroicon-o-check-circle`, com o nome do utilizador) acima da explicação "Por segurança, precisa de escolher uma senha pessoal..." — o toast desaparece sozinho, o alerta fixo não, por isso os dois coexistem. Testado via `assertSeeInOrder()` (alerta de login antes da orientação de troca).
  - **Bug corrigido (2026-08-17): loop login → trocar senha → login → trocar senha.** `Illuminate\Session\Middleware\AuthenticateSession` (activo no painel) compara, em cada pedido de página normal, o hash da senha guardado na sessão (`password_hash_web`) com o hash actual do utilizador — e desliga a sessão sozinho se não baterem certo (protecção standard do Laravel contra sessões "roubadas"). Como `guardar()` corre dentro de um pedido Livewire (AJAX), que **não** passa pelo middleware do painel, a sessão ficava com o hash antigo mesmo depois da senha mudar na BD; o pedido de página seguinte (o redirect para o Dashboard) via isso como sessão roubada e mandava sempre de volta para o login — que, por sua vez, voltava a cair nesta página porque `deve_alterar_senha` só é lido no login seguinte. Corrigido actualizando manualmente `session()->put('password_hash_'.Auth::getDefaultDriver(), ...)` logo a seguir a gravar a nova senha. Regressão coberta por `test_trocar_a_senha_nao_desliga_a_sessao_no_pedido_de_pagina_seguinte` (confirmado a falhar sem o fix antes de o aplicar).
- Testado em `tests/Feature/AlterarSenhaObrigatoriaTest.php` (redireccionamento real via HTTP, submissão da página via `Livewire::test()`, logout mesmo com a flag pendente, criação de conta nova já marcada).

## 5. Filament

- **`UserResource`** (`navigationGroup: Acessos`):
  - `papeisAtribuiveis()`: `admin_geral` escolhe entre todos os papéis; `administrador_paroquial` só pode atribuir `tesoureiro_paroquial`/`tesoureiro_centro`/`coordenador_centro`/`secretario_centro` (financeiro) e `coordenador_catequese_paroquia`/`secretario_catequese` (catequese); `coordenador_centro` (2026-08-17) só pode atribuir `tesoureiro_centro`/`secretario_centro`.
  - `papelPermitido(string $role)`: valida server-side o papel submetido contra `papeisAtribuiveis()`, `abort`(403) se não permitido — defesa contra adulteração do `Select` no cliente, no mesmo espírito do Observer.
  - Formulário em 2 tabs (Dados de Acesso / Atribuição); campo `password` usa `dehydrated(fn (?string $state) => filled($state))` porque o model já faz cast `'hashed'` (evita cifrar duas vezes). O campo `centro_id` (2026-08-17) fica `disabled()->dehydrated()` e pré-preenchido com o próprio centro sempre que quem preenche o formulário é `coordenador_centro` — mesmo padrão já usado no `FielImporter`.
  - `getEloquentQuery()`: filtra por `paroquia_id` do utilizador quando `administrador_paroquial`, e por `centro_id` quando `coordenador_centro` (2026-08-17) — **não** usa `ParoquiaScope` no model `User` (para não afectar outros pontos do sistema que consultam `User`, ex. login, comando de notificações); o isolamento é feito só nesta Resource.
  - Tabela: acções agrupadas num `ActionGroup` — `EditAction` e **`redefinirSenha`** (2026-08-17, `Auth::user()->can('resetPassword', $record)`): gera uma senha temporária aleatória (`Str::password(12)`), grava-a (`forceFill` + `deve_alterar_senha = true`) e mostra-a **uma única vez** numa `Notification` persistente — nunca fica gravada em claro nem em log, só existe nesse toast para o administrador/coordenador copiar e entregar ao utilizador.
  - Sem `DeleteAction`/bulk delete — coerente com `UserPolicy::delete()` sempre `false`.
- **`RoleResource` publicado localmente (2026-08-17)**: `php artisan shield:publish admin` copiou o Resource do pacote para `app/Filament/Resources/RoleResource.php` (+ `Pages/{List,Create,Edit,View}Role.php`) — o `FilamentShieldPlugin::register()` deixa de registar a versão do vendor assim que detecta `is_subclass_of` uma classe própria a terminar em `\RoleResource` (`Utils::isResourcePublished()`), por isso não há duplicação. Feito só para poder agrupar as acções da tabela (ver abaixo); o resto do Resource (form, colunas, permissions) foi deixado tal como o pacote gera.
  - Tabela: acções de `EditAction`/`DeleteAction` agrupadas num `ActionGroup` (pedido do cliente: qualquer tabela com mais de uma acção visível ao mesmo tempo usa o menu de reticências, nunca botões soltos) — o pacote gerava-as soltas.
  - **Nome do grupo de navegação (2026-08-17)**: o pacote mostrava "Filament Shield" no menu lateral — nome técnico do pacote, sem significado para quem usa o sistema (pedido do cliente: só o menu, resto do ecrã do Shield fica como está). Corrigido com `lang/vendor/filament-shield/pt_PT/filament-shield.php`, sobrescrevendo **só** a chave `nav.group` para `'Papéis e Permissões'` — as restantes strings do Shield continuam a resolver pelo `fallback_locale` automático do Laravel por chave em falta (o pacote nem sequer tem tradução pt_PT própria, só en/pt_BR).
  - **Cuidado (regressão apanhada nos testes ao implementar isto)**: `Illuminate\Foundation\Application::bindPathsInContainer()` usa `resources/lang` em vez do `lang/` de topo sempre que `resources/lang` existir como pasta — **mesmo vazia**. Criar qualquer ficheiro dentro de `resources/lang/` muda o `langPath()` activo de **toda a aplicação**, e `lang/pt_PT/validation.php` (mensagens de validação em português, cobertas por `tests/Unit/ValidationMessagesPortuguesasTest.php`) deixa de carregar. A correcta é sempre `lang/vendor/...`, nunca `resources/lang/vendor/...`, nesta app.

## 6. Seeders

- **`RoleSeeder`**: `Role::firstOrCreate()` para os 11 papéis (idempotente).
- **`PermissionSeeder`**: corre `shield:generate --option=permissions` (nunca `policies_and_permissions`, para nunca sobrescrever ficheiros de Policy), cria 2 permissions customizadas (`aprovar_movimento`, `rejeitar_movimento` — abilities que não são CRUD standard), e faz `syncPermissions()` por papel, espelhando exactamente o que cada Policy já concede. O comentário no código é explícito: **isto não substitui as Policies** — a autorização real continua a ser feita por elas; o seeder só preenche a tabela de permissions para o ecrã de gestão de Roles do Shield ficar coerente.
- Papéis da Catequese ainda **não** têm permissions atribuídas pelo `PermissionSeeder` (nota registada em `docs/modulos/catequese.md`, secção 9).

## 7. Regras de negócio não óbvias

- `codigo_dizimista`, `paroquia_id`, `centro_id` nunca são de confiança vindos do cliente — sempre recalculados/validados no servidor (Observer + `papelPermitido()`), um padrão repetido em vários módulos.
- `admin_geral` contorna todas as Policies via `Gate::before`; qualquer regra "nunca X" (ex. apagar Movimento) tem de ser reforçada explicitamente numa Page/Action, não apenas na Policy.
- Sem soft delete em `users`: desactivação é feita via `status = 'inativo'`, nunca remoção.
- A senha temporária gerada por "Redefinir Senha" só existe em memória durante aquele pedido — nunca é persistida em claro nem passa por nenhum log/Activitylog (`User` não usa `LogsActivity`); se o administrador/coordenador fechar a notificação sem a copiar, a única forma de recuperar é redefinir de novo.
