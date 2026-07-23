# Módulo 6 — Despesas + Categorias

> Estado: **implementado**. Este documento descreve o código tal como existe hoje (as-built), para referência — não é um plano.

## 1. Visão geral

Não existe um model/tabela `Despesa` própria: uma "despesa" é um `Movimento` com `tipo = despesa_centro` (ver Módulo 4), classificado obrigatoriamente por uma `CategoriaDespesa`. Este módulo cobre especificamente a gestão das categorias de despesa (o "catálogo" usado no lançamento) e a regra de aprovação automática/limite aplicada a despesas. Usado por `administrador_paroquial`/`tesoureiro_paroquial` (CRUD de categorias); `tesoureiro_centro` só usa categorias já existentes ao lançar despesas (sem gerir o catálogo); `consultor` só leitura.

## 2. Tabelas de base de dados

### `categorias_despesa`
| Campo | Tipo | Notas |
|---|---|---|
| `paroquia_id` | FK → paroquias, **restrictOnDelete** | |
| `nome` | string | |
| `descricao` | text, nullable | |
| `status` | enum(`ativo`,`inativo`) default `ativo` | |
| timestamps + **SoftDeletes** | | adicionado em migration posterior (`2026_07_13_122324`) — tabela financeira, regra ABSOLUTA nº1 |

Relação com `movimentos`: `movimentos.categoria_despesa_id` é FK **restrictOnDelete**, obrigatória só quando `movimentos.tipo = despesa_centro` (ver Módulo 4).

## 3. Models e relações

- **`CategoriaDespesa`**: `SoftDeletes`, aplica `ParoquiaScope`; `belongsTo(Paroquia)`, `hasMany(Movimento)`.

## 4. RBAC

### `CategoriaDespesaPolicy`
- `GESTORES_PAROQUIA = ['administrador_paroquial', 'tesoureiro_paroquial']`.
- `viewAny`: gestores + `consultor`.
- `view`: `consultor` global; gestores por `paroquia_id`.
- `create`/`update`: só gestores da paróquia.
- `delete`: gestores da paróquia **e** só se `$categoriaDespesa->movimentos()->count() === 0` — nunca apagar uma categoria com despesas já lançadas (protege a integridade do relatório de Balanço de Receitas vs Despesas, Módulo 7).
- `deleteAny`: gestores da paróquia.
- `restore`/`forceDelete`: sempre `false`.

`tesoureiro_centro` **não** tem qualquer permissão nesta Policy — só consome categorias existentes através do `Select` relationship no formulário de `Movimento` (Módulo 4), que não passa por aqui.

## 5. Filament

- **`CategoriaDespesaResource`** (`navigationGroup: Financeiro`):
  - Campo `paroquia_id` como `Hidden`, com `default(fn () => Auth::user()?->paroquia_id)` — reforçado ainda pelo `ForcaParoquiaUtilizadorObserver` (Módulo 1) contra adulteração.
  - Coluna `movimentos_count` (`->counts('movimentos')`) na tabela — visibilidade directa de quantas despesas já usam a categoria, relevante para perceber se pode ser apagada (ver Policy acima).
  - Filtro `TrashedFilter::make()`; acções `EditAction` + `DeleteAction` explícitos na tabela (ao contrário de `Movimento`, aqui a UI expõe o delete directamente, protegida pela Policy).

## 6. Regras de negócio não óbvias

- **Não existe uma tabela `despesas` separada** — "despesa" é semanticamente um `Movimento` filtrado por `tipo = despesa_centro`; toda a lógica de aprovação, comprovativo e conciliação é a mesma dos outros tipos de movimento (Módulo 4).
- **Limite de aprovação automática** (partilhado com o Módulo 4, mas relevante aqui porque é o que diferencia uma "despesa" simples de uma que precisa de aprovação): `config('sge.valor_aprovacao_despesa')` (env `SGE_VALOR_APROVACAO_DESPESA`, default 50000 Kz) — despesas até este valor ficam `status_conciliacao = aprovado` automaticamente no `MovimentoObserver::created()`; acima do limite ficam `pendente`, exigindo aprovação manual de um `GESTORES_PAROQUIA` (`administrador_paroquial`/`tesoureiro_paroquial`) através da acção `aprovar` do `MovimentoResource`.
- Uma categoria de despesa com pelo menos uma despesa lançada **nunca** pode ser apagada (mesmo soft delete), nem por `administrador_paroquial`/`tesoureiro_paroquial` — só quando a contagem de movimentos associados for zero.
