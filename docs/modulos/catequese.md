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
| `nome_grupo` | string(150) nullable | só relevante quando `pertence_grupo=true` |

## 6. Catequistas (mínimo — a expandir)

### `catequistas`
| Campo | Tipo |
|---|---|
| `paroquia_id` | FK, restrictOnDelete |
| `centro_id` | FK → centros, nullable (centro principal) |
| `fiel_id` | FK nullable, restrictOnDelete |
| `user_id` | FK → users, nullable — se tiver login próprio |
| `nome_completo` | string(150) |
| `data_nascimento` | date, nullable |
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

## 17. Nome do Grupo (2026-08-15)

Campo `nome_grupo` (migration `2026_08_15_000001`, string(150) nullable, coluna nova em `dados_religiosos`) — pedido do utilizador: quando o catequizando pertence a um grupo, deve poder escrever o nome desse grupo. No formulário (`CatequizandoResource`, separador "Dados Religiosos"), o `Toggle` `pertence_grupo` ganhou `->live()` e o `TextInput` `nome_grupo` só aparece (`->visible()`) quando o toggle está activo.

## 18. Colocação em massa, bloqueio de reactivação e exportações (2026-08-16)

Três pedidos do utilizador sobre o fluxo de colocação em turma:

1. **"Adicionar Catequizando" passou a aceitar vários de uma vez** (`CatequizandosRelationManager`, Turma): o `Select` do formulário ganhou `->multiple()` (campo `catequizando_ids`, array), e a acção passou a percorrer a lista em loop, criando/reaproveitando a inscrição de cada um.
2. **A lista de opções deixou de incluir quem já está activo noutra turma** — antes só excluía quem já estava activo *nesta* turma (`$idsJaAtivos`); agora exclui quem está activo em **qualquer** turma do mesmo ano lectivo (`Inscricao::whereHas('turmaAtiva')`). Antes, escolher alguém já colocado noutro lado transferia-o em silêncio; isso deixou de ser possível a partir daqui — mover alguém de turma continua a ser feito exclusivamente por "Trocar de Turma" (`InscricaoTurmaRelationManager`, que já excluía a turma actual das opções). A acção mantém, mesmo assim, uma verificação defensiva no momento de gravar (contra uma colocação feita por outra pessoa enquanto o formulário estava aberto): se acontecer, esse catequizando é ignorado e listado num aviso, nunca transferido às escondidas.
3. **"Reactivar" (nos dois RelationManagers, `CatequizandosRelationManager` e `InscricaoTurmaRelationManager`) deixou de transferir automaticamente** quando a inscrição está activa noutra turma — antes fechava essa colocação e activava a nova sem perguntar; agora bloqueia com uma notificação de erro a identificar o nome da turma onde já está activo (via `Turma::descricaoCurta()`, método novo no model), e não faz nada até o utilizador remover a colocação antiga primeiro.

Também acrescentado `Turma::descricaoCurta()` (ex.: "1º Ano, manhã (09:00–10:00)"), reaproveitado no Select de "Colocar/Trocar de Turma" e nas mensagens de bloqueio acima — antes esse label estava duplicado inline com um travessão longo a separar as partes, unificado num único método.

## 19. Exportação Excel/PDF com botão único e escolha de Estado (2026-08-16)

Pedido do utilizador: em vez de dois botões separados ("Exportar Excel" / "Exportar PDF"), um único botão "Exportar" que agrupa as duas opções, e ao escolher qualquer uma delas abre um modal a perguntar que Estado incluir — se não escolher nada, assume o estado "activo" por omissão (excepto em Inscrições, ver abaixo). Pedido também para acrescentar a mesma exportação à lista de Catequistas, que ainda não tinha nenhuma.

**`App\Filament\Concerns\TemExportacaoComEstado`** (trait nova) — constrói o botão agrupado (`Tables\Actions\ActionGroup` com duas sub-acções, "Excel" e "PDF"), cada uma com um `->form()` de um único `Select` "Estado" (opções e valor por omissão vêm de quem chama) que, ao submeter, redirecciona para a rota de exportação correspondente com `?estado=`. Não acede a `$this` directamente — quem chama passa via `parametrosRota` uma closure com o que for preciso do próprio contexto (ex.: id da turma), para funcionar tanto em Resources (`table()` estático) como em RelationManagers (`table()` de instância). Reaproveitada em 4 sítios:

| Lista | Opções de Estado | Por omissão | Rotas |
|---|---|---|---|
| Catequizandos de uma Turma (`CatequizandosRelationManager`) | Todos / Activo / Transferido / Removido (estado da colocação em `inscricao_turma`) | Activo | `relatorios.turma-catequizandos.{excel,pdf}` |
| Catequizandos (`CatequizandoResource`) | Todos / Activo / Inactivo | Activo | `relatorios.catequizandos.{excel,pdf}` |
| Catequistas (`CatequistaResource`) | Todos / Activo / Inactivo | Activo | `relatorios.catequistas.{excel,pdf}` |
| Inscrições (`InscricaoResource`) | Todos / Inscrito / Aprovado / Reprovado / Desistente / Cancelado | **Todos** — o `estado` de Inscrição não tem um valor "activo" equivalente aos outros três, por isso não filtra nada por omissão (decisão da IA, sinalizada aqui para o utilizador corrigir se preferir outro valor por omissão) | `relatorios.inscricoes.{excel,pdf}` |

`EstadoInscricaoTurma` ganhou um método `label()` (Activo/Transferido/Removido), substituindo o `match` que estava duplicado em 4 sítios (`CatequizandosRelationManager`, `InscricaoTurmaRelationManager`, a view PDF da turma, e agora também a rota de exportação Excel da turma).

As rotas Excel deixaram de usar `pxlrbt/filament-excel` (que não dava para combinar facilmente com um `Select` de estado dentro do próprio modal) — passaram a seguir o mesmo padrão `ArrayExport`+`Excel::download()` já usado pelos relatórios financeiros do Módulo 7. Todas as rotas (Excel e PDF, para as 4 listas) seguem o mesmo critério de RBAC das Policies do módulo (`admin_geral`, `coordenador_catequese_paroquia`, `coordenador_catequese_centro`, `secretario_catequese`, `tesoureiro_catequese`), com o mesmo reforço de `centro_id` para quem só gere/lê o seu próprio centro — mesma ressalva do Módulo 7: a rota replica o critério da Policy em vez de o reutilizar, por isso uma mudança de RBAC numa Policy do módulo tem de ser replicada manualmente aqui também.

### 19.1 Ajustes pós-teste (2026-08-16, continuação)

Quatro pedidos do utilizador depois de testar o fluxo de exportação:

1. **Bug reproduzido ao exportar a Turma — mesma classe do `wherePivot()` das secç. 12/16**: o filtro de Estado em `/relatorios/turma/{turma}/catequizandos/{excel,pdf}` usava `$turma->inscricoes()->when(...)->wherePivot('status', $estado)`, e mesmo sendo (aparentemente) uma chamada directa sobre uma relação nova, o `wherePivot()` dentro do closure do `->when()` gerava a mesma SQL quebrada (`where \`pivot\` = status`, ignorando o valor real de `$estado`). Corrigido com o mesmo padrão já estabelecido: `where('inscricao_turma.status', $estado)`, nome da tabela qualificado directamente. Lição reforçada: `wherePivot()` não é de confiar dentro de **qualquer** closure passado a `when()`/`filter()`/etc. neste código-base — usar sempre `where('inscricao_turma.status', ...)`.
2. **Coluna "Nº" (número de ordem)** acrescentada às 4 listas exportadas (Excel e PDF): Catequizandos de uma Turma, Catequizandos, Catequistas, Inscrições — primeira coluna, 1 a N conforme a ordem devolvida pela query.
3. **Modal do "Exportar" reduzido para `sm`** (`->modalWidth('sm')` nas duas sub-acções, Excel e PDF, dentro de `TemExportacaoComEstado`) — só há um campo (Estado), o modal por omissão do Filament era desproporcionalmente grande.
4. **PDF agora abre numa nova aba** — mas não via `$this->js('window.open(...)')`: os closures das acções vivem num método **estático** da trait (`accaoExportarComEstado`), por isso nunca têm `$this` ligado ao componente Livewire (uma limitação do próprio PHP, closures definidos dentro de um método estático não têm contexto de objecto, independentemente de quem chama o método) — e mesmo que tivessem, um `window.open()` disparado depois do pedido AJAX do formulário arrisca ser bloqueado pelo browser como pop-up. Solução usada: a sub-acção "PDF" já não redirecciona directamente — envia uma notificação Filament de sucesso com um botão "Abrir PDF" (`Filament\Notifications\Actions\Action` com `->url()->openUrlInNewTab()`), garantindo que a nova aba abre sempre a partir de um clique genuíno do utilizador (sem depender de heurísticas de pop-up do browser). O Excel manteve-se como redirecionamento directo (download de ficheiro não é bloqueado como pop-up, e não faz sentido pedir confirmação extra para isso).

### 19.2 Filtro de Ano Lectivo nas exportações (2026-08-16, continuação)

Pedido do utilizador: além do Estado, filtrar também por Ano Lectivo nos locais onde fizer sentido — o trabalho do módulo é sempre organizado por ano lectivo. `accaoExportarComEstado()` ganhou o parâmetro opcional `comAnoLectivo: true`, que acrescenta um segundo `Select` ("Ano Lectivo", opções "Todos" + `AnoLetivo` da paróquia, por omissão o ano `em_curso`) ao mesmo modal `sm`, e passa `?ano_letivo=` na rota gerada.

### 19.3 Selector de Centro nas exportações (2026-08-17)

Pedido do utilizador: quem não está preso a um centro (`admin_geral`, `coordenador_catequese_paroquia`) deve poder escolher um centro específico ou "Todos" ao exportar — antes, essas 3 listas (Catequizandos, Catequistas, Inscrições) nunca tinham selector nenhum, o `centro_id` só era forçado para quem já estava preso a um. `accaoExportarComEstado()` ganhou o parâmetro opcional `comCentro: true`, que acrescenta um terceiro `Select` ("Centro", opções "Todos os centros" + `Centro` da paróquia, por omissão "Todos") ao mesmo modal `sm` — escondido para os papéis presos a um centro (`coordenador_catequese_centro`, `secretario_catequese`, `tesoureiro_catequese`, constante `PAPEIS_CENTRO_CATEQUESE` na trait), que nunca o veem. Activo nos mesmos 3 locais do Ano Lectivo (não em Catequizandos de uma Turma, pela mesma razão — a turma já pertence a um único centro).

As 3 rotas correspondentes (`routes/web.php`) passaram a resolver o centro via **`App\Support\ResolveCentroExportacao::centroCatequese()`** (partilhada com o Módulo 7) em vez do `hasRole([...]) ? $user->centro_id : null` inline de antes — mesmo critério de sempre (papéis presos a centro forçados, ignorando o parâmetro; os restantes usam `?centro_id=` se válido) mas agora também aceitam a escolha explícita. A mesma função resolve ainda a paróquia do cabeçalho do PDF (`$user->paroquiaParaExportacao($centro)`, Módulo 7) — corrige de caminho um bug relacionado reportado pelo cliente: nenhum PDF da Catequese mostrava o logotipo da paróquia carregado (Módulo 2), porque `admin_geral` não tem `paroquia_id` próprio.

Activo em 3 dos 4 locais — o quarto (Catequizandos de uma Turma) não se aplica, porque a turma já pertence a um único `ano_letivo_id`, filtrar de novo seria redundante:

| Lista | Como filtra por ano lectivo | Porquê essa forma |
|---|---|---|
| Catequizandos (`CatequizandoResource`) | `whereHas('inscricoes', fn ($q) => $q->where('ano_letivo_id', ...))` | `Catequizando` não tem `ano_letivo_id` próprio — o vínculo a um ano lectivo é sempre via `Inscricao` |
| Catequistas (`CatequistaResource`) | `whereHas('turmas', fn ($q) => $q->where('ano_letivo_id', ...))` | idem, mas via `turma_catequista`; `ano_letivo_id` vive na própria tabela `turmas` (não no pivot), por isso não há aqui o risco do bug de `wherePivot()` da secç. 19.1 |
| Inscrições (`InscricaoResource`) | `where('ano_letivo_id', ...)` directo | `inscricoes.ano_letivo_id` é uma coluna própria |

### 19.3 Nome de ficheiro descritivo ao exportar (2026-08-16, continuação)

Pedido do utilizador: os ficheiros descarregados deixaram de ter nome genérico (`catequizandos.xlsx`, `inscricoes.pdf`) e passaram a incluir o que foi realmente exportado — padrão `{lista}_{ano-lectivo}_{estado}` (ex.: `inscricoes_2026-2027_todos.xlsx`), ou `{lista}_{turma}_{estado}` no caso da Turma (que não tem selector de Ano Lectivo, ver secç. 19.2).

`AnoLetivo::slugParaExportacao(string $anoLetivo)` (novo método estático) converte o valor bruto de `?ano_letivo=` ("todos" ou um id) no trecho do nome do ficheiro — **armadilha encontrada e corrigida duas vezes**: `Str::slug('2026/2027')` sozinho engole a barra e dá `20262027` (ilegível, os dois anos colados); trocar a barra por `-` antes de chamar `Str::slug()` também não chega, porque o `Str::slug()` normaliza esse `-` de volta ao separador por omissão independentemente do valor pedido. A correcção final troca a barra por um **espaço** (o único carácter que o `Str::slug()` reconhece sempre como fronteira de palavra, seja qual for o separador) e pede separador `_` explicitamente — resultado "2026_2027", a pedido do utilizador (ficheiros como `inscricoes_2026_2027_todos.xlsx`).

### 19.4 PDF da Turma com Sacramento(s) e Catequista(s) (2026-08-16, continuação)

Pedido do utilizador: o PDF de catequizandos de uma turma (`pdfs.relatorios.turma-catequizandos`) passou a mostrar, no cabeçalho, o(s) Sacramento(s) da turma (`$turma->sacramentos`) e o(s) Catequista(s) actualmente atribuídos (`$turma->catequistas()->wherePivotNull('data_fim')->get()`, com o papel Titular/Auxiliar entre parêntesis) — `data_fim IS NULL` no pivot `turma_catequista` é o mesmo critério de "vínculo activo" já usado em `CatequistasRelationManager` (coluna "Fim" mostra "Activo" quando `null`). Só no PDF, não no Excel (não pedido).

## 20. Sacramento "Perseverança" (2026-08-16, continuação)

Pedido do utilizador: acrescentado um 4º sacramento de referência, `Perseverança` (`ordem` 4), a seguir a Baptismo/Comunhão/Crisma — `database/seeders/CatequeseSeeder.php` actualizado e já corrido contra a BD de desenvolvimento (`firstOrCreate`, idempotente — não duplicou nem tocou nos 3 existentes).

## 21. Data de Nascimento do Catequista + cópia alargada do Fiel (2026-08-16, continuação)

Pedido do utilizador: `catequistas` ganhou a coluna `data_nascimento` (migration `2026_08_16_000003`, date nullable, a seguir a `nome_completo`) — mostrada no formulário (`DatePicker`, `maxDate(now())`) e como coluna toggleable na tabela.

O `Select` `fiel_id` de `CatequistaResource` já copiava `nome` para `nome_completo` ao seleccionar um Fiel (`->afterStateUpdated()`); passou a copiar também `data_nascimento`, `telefone` e `email` do Fiel para os campos correspondentes do Catequista, na mesma acção — tudo continua editável a seguir, a cópia é só um ponto de partida.

## 22. Auditoria de filtragem por Centro (2026-08-16, continuação)

Pedido do utilizador: verificação geral a todos os campos dependentes de Centro no módulo — sempre que se escolhe (ou já se está preso a) um Centro, os campos de Fiel/Catequista devem obedecer a esse filtro. Feita por um agente de exploração (só leitura) e corrigida a seguir. Achados e correcções:

- **`CatequizandoResource`, `CatequistaResource`**: campo `fiel_id` listava todos os Fiéis da paróquia, sem filtrar pelo Centro escolhido — corrigido com `relationship(..., modifyQueryUsing: ...)` filtrando `status=ativo` e `whereHas('centros', ...)` pelo `centro_id` (mesmo padrão já usado em `MovimentoResource::fiel_id`). `centro_id` ganhou `->live()` nos dois Resources (sem isso, os campos dependentes nunca reagiam à escolha) e `afterStateUpdated` a limpar `fiel_id` ao trocar de centro.
- **`CatequistaResource`**: campo `user_id` (login vinculado) não filtrava por paróquia nenhuma — `User` não tem `ParoquiaScope` própria. Corrigido a filtrar por `paroquia_id` (do campo, ou do utilizador autenticado). `paroquia_id` também ganhou `->live()`.
- **`InscricaoResource`**: campo `catequista_id` não usava o `scopePorCentro()` já existente no próprio Resource (o campo `catequizando_id` ao lado já usava) — corrigido a reaproveitar o mesmo método.
- **`TurmaResource\RelationManagers\CatequistasRelationManager`** e **`CatequistaResource\RelationManagers\TurmasRelationManager`**: a acção "Anexar" (`AttachAction::getRecordSelect()`) não filtrava por centro — corrigido com `->recordSelectOptionsQuery(...)`. No lado do Catequista → Turmas, `centro_id` do catequista é nullable ("centro principal"); só filtra quando definido, para não esconder todas as turmas de um catequista sem centro fixo (paróquia inteira).
- **`TurmaResource`**: `centro_id` ganhou `->live()` por consistência/pré-requisito, apesar de o próprio form da Turma não ter hoje nenhum campo dependente directo.

Sem achados em `getEloquentQuery()` dos Resources (todos já restringiam correctamente por centro) nem em `MatrizDizimos`/`MatrizAssiduidadeReport` (já bem implementados via `FiltraMatrizDizimos`).

Testado em `tests/Feature/CatequeseFiltroPorCentroTest.php` (3 casos: `fiel_id` do Catequizando, `fiel_id` do Catequista, `catequista_id` da Inscrição — todos confirmando que um registo de outro centro nunca aparece nas opções).

## 23. Gráficos do dashboard em linha própria (2026-08-16, continuação)

Mesmo pedido do utilizador que motivou o ajuste equivalente no Demonstrativo de Receitas/Despesas (`docs/modulos/04-movimentos-conciliacao.md`): os `ChartWidget` do dashboard principal (`CatequizandosPorTurmaChart`, `InscricoesPorEstadoChart`) ganharam `$columnSpan = 'full'` e `$maxHeight = '320px'`, para nunca ficarem lado a lado de forma desproporcionada — mesmo ajuste aplicado aos gráficos financeiros (`ArrecadacaoBarChart`/`PieChart`, `DespesasBarChart`/`PieChart`). Os widgets de estatística (`CatequeseEstatisticasWidget`, `EstatisticasGeraisWidget`) não foram tocados — cartões lado a lado é o layout normal desse tipo de widget, não o problema reportado.

## 24. Sacramento(s) no label do gráfico "Catequizandos por Turma" (2026-08-16, continuação)

Pedido do utilizador: o label de cada barra ("1º Ano · Manhã") não distinguia turmas diferentes com o mesmo ano catequético/período — passou a incluir o(s) sacramento(s) entre parênteses no fim (ex.: "1º Ano · Manhã (Baptismo, Comunhão)"), a mesma informação que já distingue turmas em `InscricaoTurmaRelationManager`/`CatequizandosRelationManager` (docs secç. 12). A query ganhou eager-load de `sacramentos` (`->with(['anoCatequetico', 'sacramentos'])`) para não gerar N+1. Nota: mesmo com sacramentos no label, ainda é possível duas turmas ficarem com o label idêntico (confirmado com dados reais: duas turmas "1º Ano · Manha (Baptismo)" distintas) — isso só acontece quando são mesmo a mesma combinação ano/período/sacramentos (ex. turmas paralelas na mesma faixa horária), o que está fora do que foi pedido.

## 25. Importação em massa de Catequizandos e Catequistas (2026-08-16, continuação)

Pedido do utilizador: "fechar a sessão com chave de ouro" — importação em massa via Excel/CSV, mesmo padrão já usado para Fiéis (`App\Filament\Imports\FielImporter`, Módulo 3). Dois `Importer` novos, espelhados campo a campo:

- **`CatequizandoImporter`**: colunas `nome_completo`/`data_nascimento`/`sexo` obrigatórias (`data_nascimento` é `NOT NULL` na BD, ao contrário do Fiel), mais `nome_pai`, `nome_mae`, `numero_identificacao`, `telefone`, `email`, `residencia`, `status`. `afterCreate()` grava também a primeira linha em `catequizando_centros` (mesmo que `CreateCatequizando::afterCreate()`).
- **`CatequistaImporter`**: `nome_completo` obrigatório, mais `data_nascimento`, `telefone`, `email`, `ativo` (texto "ativo"/"inativo" no ficheiro, convertido para booleano — ver armadilha na secç. 26).

Em ambos, `centro_id` nunca vem do ficheiro — escolhido uma vez no modal de importação (`getOptionsFormComponents()`) e aplicado a todas as linhas, com `paroquia_id` derivado desse centro e validado contra a paróquia de quem importa (`beforeCreate()`, mesma defesa "nunca confiar em dados externos" já usada no `FielImporter`). Botão "Importar" nas listagens (`ListCatequizandos`/`ListCatequistas`), visível com a mesma Policy do `CreateAction` (`can('create', ...)`).

**Centro pré-seleccionado e bloqueado para quem já está preso a um centro**: pedido de seguida pelo utilizador — `coordenador_catequese_centro`/`secretario_catequese`/`tesoureiro_catequese` veem logo o seu próprio centro no modal de importação e não conseguem escolher outro; só `admin_geral`/`coordenador_catequese_paroquia` (paróquia inteira) escolhem livremente. Mesmo critério `GESTORES_CENTRO_LIVRE` já usado nos Resources da Catequese. Ver armadilha séria na secç. 26 sobre a primeira tentativa desta implementação.

Testado em `tests/Feature/CatequizandoImporterTest.php`, `tests/Feature/CatequistaImporterTest.php` (chamada directa ao Importer, mesmo padrão do `FielImporterTest`) e `tests/Feature/ImportCentroPreSelecionadoTest.php` (pré-selecção/bloqueio do centro).

## 26. Bug crítico: importação "não fazia nada" — `visible(false)` esconde o valor do formulário de opções (2026-08-16, continuação)

Bug reportado pelo utilizador depois da secç. 25: "carrego o ficheiro, diz que vai processar em segundo plano, depois nada, os dados não persistem". Diagnóstico via `docker compose logs worker` + inspecção directa da tabela `imports`/`failed_import_rows` (`Filament\Actions\Imports\Models\Import::latest()->first()->failedRows`) — todas as linhas falhavam com "Centro inválido para a paróquia de quem está a importar.", mesmo com um centro válido escolhido.

**Causa raiz**: a primeira versão da funcionalidade da secç. 25 escondia o campo `centro_id` do modal de opções com `->visible(false)` (mais `->default()`) para o pré-seleccionar e bloquear. Este padrão funciona num formulário normal de Resource (Create/Edit), mas **não** num `ImportAction`: `Filament\Actions\Concerns\CanImportRecords` constrói o array `$options` enviado ao job da fila a partir de `$form->getState()` (`array_except($data, ['file', 'columnMap'])`), e o `getState()` de uma acção **não inclui campos invisíveis** — ao contrário do que já se via nos forms de `CatequizandoResource`/`CatequistaResource`/`MovimentoResource` (onde a combinação visible(false)+default() sempre funcionou, incl. reforçada por `mutateFormDataBeforeCreate`). O resultado: `$this->options['centro_id']` chegava vazio a cada linha, `(int) (null ?? 0)` dava `0`, e a validação contra a paróquia falhava sempre — em silêncio, sem nenhum erro visível na UI (só visível ao abrir o registo de `Import` e ver `failed_import_rows`).

**Correcção**: trocado `->visible(false)` por `->disabled(...)` (mantém o campo visível, só bloqueado — mostra ao utilizador qual o centro que vai ser usado) **+ `->dehydrated()`** explícito, porque campos desactivados também são excluídos do `getState()` por omissão. Aplicado a `CatequizandoImporter` e `CatequistaImporter`.

**Bug secundário encontrado na mesma investigação**: os jobs `Filament\Notifications\DatabaseNotification` (usados pela notificação de "importação concluída") falhavam sempre com `Table 'sge_db.notifications' doesn't exist` — a tabela `notifications` (canal `database` do Laravel) nunca tinha sido criada, porque a única notificação do projecto até agora (`ComprovativoPendenteNotification`) só usa o canal `mail`. Corrigido com a migration `2026_08_16_000004_create_notifications_table` (schema padrão do Laravel: `id` uuid, `type`, `notifiable_type`/`notifiable_id`, `data`, `read_at`, timestamps). Sem esta tabela, mesmo depois de corrigido o bug do `centro_id`, a notificação de conclusão continuaria a falhar em silêncio — o utilizador via "vai processar em segundo plano" e nunca mais nada, exactamente o sintoma reportado.

**Lição para o resto do código**: `assertActionDataSet()` (usado em `ImportCentroPreSelecionadoTest`) só verifica o estado do formulário montado no browser — **não** apanha este bug, porque o valor está lá até ao momento de `getState()` filtrar campos invisíveis na acção real. A correcção só foi confirmada com um teste que sobe mesmo um CSV através da acção real (`tests/Feature/ImportViaAcaoRealTest.php`, `QUEUE_CONNECTION=sync` em `phpunit.xml` corre o job na hora) e verifica o registo criado na BD — esse teste teria apanhado o bug original, os anteriores não apanhavam.

**Terceiro bug, encontrado ao confirmar a correcção do segundo**: mesmo depois da migration `2026_08_16_000004_create_notifications_table`, o utilizador continuou a não ver a mensagem de conclusão — mesmo numa importação de Catequistas 100% bem sucedida (4/4). Verificado directamente na BD: a notificação **estava** a ser gravada correctamente na tabela `notifications` (`Filament\Notifications\DatabaseNotification`, sem nenhum `failed_jobs` novo). O problema real: o painel Filament (`AdminPanelProvider`) nunca tinha `->databaseNotifications()` activado — sem isso não existe **nenhuma superfície na UI** (o "sino" no topo do painel) a mostrar essas notificações, por mais que a tabela esteja correctamente preenchida. Corrigido em `app/Providers/Filament/AdminPanelProvider.php`.

**`FielImporter` preparado para o mesmo padrão de centro bloqueado**: pedido do utilizador ("também poderemos ter o coordenador do centro") — `getOptionsFormComponents()` ganhou o mesmo `->default()`+`->disabled()`+`->dehydrated()` de `CatequizandoImporter`/`CatequistaImporter`, condicionado a `['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial']` (papéis livres actuais). Sem efeito prático agora — `FielPolicy::create()` continua a não incluir `tesoureiro_centro` (decisão explícita do utilizador de não mexer na Policy agora), fica só pronto para o dia em que esse papel ganhar permissão de importar.

## 27. Menu "Catequizandos", registo duplo e progressão de ano a partir da lista (2026-09-03)

Pedido do utilizador: eliminar as "duas voltas" de primeiro registar o Catequizando e só depois ir a Inscrições criar a ficha do ano lectivo, e dar à lista de Catequizandos uma noção de ano lectivo (até aqui não tinha nenhuma).

**Menu**: `CatequizandoResource` ganhou `getNavigationItems()`, substituindo o item único automático por dois `NavigationItem` no grupo novo `Catequizandos` — **Inscrever** (`->url(getUrl('create'))`) e **Listar** (`->url(getUrl('index'))`). O grupo é colapsável (`->collapsed()` em `AdminPanelProvider::navigationGroups()`) para se comportar como um verdadeiro dropdown — o Filament v3 não tem submenus de 2 níveis, um grupo colapsável fechado por omissão é a aproximação mais próxima. `navigationGroups()` também passou a declarar a ordem explícita de todos os grupos do painel (antes implícita/alfabética), para "Catequizandos" cair a seguir a "Catequese" e não no fim da lista.

**Inscrever = registo duplo numa transacção**: `CatequizandoResource::form()` ganhou um separador "Inscrição", visível só no `create` (`Tab::make('Inscrição')->visible(fn (string $operation) => $operation === 'create')`), com um `Group::make()->statePath('inscricao')` a evitar colisão com os campos do Catequizando. **Decisão consciente**: os campos deste separador não usam `->relationship()`/magia de gravação automática — `sacramentos`, por exemplo, não é sequer uma relação do `Catequizando`, só da `Inscricao` que ainda não existe no momento do render. `CreateCatequizando::mutateFormDataBeforeCreate()` extrai o bloco `inscricao` do array antes de gravar o Catequizando; `afterCreate()` cria a `Inscricao` (`tipo=nova`, `centro_id`/`paroquia_id` copiados do Catequizando recém-criado, nunca escolhidos de novo) e sincroniza os sacramentos. A página ganhou `hasDatabaseTransactions(): bool => true` (trait `CanUseDatabaseTransactions`, já usada pelo Filament) — sem isto, um erro ao criar a `Inscricao` deixaria o Catequizando gravado sozinho, exactamente o problema que este formulário existe para evitar. **Decisão do utilizador**: esta opção serve só para catequizandos novos — quem já existe usa o `InscricaoResource` directamente ou a acção "Confirmar para o Próximo Ano Lectivo" abaixo.

**Filtro de Ano Lectivo na lista**: `CatequizandoResource::table()` ganhou `->filtersLayout(FiltersLayout::AboveContent)` (filtro sempre visível, não escondido no menu de filtros — é o filtro principal do módulo) e um `SelectFilter::make('ano_letivo')` com `->query()` manual (`whereHas('inscricoes', ...)`), por omissão o ano `em_curso`. Importante: o filtro decide **quem aparece** na lista (quem tem inscrição nesse ano), nunca altera os dados do próprio Catequizando — esses continuam sempre os actuais, só a `Inscricao` é anual. Duas colunas novas (`ano_catequetico_actual`, `estado_inscricao_actual`) lêem a inscrição do ano filtrado via o helper novo `Catequizando::inscricaoNoAnoLetivo()` (devolve a inscrição não-cancelada desse ano, a partir da relação já carregada — `getEloquentQuery()` ganhou `->with(['inscricoes.anoCatequetico', 'inscricoes.sacramentos', 'inscricoes.anoLetivo', 'inscricoes.catequista', 'inscricoes.turmaAtiva.turma'])` para não gerar N+1). Este helper é reaproveitado pelas duas acções novas abaixo, sempre lendo o ano lectivo actualmente seleccionado no filtro (`CatequizandoResource::anoLetivoIdFiltrado()`, via `$livewire->getTableFilterState('ano_letivo')`).

**Acções da linha agrupadas** (`ActionGroup`, mesmo padrão do commit "agrupa ações da tabela de Movimentos num menu"):
- **Ver ficha** — página nova `ViewCatequizando` (`ViewRecord` sem `infolist()` próprio: sem componentes registados, o Filament reaproveita `CatequizandoResource::form()` em modo só-leitura — o separador "Inscrição" fica escondido automaticamente, por já só ser visível no `create`).
- **Editar** — inalterado.
- **Dados de Catequese** — modal só-leitura (`Forms\Components\Placeholder`, `->modalSubmitAction(false)`) com a ficha da inscrição do ano filtrado (nº ficha, tipo, ano catequético, sacramentos, estado, catequista, turma via `Turma::descricaoCurta()`), mais um botão no rodapé do modal (`->extraModalFooterActions()`) a abrir a ficha completa no `InscricaoResource`. Desactivada (`->disabled()`) e com `->tooltip()` a explicar quando não há inscrição no ano seleccionado.
- **Confirmar para o Próximo Ano Lectivo** — gera uma nova `Inscricao` (`tipo=confirmacao`, `inscricao_anterior_id` a apontar para a inscrição do ano actual), mesmo espírito do "Fluxo de progressão" descrito na secção 7. **Desvio consciente da secção 7** ("`aprovado` habilita a geração da inscrição do ano seguinte"): decisão explícita do utilizador nesta ronda foi permitir a confirmação em **qualquer estado excepto `cancelado`**, não só `aprovado` — mais flexível para uso real, sinalizado aqui para referência futura caso se queira reverter. O modal pré-preenche o Ano Lectivo destino (o `AnoLetivo` seguinte por `data_inicio`), o Ano de Catequese (o `AnoCatequetico` seguinte por `ordem`) e os sacramentos (copiados da inscrição actual), todos editáveis. Mesma regra de unicidade da secção 7 (`(catequizando_id, ano_letivo_id)` não pode ter duas inscrições não-canceladas), validada por `->rule()` no campo Ano Lectivo. Sem colocação em turma — isso continua exclusivo das acções dedicadas já existentes (`InscricaoTurmaRelationManager`).

Testado em `tests/Feature/InscreverCatequizandoTest.php` (registo duplo + rollback transaccional) e `tests/Feature/ConfirmarProximoAnoLetivoTest.php` (trilha de progressão + bloqueio de duplicado + estados aceites).

## 28. Correcção: número de ficha é por (paróquia, centro), não só por paróquia (2026-09-03, continuação)

Bug reportado pelo utilizador depois da secção 27: a numeração de `numero_ficha` (secção 11, item 1) reiniciava por `(paroquia_id, ano_letivo_id)` desde a primeira versão do módulo — errado, porque os centros são independentes entre si. Duas paróquias com vários centros nunca conseguiam ter dois catequizandos de **centros diferentes** com "F0001" no mesmo ano lectivo; a numeração era partilhada por toda a paróquia. O correcto é reiniciar por `(centro_id, ano_letivo_id)`, tal como as fichas de papel originais — cada centro com a sua própria contagem.

**Decisão do utilizador, ao rever a primeira correcção**: o scope final tem de incluir `paroquia_id` **e** `centro_id`, não só `centro_id`. Tecnicamente é redundante — um centro pertence sempre a uma única paróquia, nunca muda — mas o isolamento entre paróquias diferentes na mesma BD deve ficar explícito na própria constraint, no mesmo espírito de "defesa em profundidade" já usado no resto do projecto (`ParoquiaScope` em quase todos os models, mesmo quando o isolamento seria tecnicamente derivável via join). Nunca depender só da FK de `centro_id` para garantir isolamento entre paróquias.

**Corrigido**:
- `Inscricao::proximoNumeroFicha()` passou a receber `(paroquia_id, centro_id, ano_letivo_id)` (assinatura mudou duas vezes nesta ronda — primeiro só para `centro_id`, depois corrigida para incluir `paroquia_id` também); o hook `creating` em `Inscricao::booted()` idem.
- `database/seeders/CatequeseDemoDataSeeder.php` (única outra chamada directa, por causa do `WithoutModelEvents` do `DatabaseSeeder` — ver secção 11) passou a chamar com `$paroquia->id, $turma->centro_id`.
- Migration `2026_09_03_000001_alter_numero_ficha_unique_scope_to_centro_on_inscricoes_table` troca o unique `(paroquia_id, ano_letivo_id, numero_ficha)` por `(paroquia_id, centro_id, ano_letivo_id, numero_ficha)`.

**Armadilha encontrada na primeira tentativa (corrigida antes de chegar à versão final)**: a primeira versão desta migration pôs `centro_id` como **primeira** coluna do unique novo (sem `paroquia_id`). Ao criar esse unique, o MySQL **absorveu silenciosamente** o índice dedicado que sustentava a foreign key de `centro_id` (`inscricoes_centro_id_foreign` desapareceu do `SHOW INDEX` sem nenhum `dropIndex` explícito) — o índice composto passou a ser o único a cobrir essa FK, e reverter (`down()`) falhava com o erro 1553 ("needed in a foreign key constraint"). A versão final evita o problema pela raiz: com `paroquia_id` como primeira coluna do unique (não `centro_id`), o índice novo cobre a FK de `paroquia_id`, e `centro_id` — por nunca ser a coluna mais à esquerda de nenhum índice tocado por esta migration — mantém sempre o seu próprio índice de FK intacto, sem precisar de nenhum índice extra de salvaguarda em `up()` nem em `down()`. **Lição**: ao desenhar um unique composto que substitui outro, a ordem das colunas importa não só para a query — a primeira coluna é a única que herda automaticamente a cobertura de FK de um índice composto; qualquer outra coluna com FK própria arrisca perder o índice que a sustentava se não sobrar mais nada a cobri-la.

Esta armadilha só se manifesta em MySQL — os testes automatizados correm em SQLite (`phpunit.xml`), que não tem este comportamento de absorção de índice, por isso **não** teriam apanhado o bug; a migration (nas duas versões) foi validada manualmente contra a base de dados de desenvolvimento (`docker compose exec php-fpm php artisan migrate` seguido de `migrate:rollback` e `migrate` outra vez, confirmando os índices finais via `SHOW INDEX` em cada passo).

Testado em `tests/Feature/NumeroFichaPorCentroTest.php` (dois centros da mesma paróquia com "F0001" simultâneo; segundo catequizando do mesmo centro avança para "F0002").

## 29. "Confirmar para o Próximo Ano Lectivo": bloqueio sem ano seguinte activo + helper text do catequista (2026-09-03, continuação)

Dois pedidos do utilizador depois de testar a secção 27:

1. **`catequista_id` no modal de confirmação ganhou o mesmo `->helperText()`** já usado no separador "Inscrição" e no `InscricaoResource`: "Quem atendeu/processou a ficha (não é necessariamente quem lecciona a turma)." — inconsistência de esquecimento, não decisão deliberada.
2. **A acção passou a verificar, antes de abrir o modal, se já existe um ano lectivo seguinte "em_curso" nesta paróquia** — sem isto, a acção calculava o "próximo ano lectivo" só por `data_inicio >` actual, **ignorando o estado**; se a paróquia ainda não tivesse aberto o ano lectivo seguinte (cenário normal: só há um `em_curso` por paróquia de cada vez, ver `AnoLetivoResource`), o `default()` do campo `ano_letivo_id` podia ficar vazio ou o utilizador podia escolher por engano o próprio ano lectivo em que o catequizando já está, só detectado tarde (ao submeter, pela regra de unicidade). Corrigido com o novo helper privado `CatequizandoResource::proximoAnoLetivoActivo(Inscricao $inscricaoActual): ?AnoLetivo`, que procura um `AnoLetivo` da mesma paróquia (`paroquia_id` explícito, mesmo motivo de defesa em profundidade da secção 28), `status = em_curso`, com `data_inicio` posterior ao da inscrição actual. O `default()` do campo `ano_letivo_id` no formulário passou a usar este helper em vez do cálculo antigo (só por data). **Nota**: a primeira versão desta correcção usava `->disabled()` para este caso — revista logo a seguir na secção 30, porque um botão desactivado passava despercebido.

Testado em `tests/Feature/ConfirmarProximoAnoLetivoTest.php` (2 casos novos: bloqueado quando o ano seguinte existe mas está `encerrado`; bloqueado quando não existe nenhum ano lectivo seguinte) — os testes existentes tiveram de passar o `anoSeguinte` da fixture para `em_curso` (antes era `encerrado`, o que já não chega para a acção ficar disponível).

## 30. Alerta em vez de botão desactivado quando falta o ano lectivo seguinte (2026-09-03, continuação)

Pedido do utilizador logo a seguir à secção 29: "em vez só de barrar, deve abrir um alerta a informar que o próximo ano lectivo ainda não entrou em vigor, contactar o administrador" — um botão simplesmente desactivado (só com tooltip ao passar o rato) é fácil de ignorar; a acção devia continuar clicável e explicar o motivo de forma visível.

**Distinção entre os dois bloqueios da acção `confirmarProximoAno`** (deixaram de ser tratados da mesma forma):
- **Sem inscrição no ano lectivo filtrado** (`! inscricaoNoFiltroActual()`) — continua `->disabled()` com `->tooltip()`: não há nada de accionável aqui, a linha simplesmente não se aplica à vista actual, não é uma situação que se resolva "esperando" ou "contactando alguém".
- **Sem ano lectivo seguinte `em_curso`** (`! proximoAnoLetivoActivo()`) — deixou de desactivar a acção. O botão fica sempre clicável; ao abrir, o modal mostra um alerta em vez do formulário: `->modalIcon('heroicon-o-exclamation-triangle')` + `->modalIconColor('warning')`, `->modalHeading('Ano Lectivo Seguinte Ainda Não Disponível')`, `->modalDescription('O próximo ano lectivo ainda não entrou em vigor nesta paróquia. Contacte o administrador ou o coordenador de catequese para criar e activar o novo ano lectivo antes de confirmar catequizandos.')`, sem campos (`->form()` devolve `[]`), sem botão de submeter e sem botão de cancelar (`->modalSubmitAction()` e `->modalCancelAction()`, ambos a devolver `false` neste caso — pedido do utilizador ao ver o resultado: um único botão "Fechar" desalinhado no canto ficava a mais para um alerta de uma linha; fecha-se pelo X do canto ou clicando fora, como qualquer modal). `->modalWidth('sm')` só neste caso (pedido do utilizador logo a seguir a ver o resultado — um alerta de uma linha não precisa da largura por omissão do Filament, `4xl`); o formulário completo de confirmação mantém a largura por omissão (`->modalWidth()` devolve `null` nesse ramo, que `getModalWidth()` interpreta como "usar o valor por omissão").

**Armadilha ao implementar `->modalSubmitAction()`**: a assinatura de Filament espera que o closure devolva o próprio objecto `StaticAction` (o botão, possivelmente alterado), `null` (mantém o botão por omissão — via `?? $action` internamente) ou `false` (esconde o botão) — **nunca `true`**. Devolver `true` rebenta com `TypeError: getModalSubmitAction(): Return value must be of type ?StaticAction, true returned`, só visível a testar (o `php -l`/lint normal não apanha isto, é um erro de tipo em tempo de execução dentro do Blade da modal). Corrigido trocando `fn (...): bool => ! $bloqueado` por `fn (...): ?bool => $bloqueado ? false : null`.

A acção `->action()` mantém, na mesma, uma verificação defensiva do mesmo bloqueio antes de gravar — nunca confiar só na UI (botão de submeter escondido) para impedir uma gravação indevida.

Testado em `tests/Feature/ConfirmarProximoAnoLetivoTest.php` (`test_alerta_em_vez_de_gravar_quando_nao_ha_ano_lectivo_seguinte_em_curso`/`test_alerta_em_vez_de_gravar_quando_nao_existe_nenhum_ano_lectivo_seguinte`, substituindo os dois testes equivalentes da secção 29): confirmam que a acção fica `assertTableActionEnabled()` (não desactivada) e que chamá-la por completo continua sem criar nenhuma inscrição nova.

Testado manualmente também (secção 27-30 no conjunto): criado o ano lectivo 2027/2028, encerrado o 2026/2027 e activado o novo, confirmada a Adelaide Nsimba Vunge para o ano seguinte (Nº Ficha reiniciou para "F0001" no novo ano/centro, `tipo=confirmacao`, `inscricao_anterior_id` ligado à ficha antiga) e inscrito um catequizando novo via "Inscrever" (registo duplo confirmado na lista). Sem surpresas face ao desenhado.

## 31. Coluna "Tipo de Inscrição" na lista de Catequizandos (2026-09-03, continuação)

Pedido do utilizador depois do teste manual da secção 30: mostrar também, na lista de Catequizandos, se a inscrição do ano filtrado é uma matrícula nova ou uma confirmação (progressão de ano) — até aqui só dava para ver isso abrindo "Dados de Catequese" ou a ficha completa.

Coluna nova `tipo_inscricao_actual` (mesma família das colunas "Ano de Catequese"/"Estado da Inscrição" — lê `self::inscricaoNoFiltroActual()`, não é atributo real), com badge colorido: `success` (verde) para `confirmacao`, `gray` para `nova`/sem inscrição.

**Rótulo "Matrícula" em vez de "Nova"** — pedido explícito do utilizador ("se é confirmação ou matrícula (nova)"), inicialmente feito só nesta coluna (ver secç. 31 original), com o `InscricaoResource` a manter "Nova" no seu próprio Tipo. **Corrigido logo a seguir** (mesmo dia): o utilizador reparou que a tabela "Inscrições" dentro de "Editar Catequizando" (`InscricoesRelationManager`) e a lista `InscricaoResource` continuavam a mostrar "Nova" — informação inconsistente para o mesmo facto, mostrada em três sítios diferentes. Em vez de repetir o `match` em cada Resource/RelationManager (a fonte exacta do problema — cada cópia podia divergir da outra, e divergiu), a lógica passou a viver **no próprio enum**: `App\Enums\TipoInscricao::label()` e `::color()`, mesmo padrão já usado por `EstadoInscricaoTurma::label()` (secç. 19). Todos os sítios que mostravam o tipo (`CatequizandoResource::table()`, `CatequizandoResource` modal "Dados de Catequese", `CatequizandoResource\RelationManagers\InscricoesRelationManager`, `InscricaoResource::form()`/`table()`/filtro) passaram a chamar `$tipo->label()`/`$tipo->color()` em vez de repetir o `match`. A única excepção deliberada: a opção "Confirmação" no `Select` de criação/edição de `InscricaoResource` mantém o sufixo informativo "(progressão de ano)" (`TipoInscricao::Confirmacao->label().' (progressão de ano)'`) — contexto útil só nesse formulário de preenchimento, não é uma divergência de dados.

## 32. Carga real da Catequese 2026/2027 (2026-10-09)

Desenho completo em `docs/modulos/catequese-importacao-2026-2027.md`. Dados reais da Paróquia São Carlos Lwanga (Centro-Sede) carregados a partir de `LISTA NOMINAL DE CATEQUESE 2026-2027.docx` + `BASE DE DADOS CATEQUESE - 2026-2027.xlsx`.

**Esquema novo** (migrations `2026_10_09_000001..4`): tabela global `catecismos` + `turmas.catecismo_id`; `turmas.nome`; valor `adultos` em `turmas.publico_alvo`; `catequizandos.data_nascimento` nullable + `data_nascimento_aproximada`; `anos_letivos.data_inicio/data_fim` nullable (decisões do utilizador: tudo opcional e editável).
**Seeders**: `CatequeseSeeder` passou a anos 0 Iniciação · 1º–4º Ano · 5 "1º Crisma" · 6 "2º Crisma" · 7 Perseverança, 4 sacramentos, 10 catecismos (os antigos "5º/6º Ano" foram renomeados). `ParoquiaRealSeeder` (fora do `DatabaseSeeder`) cria a paróquia e os centros Sede e São Miguel Arcanjo com dados genéricos a editar em produção.
**Comandos**: `catequese:preparar-importacao {docx} {xlsx}` (gera `storage/app/private/importacao-catequese/staging.json` + `problemas.csv`, git-ignorado — dados de menores) e `catequese:importar` (ensaio por omissão; `--confirmar` grava numa transacção; idempotente).
**Resultado**: 54 turmas, 96 catequistas (97 atribuições titular/auxiliar), 1 151 catequizandos/inscrições (F0001–F1151), 1 084 colocações; 67 inscritos sem turma; 21 sem data de nascimento. Regras: toda a data de nascimento é aproximada (só havia ano/idade); sexo em falta = M; sem origem DOCX → `nova`; "Situação 2025/2026" e observações do XLSX em `inscricoes.observacoes`; `publico_alvo` por catecismo (adulto) ou idade mediana (≤12 / 13–17 / ≥18), editável.
**Pendente de revisão humana** (ver `problemas.csv`): 77 sexos assumidos, 11 alunos em duas turmas (mantida a primeira), nomes só no DOCX, 1 emparelhamento por grafia próxima, 1 catequista fundido (Isamara/Isanara).
