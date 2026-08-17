# Módulo 4 — Movimentos Financeiros + Conciliação

> Estado: **implementado**. Este documento descreve o código tal como existe hoje (as-built), para referência — não é um plano.

## 1. Visão geral

Núcleo financeiro do SGE: lançamento de dízimos, ofertórios, outras contribuições ("campanha") e despesas de centro, com um fluxo de conciliação bancária (pendente → aprovado/rejeitado) e rastreabilidade por método de pagamento/banco/comprovativo. Usado por `administrador_paroquial`/`tesoureiro_paroquial` (CRUD completo + conciliação), `tesoureiro_centro`/`coordenador_centro` (CRUD só no seu centro, sem conciliação — mesmo alcance financeiro entre os dois, `coordenador_centro` acumula gestão de Fiéis) e `consultor` (leitura global). `secretario_centro` não tem qualquer acesso a este módulo.

## 2. Tabelas de base de dados

### `metodos_pagamento`
| Campo | Tipo | Notas |
|---|---|---|
| `nome` | string | ex. Numerário, Transferência Bancária, Depósito Bancário, Cheque |
| `exige_comprovativo` | boolean default `false` | condiciona obrigatoriedade do upload de comprovativo no lançamento |
| `status` | enum(`ativo`,`inativo`) default `ativo` | |
| timestamps | | sem soft delete, sem `paroquia_id` — tabela de referência **global**, sem Filament Resource dedicado (só seedada via `DemoDataSeeder`, usada como `Select` relationship nos formulários de Movimento) |

### `bancos`
| Campo | Tipo | Notas |
|---|---|---|
| `paroquia_id` | FK → paroquias, **restrictOnDelete** | |
| `nome_banco`, `sigla` (nullable), `numero_conta`, `iban` (nullable) | string | |
| `status` | enum(`ativo`,`inativo`) default `ativo` | |
| timestamps + **SoftDeletes** | | adicionado em migration posterior (`2026_07_13_122324`) — tabela financeira, regra ABSOLUTA nº1 |

### `movimentos`
| Campo | Tipo | Notas |
|---|---|---|
| `paroquia_id` | FK, **restrictOnDelete** | derivado do `centro_id` pelo `MovimentoObserver`, nunca do formulário |
| `centro_id` | FK → centros, **restrictOnDelete** | |
| `usuario_id` | FK → users, **restrictOnDelete** | quem lançou; preenchido pelo Observer se em branco |
| `fiel_id` | FK → fieis, nullable, **restrictOnDelete** | obrigatório só quando `tipo = dizimo` |
| `metodo_pagamento_id` | FK → metodos_pagamento, **restrictOnDelete** | |
| `banco_id` | FK → bancos, nullable, **restrictOnDelete** | |
| `tipo` | enum(`dizimo`,`ofertorio`,`campanha`,`despesa_centro`) | `App\Enums\TipoMovimento` |
| `categoria_despesa_id` | FK → categorias_despesa, nullable, **restrictOnDelete** | obrigatório só quando `tipo = despesa_centro` |
| `categoria_receita_id` | FK → categorias_receita, nullable, **restrictOnDelete** | opcional, só visível quando `tipo = campanha` — categoriza "Outras Contribuições" (Casamentos, Baptizados, Funerais, etc.), pedido do cliente em reunião (2026-08-16); ao contrário de `categoria_despesa_id`, nunca é obrigatório, para não obrigar a categorizar todo lançamento antigo/genérico de campanha |
| `valor` | decimal(10,2) | |
| `ano_competencia` | unsignedSmallInteger, nullable | só para dízimo |
| `mes_competencia` | unsignedTinyInteger, nullable | 1–12; **CHECK constraint** em MySQL (`mes_competencia BETWEEN 1 AND 12` ou nulo) — omitido no SQLite dos testes, validado só na aplicação nesse caso |
| `data_movimento` | date | |
| `comprovativo_path` | string, nullable | ficheiro no disco configurado (`filesystems.default`) |
| `numero_referencia_bancaria` | string, nullable, **unique** | |
| `status_conciliacao` | enum(`pendente`,`aprovado`,`rejeitado`) default `pendente` | `App\Enums\StatusConciliacao` |
| `motivo_rejeicao` | text, nullable | |
| `dizimo_unico` | string(100), nullable, **coluna gerada (`storedAs`)** | `CASE WHEN tipo='dizimo' THEN CONCAT(fiel_id,'-',ano_competencia,'-',mes_competencia) ELSE NULL END` — só recebe valor para dízimos |
| timestamps + **SoftDeletes** | | |
| **unique** | `dizimo_unico` (nome da constraint: `movimentos_dizimo_unico_por_mes`) | replica a **Constraint Crítica** do CLAUDE.md — `(fiel_id, ano_competencia, mes_competencia)` só para `tipo='dizimo'` — porque o MySQL ignora `NULL` em unique keys, e a coluna só é preenchida quando `tipo='dizimo'` |

## 3. Models e relações

- **`Movimento`**: `SoftDeletes`, `LogsActivity` (Spatie Activitylog — regista `valor`, `status_conciliacao`, `motivo_rejeicao`, `tipo`, só quando alterados, `dontSubmitEmptyLogs()`); aplica `ParoquiaScope`. Casts: `tipo` → `TipoMovimento`, `status_conciliacao` → `StatusConciliacao`, `valor` → `decimal:2`. Relações `belongsTo`: `paroquia`, `centro`, `usuario` (FK `usuario_id`), `fiel`, `metodoPagamento`, `banco`, `categoriaDespesa`, `categoriaReceita`.
- **`Banco`**: `SoftDeletes`, `ParoquiaScope`; `belongsTo(Paroquia)`, `hasMany(Movimento)`.
- **`MetodoPagamento`**: sem scope de paróquia (tabela global).

## 4. RBAC

### `MovimentoPolicy`
- `GESTORES_PAROQUIA = ['administrador_paroquial', 'tesoureiro_paroquial']`.
- `GESTORES_CENTRO = ['tesoureiro_centro', 'coordenador_centro']` (2026-08-17) — mesmo alcance financeiro entre os dois; `secretario_centro` fica de fora de toda esta Policy.
- `viewAny`: gestores + `GESTORES_CENTRO` + `consultor`.
- `view`: `consultor` global; gestores por `paroquia_id`; `GESTORES_CENTRO` por `centro_id`.
- `create`: gestores + `GESTORES_CENTRO`.
- `update`: só se `status_conciliacao === Pendente` **e** (gestor da mesma paróquia, ou `GESTORES_CENTRO` do mesmo centro) — um movimento já conciliado (aprovado/rejeitado) deixa de ser editável por ninguém.
- `aprovar`/`rejeitar` (abilities customizadas, não CRUD standard): exclusivas dos `GESTORES_PAROQUIA`, dentro da própria paróquia — nem `tesoureiro_centro` nem `coordenador_centro` conciliam.
- `delete`/`deleteAny`/`restore`/`forceDelete`: sempre `false` — regra ABSOLUTA nº2 do CLAUDE.md (nunca DELETE físico, usar estornos).

### `BancoPolicy`
- `viewAny`/`view`: gestores da paróquia + `consultor`. `tesoureiro_centro` **sem acesso** a gerir bancos (só os usa como relationship ao lançar movimentos).
- `create`/`update`: gestores da paróquia.
- `delete`: gestores da paróquia **e** só se `$banco->movimentos()->count() === 0` — nunca apagar um banco com movimentos já lançados (protege a rastreabilidade bancária).
- `restore`/`forceDelete`: sempre `false`.

## 5. Filament

- **`MovimentoResource`** (`navigationGroup: Financeiro`):
  - Formulário em 2 tabs (Lançamento / Pagamento); campos `fiel_id`, `categoria_despesa_id`, `categoria_receita_id`, `ano_competencia`, `mes_competencia` só aparecem/são obrigatórios consoante `tipo` (via `Get`/`->live()`).
  - **`natureza` e `categoria_movimento`** (2026-08-16, pedido do cliente — "tudo é receita"): dois campos virtuais no topo do tab "Lançamento", nenhum existe na BD (`dehydrated(false)`). `natureza` é Receita/Despesa; ao escolher "Receita", `categoria_movimento` mostra **Dízimo, Ofertório e cada `CategoriaReceita` activa, todos ao mesmo nível** (chave `"cr_{id}"` para as categorias registadas), mais "Outras Contribuições (sem categoria específica)" como opção de recurso — "Outras Contribuições" deixou de ser uma opção fixa à parte. A escolha aqui determina os campos reais `tipo` e `categoria_receita_id` (ambos `Forms\Components\Hidden`, geridos via `->afterStateUpdated()`); ao editar, `->afterStateHydrated()` faz o caminho inverso (deriva `natureza`/`categoria_movimento` a partir do `tipo`/`categoria_receita_id` já gravados). Para Despesa, `categoria_despesa_id` continua a ser o `Select` normal de sempre, só que agora condicionado a `natureza` em vez de `tipo` directamente. Testado em `tests/Feature/MovimentoNaturezaCategoriaTest.php` (criação e re-hidratação em edição, via `Livewire::test()`).
  - Validação inline (`->rule(...)`) no campo `mes_competencia`: verifica duplicado de dízimo (`fiel_id` + `ano_competencia` + `mes_competencia` + `tipo=dizimo`), ignorando o próprio registo em edição — mensagem amigável antes de a unique key da BD rejeitar.
  - `comprovativo_path`: `FileUpload` com nome gerado por `Str::uuid()`, `required()` condicional a `MetodoPagamento::exige_comprovativo`.
  - Campo `centro_id` escondido para `tesoureiro_centro`/`coordenador_centro` (usam sempre o seu próprio); **`CreateMovimento`/`EditMovimento`** reforçam isto no servidor via `mutateFormDataBeforeCreate`/`mutateFormDataBeforeSave`, forçando `centro_id = $user->centro_id` — o valor do form não é de confiança.
  - Tabela: acções agrupadas num `ActionGroup` — `EditAction`, `verComprovativo` (URL assinada temporária se disco `s3`, URL directa nos outros discos; escondida se o disco `s3` estiver mal configurado, para não rebentar o render), `aprovar`/`rejeitar` (via `Auth::user()->can('aprovar'/'rejeitar', $record)`, só visíveis se `status_conciliacao = Pendente`). `rejeitar` exige motivo obrigatório num modal.
  - `getEloquentQuery()`: reforça `centro_id` para `tesoureiro_centro`/`coordenador_centro`.
  - **`EditMovimento::getHeaderActions()` devolve array vazio** — bloqueia explicitamente qualquer `DeleteAction`, porque `admin_geral` contorna a `MovimentoPolicy` via `Gate::before` (Módulo 1) e, sem este reforço, ainda conseguiria apagar um movimento pela UI apesar da Policy dizer sempre `false`.
- **`BancoResource`**: CRUD simples com Policy aplicada; sem RelationManagers.
- **`CategoriaReceitaResource`** (`navigationGroup: Financeiro`) e **`CategoriaReceitaPolicy`**: CRUD simples, espelha `CategoriaDespesaResource`/`CategoriaDespesaPolicy` campo a campo (mesmo form, mesma coluna "Receitas lançadas" via `counts('movimentos')`, mesmo bloqueio de `delete` quando já há movimentos ligados). `ForcaParoquiaUtilizadorObserver` também aplicado (`AppServiceProvider`).
  - **`CategoriasFixasWidget`** (`CategoriaReceitaResource\Widgets`, fora de `app/Filament/Widgets` de propósito — não é descoberto pelo `AdminPanelProvider`, não aparece no dashboard principal; renomeado de `DizimoOfertorioWidget` em 2026-08-16 ao acrescentar o 3º cartão): 3 cartões informativos ("Dízimo"/"Ofertório"/"Outras Contribuições (sem categoria)", contagem de movimentos lançados) acima da tabela, ligado só via `ListCategoriaReceitas::getHeaderWidgets()`. Pedido do cliente depois de ver a tabela só com as categorias personalizadas (Casamentos, etc.) — nenhum dos 3 vai ser linha editável dessa tabela (não são registos em `categorias_receita`: os dois primeiros são o próprio `tipo`, com regras próprias; o terceiro é o resíduo de `campanha` com `categoria_receita_id` nulo), mas o cliente queria vê-los ali na mesma, incluindo o resíduo — "tem e terá movimentos, também é fixa do sistema".
- **Fiel filtrado por Centro no lançamento** (2026-08-16): `fiel_id` (visível só quando a Categoria escolhida é Dízimo) ganhou `relationship(..., modifyQueryUsing: ...)` a restringir a fiéis `status=ativo` **e** vinculados ao `centro_id` escolhido (mesmo critério — `whereHas('centros', ...)->whereNull('fiel_centros.data_fim')` — já usado em `EstatisticasGeraisWidget`). Bug reportado pelo cliente: como `administrador_paroquial` (que escolhe o centro livremente, ao contrário de `tesoureiro_centro`), o Select mostrava sempre todos os fiéis da paróquia, mesmo depois de escolher um centro. `centro_id` ganhou `->live()` (necessário para o filtro reagir) e `afterStateUpdated` a limpar `fiel_id` ao trocar de centro, para não deixar seleccionado um fiel de outro centro.
- Páginas de relatório ligadas à conciliação/rastreabilidade bancária (`navigationGroup: Relatórios`, documentadas em detalhe no Módulo 7): `RastreabilidadeBancaria` (lista movimentos com `banco_id` preenchido, exportável em Excel) e `AuditoriaRepassesInterCentro` (reaproveita o histórico `fiel_centros` do Módulo 3, já que não existe transferência financeira directa entre centros no schema — só filtra linhas com `motivo_transferencia` preenchido).

## 6. Regras de negócio não óbvias

- **Nunca DELETE físico**: `delete`/`forceDelete` bloqueados a nível de Policy **e** reforçados a nível de Page (`EditMovimento`) contra o bypass do `admin_geral`. Não existe, hoje, nenhuma acção explícita de "estorno" (lançamento de contrapartida) implementada — a regra do CLAUDE.md está garantida pelo bloqueio total de remoção, mas o fluxo de criar um movimento de estorno em si ainda não tem UI dedicada.
- **Aprovação automática de despesas**: `MovimentoObserver::created()` aprova automaticamente (`status_conciliacao = Aprovado`, via `saveQuietly()` para não disparar eventos em cascata) despesas de centro (`tipo = despesa_centro`) com `valor <= config('sge.valor_aprovacao_despesa')` (env `SGE_VALOR_APROVACAO_DESPESA`, default 50000). Acima do limite, fica `pendente` e exige aprovação manual de um `GESTORES_PAROQUIA`.
- **`paroquia_id` nunca vem do formulário nem do utilizador**: `MovimentoObserver::creating()` deriva sempre de `Centro::withoutGlobalScopes()->find($movimento->centro_id)?->paroquia_id` — necessário porque `admin_geral` não tem `paroquia_id` próprio.
- **`dizimo_unico` é uma coluna gerada** especificamente para replicar uma unique key parcial (só dízimos) que o MySQL não suporta nativamente — truque documentado na migration.
- Um movimento só é editável enquanto `status_conciliacao = pendente`; depois de aprovado/rejeitado, é imutável (mesmo para os gestores da paróquia).
