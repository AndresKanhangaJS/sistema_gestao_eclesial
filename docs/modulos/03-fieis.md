# Módulo 3 — Fiéis + pivot `fiel_centros`

> Estado: **implementado**. Este documento descreve o código tal como existe hoje (as-built), para referência — não é um plano.

## 1. Visão geral

Cadastro dos fiéis/dizimistas de uma paróquia e o seu vínculo (com histórico) a um ou mais Centros ao longo do tempo, incluindo transferências entre centros. Base para a Matriz de Dízimos (Módulo 5) e para o lançamento de movimentos financeiros (Módulo 4). Usado por `administrador_paroquial`/`tesoureiro_paroquial` (CRUD completo), `tesoureiro_centro` (leitura, só do seu centro) e `consultor` (leitura global).

## 2. Tabelas de base de dados

### `fieis`
| Campo | Tipo | Notas |
|---|---|---|
| `paroquia_id` | FK → paroquias, **restrictOnDelete** | |
| `nome` | string | |
| `codigo_dizimista` | string, **unique** (globalmente, não só por paróquia) | gerado automaticamente, nunca digitado |
| `telefone`, `email` | string, nullable | |
| `data_nascimento` | date, nullable | |
| `status` | enum(`ativo`,`inativo`) default `ativo` | |
| timestamps + **SoftDeletes** | | dado financeiro/sensível — regra ABSOLUTA do CLAUDE.md |

### `fiel_centros` (pivot histórico N:N)
| Campo | Tipo | Notas |
|---|---|---|
| `fiel_id` | FK → fieis, **restrictOnDelete** | |
| `centro_id` | FK → centros, **restrictOnDelete** | |
| `data_inicio` | date | |
| `data_fim` | date, nullable | `null` = vínculo activo |
| `principal` | boolean default `false` | indica o centro principal do fiel no período |
| `motivo_transferencia` | string, nullable | preenchido só nas transferências de facto (reaproveitado no relatório de Repasses Inter-Centro, Módulo 7) |
| timestamps | | sem soft delete própria (é histórico por natureza — nunca se apaga uma linha, fecha-se com `data_fim`) |
| **unique** | (`fiel_id`, `centro_id`, `data_inicio`) | |

## 3. Models e relações

- **`Fiel`**: `SoftDeletes`; aplica `ParoquiaScope`. `belongsTo(Paroquia)`; `hasMany(FielCentro)`; `belongsToMany(Centro, 'fiel_centros')->using(FielCentro::class)->withPivot([...])->withTimestamps()`; `hasMany(Movimento)`; `hasOne(Catequizando)`, `hasOne(Catequista)` (módulo Catequese).
  - `booted()` regista `static::creating()` que preenche `codigo_dizimista` via `proximoCodigoDizimista()` se estiver em branco.
  - `proximoCodigoDizimista()`: consulta `withTrashed()->withoutGlobalScopes()` (varre **todos** os fiéis, incluindo apagados e de outras paróquias, porque o código é único globalmente) por `codigo_dizimista LIKE 'F%'`, extrai o maior número e devolve `F` + próximo número com 4 dígitos (`F0001`, `F0002`, ... cresce naturalmente além de `F9999`).
- **`FielCentro`** (`extends Pivot`, `$incrementing = true` — tem PK própria `id`, não é pivot "burro"): `belongsTo(Fiel)`, `belongsTo(Centro)`; casts `data_inicio`/`data_fim` para `date`, `principal` para `boolean`.

## 4. RBAC

### `FielPolicy`
- Constante `GESTORES_PAROQUIA = ['administrador_paroquial', 'tesoureiro_paroquial']`.
- `viewAny`: gestores da paróquia + `tesoureiro_centro` + `consultor`.
- `view`: `consultor` vê tudo; gestores veem fiéis da sua `paroquia_id`; `tesoureiro_centro` só vê fiéis com vínculo **activo** (`wherePivotNull('data_fim')`) ao seu próprio centro.
- `create`: só gestores da paróquia.
- `update`/`delete`/`restore`: gestores da paróquia, restrito à própria `paroquia_id` — **inclui soft-delete e restore** (ao contrário de `Movimento`, aqui `delete` é mesmo permitido, porque `Fiel` usa soft delete real).
- `deleteAny`: gestores da paróquia.
- `forceDelete`: sempre `false` — nunca remoção física.

## 5. Filament

- **`FielResource`** (`navigationGroup: Fiéis`):
  - Formulário em 2 tabs (Dados Pessoais / Contacto); campo `codigo_dizimista` sempre `disabled()->dehydrated(false)`, só visível em modo `edit` (nunca aparece no create, porque ainda não existe).
  - Coluna computada `centro_atual` no `table()`: `$record->centros()->wherePivotNull('data_fim')->first()`, mostra "Não vinculado" se nenhum activo.
  - Filtro `TrashedFilter::make()` — expõe fiéis com soft delete na listagem.
  - `getEloquentQuery()`: reforça para `tesoureiro_centro` só ver fiéis com `whereHas('centros', ...)->whereNull('fiel_centros.data_fim')` no seu próprio centro.
  - `getRelations()`: `CentrosRelationManager`, `MovimentosRelationManager`.
- **`CentrosRelationManager`** (relação `centros`, pivot `fiel_centros`):
  - `inverseRelationship = 'fieis'` explícito — sem isto o `AttachAction` tentaria adivinhar `Centro::fiels()` (pluralização inglesa incorrecta) e rebentava.
  - `podeEscrever()`: só `admin_geral`/`administrador_paroquial`/`tesoureiro_paroquial` (não `tesoureiro_centro`, mesmo sendo leitura no seu centro).
  - Acção customizada **`transferir`**: só visível se o vínculo actual estiver activo (`pivot->data_fim === null`); valida no servidor que o novo centro pertence à **mesma paróquia** do fiel (`Centro::withoutGlobalScopes()->where('paroquia_id', $fiel->paroquia_id)`) — necessário porque `admin_geral` não tem `ParoquiaScope` aplicada e veria todos os centros sem este filtro explícito. Fecha o vínculo antigo (`data_fim`) e cria um novo com `motivo_transferencia`.
  - Acção **`editarVinculo`**: edição directa dos campos do pivot (`data_fim`, `principal`, `motivo_transferencia`).
- **`ListFiels`**: adiciona `ImportAction` (`FielImporter`) além do `CreateAction`, com a mesma condição de visibilidade (`can('create', Fiel::class)`) — importar em massa não é um privilégio à parte.

### `FielImporter` (importação em massa)
- Colunas do ficheiro: `nome` (obrigatório), `telefone`, `email`, `data_nascimento` (formato `AAAA-MM-DD`), `status` (`ativo`/`inativo`, default `ativo`). **Nunca** traz `centro_id`/`paroquia_id` do ficheiro.
- `getOptionsFormComponents()`: pede um único `centro_id` no modal de importação — todos os fiéis importados ficam vinculados a esse centro.
- `resolveRecord()`: sempre `new Fiel` — a importação **nunca actualiza** um fiel existente (o ficheiro não traz identificador).
- Roda dentro de um job em fila, **sem** `Auth::user()` disponível — por isso `beforeCreate()` lê `paroquia_id` de `$this->import->user->paroquia_id` (persistido em BD) e valida o `centro_id` com `Centro::withoutGlobalScopes()`, nunca dependendo de sessão autenticada.
- `afterCreate()`: cria a linha em `fiel_centros` (`data_inicio = now()`, `principal = true`).

## 6. Regras de negócio não óbvias

- `codigo_dizimista` é único **globalmente** (não por paróquia) e gerado automaticamente — nunca editável; uma colisão rara por concorrência é apanhada pela unique key da BD e recuperada com retry em `CreateFiel::handleRecordCreation()` (até 5 tentativas).
- `fiel_centros` nunca tem linhas apagadas: transferências fecham a linha antiga (`data_fim`) e abrem uma nova — histórico completo, não um log à parte.
- Uma transferência de centro só é permitida entre centros da **mesma paróquia** do fiel (validado no servidor, mesmo para `admin_geral`).
- Importação em massa é só para fiéis **novos**, todos atribuídos ao mesmo centro escolhido no modal — sem suporte a actualização de fiéis existentes nem a centros diferentes por linha.
