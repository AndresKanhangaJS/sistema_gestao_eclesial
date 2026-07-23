# Módulo 7 — Relatórios + Exportação PDF/Excel

> Estado: **implementado**. Este documento descreve o código tal como existe hoje (as-built), para referência — não é um plano.

## 1. Visão geral

Conjunto de páginas de relatório (Filament Pages sob `navigationGroup: Relatórios`), widgets de dashboard e rotas web dedicadas a exportação em PDF (via Browsershot/Chromium) e Excel (via `maatwebsite/excel`). Não introduz tabelas próprias — agrega dados de `Movimento`, `Fiel`, `fiel_centros` e do log de auditoria (`Spatie\Activitylog`). Usado por todos os papéis financeiros, com o alcance de dados sempre condicionado ao papel (paróquia/centro/global).

## 2. Tabelas de base de dados

Nenhuma tabela própria. Lê de `movimentos`, `fieis`, `fiel_centros`, `centros` e da tabela `activity_log` (pacote `spatie/laravel-activitylog`, migrations `2026_07_12_184752/3/4`), que regista alterações a `Movimento` (`LogsActivity`, ver Módulo 4).

## 3. Services (cálculo partilhado)

| Service | Calcula | Usado por |
|---|---|---|
| `MatrizDizimosService` | Matriz fiel × 12 meses + segmentação de assiduidade | Página interactiva (Módulo 5) e relatório `MatrizAssiduidadeReport` + rotas de export |
| `DemonstrativoArrecadacaoService` | Totais de receitas (dízimo/ofertório/campanha) aprovadas, por mês e por tipo, num ano | Página `DemonstrativoArrecadacao`, widgets do dashboard, rotas de export |
| `BalancoReceitasDespesasService` | Receitas − Despesas = Saldo por mês, só movimentos aprovados | Página `BalancoReceitasDespesas` + rotas de export |
| `FieisPorSituacaoService` | Segmentação de fiéis por assiduidade do dízimo num ano (mesmos 4 segmentos), independente do centro | Página `FieisPorSituacao` + rotas de export |

Todos os 4 Services expõem `calcular(int $ano, ?int $centroId = null)` (ou variante equivalente) e filtram sempre por `status_conciliacao = Aprovado` — números "oficiais" nunca incluem pendentes/rejeitados.

## 4. RBAC

Cada página controla o acesso via `canAccess()` estático (sem Policy dedicada, por não haver model):

| Página | Papéis com acesso |
|---|---|
| `DemonstrativoArrecadacao`, `BalancoReceitasDespesas`, `FieisPorSituacao` | `admin_geral`, `administrador_paroquial`, `tesoureiro_paroquial`, `tesoureiro_centro`, `consultor` |
| `RastreabilidadeBancaria`, `AuditoriaRepassesInterCentro` | `admin_geral`, `administrador_paroquial`, `tesoureiro_paroquial`, `consultor` (**sem** `tesoureiro_centro` — conciliação/rastreabilidade bancária é exclusiva dos gestores de paróquia) |
| `MatrizAssiduidadeReport` | mesmo alcance da Matriz de Dízimos interactiva: `admin_geral`, `administrador_paroquial`, `tesoureiro_paroquial`, `tesoureiro_centro` |
| `LogAuditoria` | **só `admin_geral`** |

As rotas web de exportação (`routes/web.php`, prefixo `/relatorios`) repetem os **mesmos critérios de papel inline** (`abort_unless($user->hasRole([...]), 403)`) — não delegam para as Pages Filament, por isso qualquer alteração de RBAC numa página tem de ser replicada manualmente na rota correspondente. Comentário no código confirma que isto é deliberado: "Protegidas por auth + verificação de role inline (mesmos critérios dos `canAccess()` das páginas Filament correspondentes)".

## 5. Filament — páginas de relatório

Todas em `app/Filament/Pages/Relatorios/`, `navigationGroup: Relatórios`:

- **`DemonstrativoArrecadacao`**, **`BalancoReceitasDespesas`**, **`FieisPorSituacao`**: consultam os Services acima; `tesoureiro_centro` tem o `centro_id` sempre forçado ao seu próprio (nunca lê da query string).
- **`RastreabilidadeBancaria`**: tabela (`InteractsWithTable`) de `Movimento::query()->whereNotNull('banco_id')`, com filtro por banco e `ExportAction` (Excel via `pxlrbt/filament-excel`).
- **`AuditoriaRepassesInterCentro`**: consulta `FielCentro::withoutGlobalScopes()->whereNotNull('motivo_transferencia')` — só mostra transferências de facto (não o vínculo inicial de um fiel a um centro). Comentário no código explica a decisão: "não há transferência financeira entre centros no schema (cada movimento pertence a 1 só centro) — reaproveita o histórico `fiel_centros`, que é a única movimentação inter-centro real". Coluna `centro_origem` calculada dinamicamente (`centroOrigem()`), procurando a linha anterior do mesmo fiel cujo `data_fim` coincide com o `data_inicio` da linha actual.
- **`MatrizAssiduidadeReport`**: usa a mesma trait `FiltraMatrizDizimos` e o mesmo `MatrizDizimosService::calcular()` da página interactiva do Módulo 5 — garante que o relatório nunca diverge da matriz ao vivo.
- **`LogAuditoria`**: tabela sobre `Activity::where('subject_type', Movimento::class)` (Spatie Activitylog), colunas `causer.name`, `description`, `subject_id`, `changes` (JSON das alterações). Só PDF, sem export Excel.

## 6. Widgets (dashboard)

- **`EstatisticasGeraisWidget`** (`StatsOverviewWidget`, `sort = -10`): 4 cards — total de fiéis activos (restrito ao centro para `tesoureiro_centro`), dízimos/ofertórios/outras contribuições do ano corrente (via `DemonstrativoArrecadacaoService`).
- **`ArrecadacaoBarChart`**: gráfico de barras por mês, 3 séries (dízimo/ofertório/campanha).
- **`ArrecadacaoPieChart`**: gráfico de pizza, proporção por tipo de receita no ano.

Ambos os gráficos restringem a `centroId` do `tesoureiro_centro` da mesma forma que o widget de estatísticas.

## 7. Exportação — infraestrutura

- **`App\Support\RelatorioPdf`**: wrapper fino sobre `Spatie\LaravelPdf\Facades\Pdf`, configurando o `Browsershot` para usar `/usr/bin/chromium` (definido no `docker/php/Dockerfile`) com `noSandbox()`. Todas as rotas de export PDF passam por `RelatorioPdf::view(...)`.
- **`App\Exports\ArrayExport`** (`FromArray`, `WithHeadings` do `maatwebsite/excel`): export genérico reutilizado por todos os relatórios cujos dados já vêm agregados/computados em array (não uma query Eloquent tabular directa). **Sanitiza contra CSV/Excel Formula Injection**: qualquer célula string que comece por `=`, `+`, `-`, `@`, tab ou carriage return é prefixada com `'` antes de ir para o ficheiro — protege contra fórmulas maliciosas vindas de dados introduzidos por utilizadores (ex. nome de fiel, motivo de transferência).
- Rotas de exportação (`routes/web.php`, `relatorios.*`): uma rota `/excel` e uma `/pdf` por relatório, todas sob `Route::middleware('auth')->prefix('relatorios')`. Views Blade em `resources/views/pdfs/relatorios/*` (PDF) — não inspecionadas neste levantamento, mas referenciadas directamente pelas rotas.

## 8. Regras de negócio não óbvias

- Todos os relatórios financeiros filtram sempre por `status_conciliacao = Aprovado` — um movimento pendente ou rejeitado nunca aparece nos totais "oficiais" (Demonstrativo, Balanço, Matriz de Assiduidade, Fiéis por Situação).
- A segmentação de assiduidade (`Assíduo`/`Regular`/`Irregular`/`Inactivo`) está **duplicada** em dois Services (`MatrizDizimosService::calcular()` e `FieisPorSituacaoService::calcular()`) com o mesmo critério (12/12, 7–11, 1–6, 0) mas calculada de formas ligeiramente diferentes (uma considera o período de vínculo mês a mês, a outra só soma meses pagos) — não é o mesmo código reutilizado, é a mesma regra reimplementada duas vezes.
- `LogAuditoria` é o único relatório sem export Excel e sem acesso a `consultor` — é auditoria de sistema, exclusiva de `admin_geral`.
- As rotas de export duplicam a lógica de RBAC das Pages Filament (não a reutilizam) — um risco de desalinhamento a vigiar se o RBAC de alguma página mudar no futuro sem replicar a mudança na rota.
