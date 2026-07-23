# Módulo Catequese — Especificação

> Estado: **desenho fechado, implementação ainda não iniciada**.
> Este documento é a fonte única de verdade do módulo enquanto for construído por fases. Atualizar sempre que uma decisão mudar — não deixar decisões só na conversa.

## 1. Objetivo (fase 1)

Gerir Catequizandos, Turmas e Catequistas por Centro, com formação de turmas orientada por regras (idade, catecismo/ano catequético, ano que frequenta e sacramento-alvo), e fluxo de inscrição/progressão anual (ano letivo catequético, ex.: 2026/2027, 2027/2028...).

Dados de Catequistas ainda estão incompletos — a secção 4 lista só o mínimo necessário para o resto do modelo funcionar; será expandida quando o utilizador enviar as especificações completas.

## 2. RBAC — novos papéis

Todos os papéis financeiros existentes (`admin_geral`, `administrador_paroquial`, `tesoureiro_paroquial`, `tesoureiro_centro`, `consultor`) mantêm-se inalterados e **não** ganham acesso automático à Catequese. Papéis novos, dedicados:

| Papel | Âmbito | Acesso |
|---|---|---|
| `coordenador_catequese_paroquia` | paróquia | gere turmas/catequistas/catequizandos/secretários/tesoureiros de todos os centros da paróquia (paridade com `administrador_paroquial`, mas só neste módulo) |
| `coordenador_catequese_centro` | 1 centro | gere turmas/catequistas/catequizandos do seu centro (paridade com `tesoureiro_centro`, mas para catequese) |
| `secretario_catequese` | 1 centro | CRUD de catequizandos, matrículas em turmas (inscrições), registo de presenças/assiduidade — **sem** acesso financeiro |
| `tesoureiro_catequese` | 1 centro | financeiro **isolado** da catequese (propinas, materiais) — subsistema próprio, não usa a tabela `movimentos` geral (decisão do utilizador). Schema deste subsistema ainda por desenhar. |

Regras de reforço a implementar (mesmo padrão do módulo financeiro):
- Policies com constante `GESTORES_CATEQUESE_PAROQUIA = ['coordenador_catequese_paroquia']` e `GESTORES_CATEQUESE_CENTRO = [..., 'coordenador_catequese_centro']`.
- Observer que força `paroquia_id`/`centro_id` a partir do utilizador autenticado ao gravar `catequizandos`, `turmas`, `inscricoes`, `catequistas` (exceto `admin_geral`/`coordenador_catequese_paroquia`, que podem escolher centro).
- `getEloquentQuery()` reforçado nos Resources Filament, restringindo por `centro_id` quando o utilizador for `coordenador_catequese_centro`, `secretario_catequese` ou `tesoureiro_catequese`.

## 3. Tabelas de referência (configuração)

### `anos_letivos`
Ciclo anual da catequese (não confundir com `anos_catequeticos`, que é o nível/progressão).

| Campo | Tipo | Notas |
|---|---|---|
| `paroquia_id` | FK → paroquias, restrictOnDelete | |
| `nome` | string | ex. "2026/2027" |
| `data_inicio` / `data_fim` | date | |
| `status` | enum(`em_curso`,`encerrado`) | apenas um `em_curso` por paróquia — validar na aplicação |

### `anos_catequeticos`
1º, 2º, 3º... Assumido como **tabela partilhada/global** (não `paroquia_id`), gerida por `admin_geral`, seguindo o programa oficial da Arquidiocese — ajustar se cada paróquia precisar da sua própria sequência.

| Campo | Tipo |
|---|---|
| `ordem` | unsigned tinyint |
| `nome` | string ("1º Ano", "2º Ano"...) |
| `status` | enum(`ativo`,`inativo`) |

### `sacramentos`
Baptismo, Comunhão, Crisma. Mesma observação de escopo global que `anos_catequeticos`.

| Campo | Tipo |
|---|---|
| `ordem` | unsigned tinyint |
| `nome` | string |
| `status` | enum(`ativo`,`inativo`) |

## 4. Turmas

### `turmas`
| Campo | Tipo | Notas |
|---|---|---|
| `paroquia_id` | FK, restrictOnDelete | denormalizado para permitir `ParoquiaScope` direto, como em `Movimento` |
| `centro_id` | FK → centros, restrictOnDelete | |
| `ano_letivo_id` | FK → anos_letivos, restrictOnDelete | |
| `ano_catequetico_id` | FK → anos_catequeticos, restrictOnDelete | |
| `publico_alvo` | enum(`criancas`,`pre_adolescentes`,`adolescentes_jovens`) | |
| `periodo` | enum(`manha`,`tarde`,`noite`) | |
| `hora_inicio` | time | ex. 09:00 — **decisão do utilizador: horário fica na turma, não numa tabela `turnos` separada** |
| `hora_fim` | time | ex. 10:00 |
| `tipo` | enum(`normal`,`intensiva`) | |
| `status` | enum(`ativo`,`inativo`,`encerrada`) | |
| SoftDeletes | | necessário porque `inscricao_turma` referencia `turma_id` permanentemente para histórico — nunca apagar fisicamente uma turma com colocações associadas |

### `turma_sacramento` (pivot N:N)
Permite combinar uma turma com 1+ sacramentos — "1º Baptismo", "1º Baptismo e Comunhão", "1º Comunhão" como turmas distintas do mesmo `ano_catequetico_id`.

`turma_id` (restrictOnDelete), `sacramento_id` (restrictOnDelete), unique(`turma_id`,`sacramento_id`).

### `turma_catequista` (pivot N:N)
Um catequista pode ter várias turmas; uma turma pode ter vários catequistas (titular/auxiliar).

`turma_id`, `catequista_id` (ambos restrictOnDelete), `papel` enum(`titular`,`auxiliar`), `data_inicio` (histórico de atribuição, molde `fiel_centros`), `data_fim` nullable, unique(`turma_id`,`catequista_id`,`data_inicio`).

## 5. Catequizando

Decisão do utilizador: **entidade independente**, não obrigatoriamente um `Fiel`.

### `catequizandos`
| Campo | Tipo | Origem |
|---|---|---|
| `paroquia_id` | FK, restrictOnDelete | scope |
| `centro_id` | FK → centros, restrictOnDelete | centro atual |
| `fiel_id` | FK → fieis, nullable, restrictOnDelete | vínculo opcional |
| `nome_completo` | string(200) | ficha |
| `nome_pai` / `nome_mae` | string(150) nullable | "Filho(a) de / e de" |
| `profissao` | string(100) nullable | dos pais/encarregado |
| `municipio_nascimento` / `provincia_nascimento` | string(100) nullable | |
| `pais_nascimento` | string(80) default "Angola" | |
| `data_nascimento` | date NOT NULL | |
| `sexo` | enum(`M`,`F`) | |
| `residencia`, `rua_numero`, `edificio`, `casa_ap` | string nullable | |
| `numero_identificacao` | string(30) unique nullable | BI |
| `telefone`, `telefone_casa`, `email` | nullable | |
| `status` | enum(`ativo`,`inativo`) | |
| SoftDeletes | | dado sensível, mesmo padrão de `Fiel` |

### `catequizando_centros` (pivot histórico, molde `fiel_centros`)
Suporta transferência entre centros (decisão do utilizador: histórico completo, não só log).

`catequizando_id`, `centro_id` (ambos restrictOnDelete), `data_inicio` date, `data_fim` date nullable, `motivo_transferencia` string nullable, unique(`catequizando_id`,`centro_id`,`data_inicio`).

### `dados_religiosos` (1:1)
| Campo | Tipo |
|---|---|
| `catequizando_id` | FK unique, **restrictOnDelete** (não CASCADE — desvio deliberado do esboço original, para manter consistência com a regra do SGE de nunca apagar em cascata dados sensíveis) |
| `paroquia_baptismo`, `data_baptismo`, `pais_baptismo` | |
| `paroquia_comunhao`, `data_comunhao`, `pais_comunhao` | |
| `padrinho_nome`, `padrinho_telefone` | |
| `madrinha_nome`, `madrinha_telefone` | |
| `paroquia_transferencia`, `ano_transferencia` | |
| `pertence_grupo` | boolean default false |

## 6. Catequistas (mínimo — a expandir)

### `catequistas`
| Campo | Tipo |
|---|---|
| `paroquia_id` | FK, restrictOnDelete |
| `centro_id` | FK → centros, nullable (centro principal) |
| `fiel_id` | FK nullable, restrictOnDelete |
| `user_id` | FK → users, nullable — se tiver login próprio |
| `nome_completo` | string(150) |
| `telefone`, `email` | nullable |
| `ativo` | boolean default true |
| SoftDeletes | |

**Em aberto**: restante ficha do catequista (formação, disponibilidade, etc.) — aguardar especificação do utilizador antes de fechar a migration.

## 7. Inscrições

Decisão do utilizador: **uma única tabela** `inscricoes` (tipo `nova`/`confirmacao`), não duas tabelas separadas (`inscricoes`+`confirmacoes`) como no esboço original — a confirmação é a progressão ano-a-ano do mesmo catequizando.

Segunda decisão do utilizador (revista após o primeiro desenho): `inscricoes` **não tem `turma_id` directo**. A ficha de inscrição representa o vínculo do catequizando ao ano lectivo; a colocação numa turma concreta vive numa tabela à parte, `inscricao_turma`, exactamente para que trocar de turma nunca precise de tocar em `inscricoes` nem em `turmas` — só se fecha uma linha e abre-se outra em `inscricao_turma`.

### `inscricoes`
| Campo | Tipo | Notas |
|---|---|---|
| `paroquia_id` | FK, restrictOnDelete | scope |
| `centro_id` | FK → centros, restrictOnDelete | **forçado pelo Observer a partir do centro do utilizador autenticado** — nunca escolhido livremente no formulário (requisito explícito do utilizador) |
| `catequizando_id` | FK, restrictOnDelete | |
| `ano_letivo_id` | FK, restrictOnDelete | |
| `catequista_id` | FK nullable, restrictOnDelete | catequista que atendeu/processou a ficha — não é necessariamente quem lecciona a turma (isso fica em `turma_catequista`) |
| `tipo` | enum(`nova`,`confirmacao`) | `confirmacao` = progressão de ano lectivo |
| `inscricao_anterior_id` | FK → inscricoes, nullable, restrictOnDelete | trilha de progressão entre anos lectivos (linha do ano anterior → linha do ano atual) |
| `numero_ficha` | string unique | |
| `data_atendimento` | date | |
| `estado` | enum(`inscrito`,`aprovado`,`reprovado`,`desistente`,`cancelado`) | `aprovado` habilita a geração da inscrição do ano seguinte |
| `observacoes` | text nullable | |
| SoftDeletes | | |

**Regra de negócio central**: uma inscrição activa por `(catequizando_id, ano_letivo_id)` — activa = estado **≠** `cancelado`. MySQL não suporta unique parcial nativamente: implementar via validação na aplicação/Observer, no mesmo espírito do `codigo_dizimista` automático em `Fiel`.

**Fluxo de progressão** (entre anos letivos): catequizando aprovado no `1º Baptismo` em 2026/2027 → no ano seguinte gera-se nova linha em `inscricoes` (`tipo=confirmacao`), com `inscricao_anterior_id` a apontar para a linha do ano anterior, e uma nova linha em `inscricao_turma` ligando-a à turma do `2º ano`/2027-2028. Histórico nunca é sobrescrito.

### `inscricao_turma` (histórico de colocação em turma)
| Campo | Tipo | Notas |
|---|---|---|
| `inscricao_id` | FK → inscricoes, restrictOnDelete | |
| `turma_id` | FK → turmas, restrictOnDelete | |
| `status` | enum(`ativo`,`transferido`,`removido`) | apenas uma linha `ativo` por inscrição de cada vez — regra de aplicação, sem unique parcial nativa |
| `data_inicio` | date | |
| `data_fim` | date nullable | |
| `motivo` | string nullable | motivo da troca/remoção |
| unique | (`inscricao_id`,`turma_id`,`data_inicio`) | |

### 7.1 Troca de turma e mudança de centro (dentro do mesmo ano letivo)

Requisitos explícitos do utilizador:

- **Troca de turma (mesmo centro)**: a linha activa em `inscricao_turma` passa a `status=transferido` (`data_fim`=hoje); cria-se uma nova linha (`status=ativo`) ligada à nova `turma_id`, mesma `inscricao_id`. **`inscricoes` e `turmas` não são alterados** — é exactamente o problema que o utilizador queria evitar ao não ligar `turma_id` directamente à inscrição.
- **Mudança de centro é sempre acompanhada de mudança de turma** — não existe catequizando "sem turma" após mudar de centro. Ao registar uma nova linha em `catequizando_centros` (novo centro): o sistema fecha `data_fim` do registo activo anterior em `catequizando_centros` **e** fecha (`status=transferido` ou `removido`) a linha activa em `inscricao_turma` da turma do centro antigo — preparando o catequizando para uma nova linha em `inscricao_turma`, desta vez numa turma do novo centro. A `inscricao` em si mantém-se a mesma (é a mesma ficha do ano lectivo); só `catequizandos.centro_id` e, tipicamente, `inscricoes.centro_id` são actualizados para reflectir o centro corrente.
- `catequizandos.centro_id` é sempre actualizado para reflectir o centro corrente (`catequizando_centros` guarda o histórico).

**`centro_id` denormalizado em três tabelas** (decisão do utilizador, "para facilitar mais tarde"): `catequizandos.centro_id` (centro atual), `turmas.centro_id` (fixo, a turma pertence sempre a um centro), `inscricoes.centro_id` (forçado pelo Observer a partir do utilizador autenticado, actualizado nas transferências de centro). Estes devem estar coerentes com a turma activa em `inscricao_turma` — validar na aplicação (não é uma FK composta, é regra de negócio).

## 8. Pendências para próxima fase

- Especificação completa de **Catequistas** (utilizador vai enviar).
- Desenho do subsistema financeiro isolado da catequese (`tesoureiro_catequese`) — tabela de pagamentos, categorias, regras de estorno (aplicam-se as regras absolutas do CLAUDE.md: soft delete, nunca DELETE físico, Global Scope).
- Assiduidade/presenças (mencionado como responsabilidade do `secretario_catequese`) — provavelmente `presencas_catequese` (turma + catequizando + data + presente/falta), no espírito da Matriz de Dízimos já existente.
- Seed inicial de `anos_catequeticos` e `sacramentos` com os 8 níveis de catecismo descritos pelo utilizador (Deus Chamou, Deus Ama-nos, Creio em Jesus Salvador, Jesus é a Vida/Eleitos, Minha História Divina I/II, Discípulos de Cristo, Apóstolo de Cristo) — mapear cada nível para a combinação `ano_catequetico` + `sacramento(s)` correspondente.

## 9. Ordem de implementação sugerida

1. Migrations + models das tabelas de referência (`anos_letivos`, `anos_catequeticos`, `sacramentos`).
2. Migrations + models de `catequizandos`, `catequizando_centros`, `dados_religiosos`, `catequistas`.
3. Migrations + models de `turmas`, `turma_sacramento`, `turma_catequista`.
4. Migrations + models de `inscricoes` e `inscricao_turma` (depende de tudo acima).
5. Papéis novos no `RoleSeeder` + Policies (`CatequizandoPolicy`, `TurmaPolicy`, `InscricaoPolicy`, `CatequistaPolicy`).
6. Filament Resources (via agente `filament-builder`), com RelationManagers para `turma_sacramento`, `turma_catequista`, `catequizando_centros`, `inscricao_turma`.
7. Testes (via agente `test-writer`): isolamento multi-tenant por centro, unique de inscrição por ano letivo, troca de turma sem alterar `inscricoes`/`turmas`, trilha de progressão, soft delete.

**Estado actual**: passos 1–6 concluídos (migrations + models + papéis no RoleSeeder + Policies, incl. `AnoLetivoPolicy`/`AnoCatequeticoPolicy`/`SacramentoPolicy` para as tabelas de referência, não listadas inicialmente mas necessárias; e os 7 Filament Resources com RelationManagers, incl. as acções dedicadas "Trocar de Turma" e "Transferir de Centro"). `User::canAccessPanel()` também foi actualizado com os 4 papéis novos, para poderem entrar no painel Filament. Passo 7 (testes PHPUnit) por fazer.

Nota: como ainda não existe Observer próprio para `Catequizando`/`Catequista`/`Turma`/`Inscricao` (só `Centro`/`Fiel`/`CategoriaDespesa`/`Banco`/`User` têm `ForcaParoquiaUtilizadorObserver`), o reforço de `centro_id`/`paroquia_id` nas criações destes models está, por agora, só nas Pages Filament (`mutateFormDataBeforeCreate`) — funciona, mas é menos robusto do que um Observer (não protege chamadas fora do Filament, ex. um comando artisan ou um job). Considerar criar um Observer dedicado no futuro.

**Actualização**: `administrador_paroquial` já consegue registar `coordenador_catequese_paroquia` e `secretario_catequese` (pedido explícito do utilizador, para começar a testar) — `UserPolicy::PAPEIS_GERIVEIS` e `UserResource::papeisAtribuiveis()`/`PAPEIS_COM_CENTRO` foram actualizados. `coordenador_catequese_centro` e `tesoureiro_catequese` continuam reservados a `admin_geral` — nem `administrador_paroquial` nem `coordenador_catequese_paroquia` os conseguem atribuir ainda; a ideia original (`coordenador_catequese_paroquia` a delegar estes dois) continua por implementar, e não bloqueia os testes actuais porque o `coordenador_catequese_paroquia` já cobre tudo o que é paroquia-wide.

`PermissionSeeder` continua por tocar: `shield:generate` só cria permissions a partir de Resources Filament que existam — como já existem (passo 6 concluído), isto pode ser feito a seguir se for preciso o ecrã `/admin/shield/roles` reflectir a Catequese; não é bloqueante para testar via login directo com os papéis já seedados (a autorização real é sempre feita pelas Policies, não pelo Shield — ver docblock do `PermissionSeeder`).

## 10. Revisão pós-implementação (auditoria dos passos 1-6)

Feita a pedido do utilizador ("verificar o que ficou por fazer, para melhorar"), depois do passo 6 estar pronto. Dois problemas reais foram encontrados e corrigidos:

- **Bug de isolamento multi-tenant em `AnoLetivoResource`**: ao contrário de todos os outros Resources do módulo (`Catequizando`, `Catequista`, `Turma`, `Inscricao`), o `CreateAnoLetivo`/`EditAnoLetivo` não reforçavam `paroquia_id` no servidor — o campo só estava escondido no formulário para quem não é `admin_geral`, o que não impede adulteração do estado Livewire no cliente. Um `coordenador_catequese_paroquia` conseguiria, em teoria, criar/editar um Ano Lectivo de outra paróquia. **Corrigido**: `mutateFormDataBeforeCreate`/`mutateFormDataBeforeSave` adicionados, mesmo padrão do resto do módulo.
- **Regra "só um ano lectivo em_curso por paróquia" nunca estava implementada** — só existia como texto de ajuda no formulário, sem validação nenhuma. **Corrigido**: `->rule()` no campo `status`, mesmo padrão da regra de inscrição única por ano lectivo em `InscricaoResource`.

Também criado **`database/seeders/CatequeseSeeder.php`** (registado no `DatabaseSeeder`) com dados de referência mínimos — 6 `anos_catequeticos` (1º a 6º Ano) e 3 `sacramentos` (Baptismo, Comunhão, Crisma) — sem isto não era possível criar nenhuma Turma. Já corrido contra a BD de desenvolvimento.

## 11. Observações pós-testes manuais (2026-07-23)

O utilizador testou o módulo manualmente (login como `administrador_paroquial`/`coordenador_catequese_paroquia`) e pediu 7 ajustes, todos aplicados:

1. **Nº de ficha automático**, a partir de `0001`, reiniciando por `(paroquia_id, ano_letivo_id)` — `Inscricao::proximoNumeroFicha()` + hook `creating` no model (mesmo padrão do `codigo_dizimista` do Fiel). Campo deixou de ser editável no formulário (`InscricaoResource`). Migration `2026_07_24_000001` mudou o unique de global para composto — sem isto, duas paróquias/anos lectivos diferentes nunca poderiam ter ambos o "0001".
   - **Armadilha**: `DatabaseSeeder` usa `WithoutModelEvents`, que desliga o hook `creating` durante o seed (mesmo motivo pelo qual o `codigo_dizimista` é gerado manualmente em `DemoDataSeeder`) — `CatequeseDemoDataSeeder` chama `Inscricao::proximoNumeroFicha()` directamente em vez de depender do evento.
2. **Dashboard exclusivo para o pessoal da Catequese** — os 3 widgets financeiros (`EstatisticasGeraisWidget`, `ArrecadacaoBarChart`, `ArrecadacaoPieChart`) ganharam `canView()` a esconder-se dos 4 papéis de catequese; 3 widgets novos (`CatequeseEstatisticasWidget`, `CatequizandosPorTurmaChart`, `InscricoesPorEstadoChart`) só visíveis a esses papéis. Mesmo `/admin`, sem painel separado.
3. **Associar turma ↔ inscrição a partir da Turma** — `TurmaResource\RelationManagers\CatequizandosRelationManager` ganhou a acção "Adicionar Catequizando" (antes só leitura): escolhe-se o catequizando, reaproveita-se a inscrição activa dele no ano lectivo da turma (ou cria uma nova), e coloca-se/troca-se a colocação em `inscricao_turma` — mesma lógica de "Colocar/Trocar de Turma", só que a partir da turma.
4. **Auto-preencher nome ao seleccionar um Fiel** — `CatequizandoResource`/`CatequistaResource`: `fiel_id` ganhou `->live()->afterStateUpdated()` a copiar `Fiel::nome` para `nome_completo` (continua editável depois).
5. **Consistência da coluna de estado** — `TurmaResource` passou de `BadgeColumn` (3 valores) para `IconColumn::boolean()` rotulado "Activo", igual a `Catequista`/`Catequizando` (que já seguiam este padrão). O valor "encerrada" continua disponível via filtro, só a coluna de relance simplificou.
6. **Vagas mínimas/máximas na Turma** — migration `2026_07_24_000002` acrescentou `vagas_minimo`/`vagas_maximo` (nullable). Mostrado no formulário e como coluna "X / Y" na tabela (vermelho quando cheia). **Sem bloqueio automático ainda** — pedido explícito do utilizador para ficar "para mais tarde".
7. **Bug corrigido — `centro_id` nulo ao criar Inscrição como `coordenador_catequese_paroquia`**: este papel não tem `centro_id` próprio (é paroquia-wide), mas `CreateInscricao` forçava sempre `centro_id = utilizador->centro_id`. `InscricaoResource` ganhou um campo `centro_id` (Select, visível só para `admin_geral`/`coordenador_catequese_paroquia`, mesmo padrão de `TurmaResource`), e `scopePorCentro()` passou a considerar esse valor ao filtrar `catequizando_id`.

Migrations `2026_07_24_000001` e `2026_07_24_000002` já aplicadas na BD de desenvolvimento (dados existentes preservados — nenhuma tabela foi truncada).

Pendências que ficam por fazer (não corrigidas nesta revisão, sinalizadas para decisão futura):
- Passo 7 (testes PHPUnit).
- Observer dedicado para Catequizando/Catequista/Turma/Inscricao/AnoLetivo (o reforço servidor existe, mas vive nas Pages Filament, não num Observer central — funciona, mas não protege chamadas fora do Filament, ex. comandos artisan).
- `PermissionSeeder` não gera permissions Shield para os Resources da Catequese (cosmético, não bloqueia).
- Numeração dupla de ano catequético por público (crianças vs. adolescentes/jovens) continua por fazer — decisão consciente do utilizador de adiar.
- Subsistema financeiro do `tesoureiro_catequese` e tabela de presenças/assiduidade continuam por desenhar.
- Bloqueio automático ao atingir `vagas_maximo` numa turma — por agora só informativo (secç. 11, item 6).

## 12. Segunda ronda de observações pós-testes (2026-07-23, continuação)

1. **Nº de ficha no formato "F0001"** — `Inscricao::proximoNumeroFicha()` passou a devolver `'F'.str_pad(...)`, ignorando (tratando como 0) valores antigos que não sigam o padrão "F"+dígitos ao calcular o próximo número.
2. **Bug encontrado e corrigido durante esta ronda**: `CatequizandosPorTurmaChart` (widget do dashboard da Catequese) rebentava com `Unknown column 'pivot'` — `wherePivot()` não resolve dentro do subquery de `withCount()` nesta versão do Filament; substituído por `where('inscricao_turma.status', ...)` com o nome da tabela qualificado.
3. **`ano_catequetico_id` e `sacramentos` (multi-select) acrescentados a `inscricoes`** (migration `2026_07_24_000003`, nullable — reforçado como obrigatório só no formulário, para não arriscar falhar o backfill em dados que eu não conseguisse prever por completo). Nova tabela `inscricao_sacramento` (pivot simples, molde `turma_sacramento`). Backfill automático a partir da turma activa de cada inscrição já existente, feito em PHP (não SQL bruto) para ser portável entre MySQL e SQLite.
4. **Filtragem de turmas por ano catequético + conjunto exacto de sacramentos**: ao "Colocar/Trocar de Turma" (`InscricaoTurmaRelationManager`) e ao "Adicionar Catequizando" a partir da Turma (`CatequizandosRelationManager`), só aparecem turmas cujo `ano_catequetico_id` bate certo com o da inscrição **e** cujo conjunto de sacramentos é **exactamente igual** (não um subconjunto) — resolve o problema que o utilizador levantou: "Turma do 1º Baptismo", "Turma do 1º Baptismo e Comunhão" e "Turma do 1º Comunhão" são turmas distintas do mesmo ano catequético, e uma inscrição só pode ir para a que corresponde exactamente ao que o catequizando persegue.
5. **`CatequeseDemoDataSeeder` actualizado** para preencher `ano_catequetico_id`/`sacramentos` em cada inscrição nova, a partir da turma. Corrigida também uma inconsistência que introduzi nos próprios dados de demonstração: o exemplo de troca de turma (Divaldo, Turma A→B) mudava a colocação mas não actualizava os sacramentos da ficha — como a Turma A é "Baptismo+Comunhão" e a B é só "Comunhão", a ficha tinha de ser sincronizada também.

Migration `2026_07_24_000003` aplicada na BD de desenvolvimento. Backfill cobriu 23 das 24 inscrições reais existentes — a excepção (`Alberto Katema`, ficha `0002`, criada manualmente pelo utilizador antes de existir turma associada) ficou sem `ano_catequetico_id`/sacramentos por não ter turma activa da qual copiar; precisa de ser editada manualmente.

## 13. Terceira ronda de observações pós-testes (2026-07-23, continuação)

1. **`SacramentosRelationManager` só deixava anexar um sacramento de cada vez** — `AttachAction` do Filament é single-select por omissão; acrescentado `->multiple()` para poder marcar "Baptismo" + "Comunhão" na mesma acção.
2. **"Adicionar Catequizando" não verificava sacramentos, só ano catequético** — a ronda anterior (secç. 12, item 4) só filtrou por `ano_catequetico_id`; faltava mesmo a comparação do conjunto de sacramentos. Corrigido: agora um catequizando só aparece nas opções se não tiver nenhuma inscrição incompatível (ano catequético **e** conjunto exacto de sacramentos diferentes dos da turma) — testado com dados reais (catequizandos da "Turma A, Baptismo+Comunhão" ficam correctamente de fora das opções da "Turma B, só Comunhão", mesmo estando no mesmo centro). Inscrições sem ano catequético/sacramentos definidos ainda (dados antigos) continuam a aparecer como compatíveis, não são excluídas às cegas.

## 14. Quarta ronda de observações pós-testes (2026-07-23, continuação)

1. **Acção "Reactivar"** — `CatequizandosRelationManager` (Turma) e `InscricaoTurmaRelationManager` (Inscrição) ganharam uma acção "Reactivar", visível só em linhas `status=removido`. Nunca reescreve a linha removida (preserva o histórico) — cria sempre uma nova linha `status=ativo`, fechando primeiro (`status=transferido`) qualquer outra colocação activa que a inscrição tenha entretanto noutra turma. Testado via consulta directa à BD: histórico fica com as duas linhas (removida + nova activa), nunca uma só.
2. **Bloqueio manual por vagas** (migration `2026_07_24_000004`, campo `turmas.vagas_bloqueadas`, boolean, default `false`) — decisão explícita do utilizador: **nada bloqueia automaticamente**. Atingir `vagas_maximo` só mostra um alerta (descrição da tabela em `CatequizandosRelationManager`); quem gere a turma decide entre:
   - **"Bloquear Vagas"** — `vagas_bloqueadas=true`; desactiva (com tooltip a explicar) as acções "Adicionar Catequizando" e "Reactivar" nessa turma, nos dois RelationManagers.
   - **"Desbloquear Vagas"** — reverte.
   - **"Aumentar Vagas"** — atalho para mudar `vagas_maximo` sem abrir o formulário completo de edição da turma (mínimo validado ≥ vagas já ocupadas).
   `Turma::vagasOcupadas()`/`Turma::estaCheia()` centralizam o cálculo (antes estava duplicado inline em `TurmaResource`). O campo também ficou editável directamente no formulário principal da Turma (Toggle), para quem preferir gerir por ali.

## 15. Bug reportado ao testar "Reactivar" (2026-07-23, continuação)

**`Illuminate\Database\UniqueConstraintViolationException`** ao reactivar uma colocação removida na mesma turma, no mesmo dia: a unique `(inscricao_id, turma_id, data_inicio)` em `inscricao_turma` partia do princípio de que a data (sem hora) já distinguia episódios de colocação diferentes — falso quando se remove e reactiva (ou até só se adiciona duas vezes) na mesma turma no mesmo dia.

**Corrigido** (migration `2026_07_24_000005`): a unique foi substituída por um índice simples (`inscricao_id`, `turma_id`), só para performance de queries — a regra real ("só uma linha `status=ativo` por inscrição de cada vez") já era garantida pela aplicação, nunca dependeu desta unique key.

Armadilha ao aplicar em MySQL: `dropUnique()` sozinho falha com o erro 1553 ("needed in a foreign key constraint") — o MySQL recusa apagar um índice que cobre uma FK sem já existir outro a substituí-lo. O índice novo tem de ser criado **antes** de apagar o antigo (dois `Schema::table()` separados, nessa ordem), nunca ao contrário.

Mesma classe de risco existe, em teoria, nas unique keys equivalentes de `catequizando_centros` e `turma_catequista` (ambas `(..., data_inicio)`) — não foram tocadas porque ainda não houve um caso real, mas ficam sinalizadas caso apareça o mesmo erro noutro fluxo (ex.: transferir um catequizando de centro e trazê-lo de volta no mesmo dia).

## 16. Bug reportado — filtro "Estado" na aba Catequizandos da Turma (2026-07-23, continuação)

Mesma classe de bug do `CatequizandosPorTurmaChart` (secç. 13, item 2): o filtro "Estado" de `CatequizandosRelationManager` usava `wherePivot('status', ...)` dentro do `query()` do `SelectFilter`, e `wherePivot()` não resolve correctamente nesse contexto (gera `pivot = status` em vez de qualificar a tabela) — mesma causa raiz, mesma correcção (`where('inscricao_turma.status', ...)`).

Verifiquei todos os outros usos de `wherePivot()`/`withCount()` no código da Catequese: os restantes (ex.: `Turma::vagasOcupadas()`, o cálculo de `$idsJaAtivos` em "Adicionar Catequizando") são chamadas directas sobre uma relação nova, fora de um `query()` de filtro ou de `withCount()` — esses funcionam normalmente (já testados em rondas anteriores) e não precisam de correcção.
