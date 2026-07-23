# Módulo 2 — Paróquias e Centros

> Estado: **implementado**. Este documento descreve o código tal como existe hoje (as-built), para referência — não é um plano.

## 1. Visão geral

Estrutura organizacional base do SGE: cada `Paroquia` agrupa vários `Centro`s (comunidades/capelas), e é a unidade de isolamento multi-tenant de todo o sistema — quase todos os models financeiros e de catequese têm `paroquia_id` e ficam sujeitos à `ParoquiaScope`. Usado por `admin_geral` (gere todas as paróquias) e pelos papéis paroquiais/de centro (operam dentro da sua própria estrutura).

## 2. Tabelas de base de dados

### `paroquias`
| Campo | Tipo | Notas |
|---|---|---|
| `nome` | string, **unique** | |
| `diocese`, `morada`, `responsavel`, `email_contato`, `telefone` | string | |
| `status` | enum(`ativo`,`inativo`) default `ativo` | |
| timestamps | | **sem soft delete** — não é tabela financeira em si (é a raiz do tenant) |

### `centros`
| Campo | Tipo | Notas |
|---|---|---|
| `paroquia_id` | FK → paroquias, **restrictOnDelete** | |
| `nome` | string | |
| `localizacao`, `responsavel_local` | string, nullable | |
| `status` | enum(`ativo`,`inativo`) default `ativo` | |
| timestamps | | **sem soft delete** |

Nenhuma das duas tabelas tem soft delete nem unique key composta além do `nome` único em `paroquias`.

## 3. Models e relações

- **`Paroquia`** (sem `ParoquiaScope`, é a raiz): `hasMany` de `centros`, `bancos`, `fieis`, `categoriasDespesa`, `movimentos`, `anosLetivos`, `catequizandos`, `catequistas`, `turmas`, `inscricoes`.
- **`Centro`**: aplica `ParoquiaScope` no `booted()`. `belongsTo(Paroquia)`; `hasMany(Movimento)`, `hasMany(FielCentro)`; `belongsToMany(Fiel, 'fiel_centros')` via pivot `FielCentro`, com `withPivot(['data_inicio','data_fim','principal','motivo_transferencia'])`; `hasMany` de `catequizandos`, `catequizandoCentros`, `catequistas`, `turmas` (módulo Catequese).

## 4. RBAC

### `ParoquiaPolicy`
- `viewAny`/`view`: só `consultor` (além de `admin_geral` via `Gate::before`).
- `create`/`update`/`delete`/`restore`/`forceDelete`: sempre `false` — CRUD de Paróquias é exclusivo do `admin_geral` (CLAUDE.md), sem excepção nem para `administrador_paroquial`.

### `CentroPolicy`
- Constante `GESTORES_PAROQUIA = ['administrador_paroquial', 'tesoureiro_paroquial']` — mesmo alcance para os dois papéis (o `administrador_paroquial` acumula ainda gestão de utilizadores, mas isso vive na `UserPolicy`).
- `viewAny`: gestores da paróquia + `tesoureiro_centro` + `consultor`.
- `view`: `consultor` vê tudo; gestores da paróquia veem centros da sua própria `paroquia_id`; `tesoureiro_centro` só vê o seu próprio `centro_id`.
- `create`/`update`: só gestores da paróquia, e só dentro da própria paróquia.
- `delete`/`deleteAny`/`restore`/`forceDelete`: sempre `false` — nenhum papel apaga Centros pela UI (mesmo `administrador_paroquial`).

## 5. Filament

- **`ParoquiaResource`** (`navigationGroup: Estrutura`): formulário em 2 tabs (Dados Gerais / Contacto); `nome` com validação `unique(ignoreRecord: true)`. A tabela inclui `DeleteBulkAction`; como `ParoquiaPolicy::delete()`/`deleteAny()` devolvem sempre `false`, a acção fica indisponível para todos excepto `admin_geral`, que contorna a Policy via `Gate::before` (Módulo 1) — é o único papel que, na prática, consegue apagar uma Paróquia pela UI.
- **`CentroResource`** (`navigationGroup: Estrutura`):
  - Campo `paroquia_id` só visível para `admin_geral`; os restantes ficam presos por omissão (`default(fn () => Auth::user()?->paroquia_id)`) e reforçados pelo `ForcaParoquiaUtilizadorObserver` (Módulo 1).
  - `getEloquentQuery()`: além da `ParoquiaScope` (aplicada no model), reforça `where('id', $user->centro_id)` para `tesoureiro_centro`.
  - Coluna e filtro por `paroquia.nome` só visíveis para `admin_geral` (para os outros papéis, sempre a mesma paróquia, não faz sentido mostrar).

## 6. Regras de negócio não óbvias

- `Paroquia` é a **única** tabela de estrutura sem `ParoquiaScope` — faz sentido, é a própria unidade do scope.
- `centros.paroquia_id` é `restrictOnDelete`: uma paróquia com centros associados nunca pode ser apagada via `DB::delete` directo sem antes remover os centros (embora a Policy já bloqueie a UI).
- CRUD de Paróquias é **exclusivo de `admin_geral`** — nem `administrador_paroquial` cria/edita a sua própria paróquia; só gere o que está "dentro" dela (centros, fiéis, movimentos, utilizadores).
