# Módulo 5 — Matriz de Dízimos (Assiduidade)

> Estado: **implementado**. Este documento descreve o código tal como existe hoje (as-built), para referência — não é um plano.

## 1. Visão geral

Página interactiva que cruza fiéis × 12 meses de um ano, mostrando o estado do dízimo em cada mês (pago / em aberto / não vinculado) e classificando cada fiel por assiduidade, com lançamento de dízimos em lote directamente a partir da matriz. Não introduz tabelas novas — é uma camada de leitura/escrita sobre `Movimento` e `fiel_centros`. Usado por `admin_geral`, `administrador_paroquial`, `tesoureiro_paroquial` e `tesoureiro_centro` (cada um vendo só os centros a que tem acesso).

## 2. Tabelas de base de dados

Nenhuma tabela própria. Consulta `movimentos` (tipo `dizimo`, `status_conciliacao = aprovado`) e `fiel_centros` (para saber a que centro(s) e em que período o fiel esteve vinculado); escreve em `movimentos` ao lançar em lote (ver secção 5).

## 3. Services

### `App\Services\MatrizDizimosService`
Serviço partilhado entre a página interactiva (este módulo) e o Relatório de Matriz de Assiduidade exportável (Módulo 7) — mesma lógica de cálculo em ambos os sítios, para nunca divergirem.

- `centrosPermitidos(User $user, int|string|null $centroIdSolicitado): array` — resolve a lista de centros a consultar consoante o papel: `tesoureiro_centro` fica sempre preso ao seu próprio; os restantes usam o centro pedido explicitamente (ex. via query string das rotas de exportação) se existir e for válido, senão caem para **todos os centros que conseguem ver**.
- `calcular(array $centroIds, int $ano): array` — para cada fiel vinculado a algum dos centros durante o ano (considerando o período `data_inicio`/`data_fim` do vínculo, não só o vínculo actual), monta um array de 12 meses com estado `pago` / `em_aberto` (vinculado mas sem pagamento) / `nao_vinculado` (fora do período de vínculo naquele mês), soma `total_pagos`, e classifica em **segmento**: `Assíduo` (12/12), `Regular` (7–11), `Irregular` (1–6), `Inactivo` (0).
- **Decisão de desenho documentada no código**: a assiduidade é sempre calculada **por fiel**, somando pagamentos de todos os centros por onde passou no ano — nunca só do centro que está a ser visto. Contar só os pagamentos do centro actual sub-contaria o total e classificaria erradamente como "Irregular" quem já tinha os 12 meses pagos, só que espalhados por dois centros (ex.: transferência a meio do ano).

## 4. RBAC

Controlado inteiramente por `canAccess()` na Page (não há Policy dedicada, porque não há model próprio): `admin_geral`, `administrador_paroquial`, `tesoureiro_paroquial`, `tesoureiro_centro`. `consultor` **não** tem acesso a esta página interactiva (tem, sim, ao relatório equivalente do Módulo 7 — ver lá).

Validações de segurança adicionais dentro da própria Page (`MatrizDizimos`), porque `centroId`/`fielId` são propriedades/argumentos Livewire adulteráveis no cliente:
- `centroPertenceAoUtilizador(int $centroId)`: `admin_geral` pode escolher qualquer centro existente; `tesoureiro_centro` só o próprio; outros papéis dependem da `ParoquiaScope` do model `Centro` (um centro de outra paróquia simplesmente não existe na query).
- `fielPertenceAoCentro(int $fielId, int $centroId)`: confirma o vínculo antes de lançar qualquer movimento em lote.

## 5. Filament

- **`App\Filament\Pages\MatrizDizimos`** (`navigationGroup: Fiéis`), implementa `HasActions`/`HasForms`.
  - Usa a trait partilhada `App\Filament\Concerns\FiltraMatrizDizimos` (também usada pelo relatório do Módulo 7): guarda `centroId`, `nomeFiel`, `ano` como propriedades Livewire; `tesoureiro_centro` nunca escolhe centro (fica sempre preso ao seu, sem selector — `mostrarFiltroCentro()` devolve `false`); os restantes veem por omissão "Todos os centros" (`centroId = null`) e podem restringir.
  - `matriz()` (`#[Computed]`): `MatrizDizimosService::calcular($this->centrosParaConsulta(), $this->ano)`, depois filtrado por `filtrarPorNome()`.
  - **`lancarLoteAction()`**: modal para lançar o dízimo de **um fiel** em vários meses de uma vez (`CheckboxList` de meses + valor + método de pagamento + banco + data + comprovativo). Só visível quando há um `centroId` concreto escolhido (em "Todos os centros" não há para onde escrever).
  - `processarLancamentoLote()`: valida `centroPertenceAoUtilizador()` e `fielPertenceAoCentro()` antes de qualquer escrita; para cada mês seleccionado, verifica se já existe um dízimo lançado (evita o erro feio da unique key, embora a unique key da BD continue a ser a garantia final); cria um `Movimento` por mês dentro de `DB::transaction()`. **`paroquia_id` não é definido explicitamente** — o `MovimentoObserver` deriva-o do `centro_id`, a mesma fonte de verdade usada no `MovimentoResource` normal (Módulo 4). Notificação final resume quantos meses foram criados e quantos ignorados (já pagos).

## 6. Regras de negócio não óbvias

- A matriz não cria movimentos automaticamente "aprovados" — os lançados em lote entram como qualquer outro `Movimento` (`status_conciliacao = pendente` por omissão), sujeitos ao mesmo fluxo de conciliação do Módulo 4 (excepto se calhar de ser um `tipo` que se auto-aprova, o que não é o caso do dízimo — a auto-aprovação do `MovimentoObserver` só se aplica a `despesa_centro`).
- Segmentação de assiduidade (`Assíduo`/`Regular`/`Irregular`/`Inactivo`) é recalculada em tempo real a partir de `movimentos` — não existe uma tabela/coluna que persista o segmento; qualquer alteração num movimento aprovado muda o resultado imediatamente na próxima consulta.
- "Todos os centros" é uma opção de primeira classe (não um caso especial escondido) — ver o requisito explícito no commit `723d37b` do histórico do repositório.
