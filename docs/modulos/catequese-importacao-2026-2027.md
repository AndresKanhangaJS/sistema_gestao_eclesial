# Carga inicial real — Catequese 2026/2027 (Paróquia São Carlos Lwanga)

> Estado: **implementado e carregado em 2026-10-09** (ver secção 32 de catequese.md).
> Origem dos dados: `LISTA NOMINAL DE CATEQUESE 2026-2027.docx` (turmas, catequistas, auxiliares, alunos) e `BASE DE DADOS CATEQUESE - 2026-2027.xlsx` (folha "INSCRIÇÃO E CONFIRMAÇÃO 2026-27" = lista mestra). As folhas de 2025/26 (PAUTA, ANONC, Folha2) ficam **fora**, por decisão do utilizador.

## 1. O que existe nos ficheiros (medido)

| Item | Valor |
|---|---|
| Turmas no DOCX | **54** (todas "CENTRO-SEDE") |
| Colocações aluno→turma no DOCX | 1081 (1075 nomes distintos; 6 nomes aparecem em 2 turmas) |
| Linhas com nome na lista mestra XLSX | 1083 (756 "Confirmação", 327 "Inscrição"); 280 linhas numeradas vazias a ignorar |
| Nomes em ambos os ficheiros (iguais após normalizar acentos/espaços) | 1015 |
| Só no DOCX | 60 (4 são variações de grafia; **56 só têm nome+idade+género**) |
| Só no XLSX | 68 (66 coincidem com a folha "NOMES POR INCLUIR NA TR" → **sem turma ainda**) |
| Total estimado de catequizandos a criar | ≈ 1 140 |
| Catequistas titulares / auxiliares | 54 / 43 (sem sobreposição exacta; há grafias duplicadas, ex. *Suzentina Choeta*/*Xoeta*, *Isamara*/*Isanara*) |
| Turmas de "Catecismo de Adulto" | 11 (idades 13–73) |

**Nada nos ficheiros pertence ao centro São Miguel Arcanjo** — todos os dados são do Centro-Sede. São Miguel Arcanjo é criado vazio.

## 2. Lacunas entre os dados reais e o modelo actual

| # | Problema | Proposta |
|---|---|---|
| L1 | **Catecismo** (Deus Chama-nos, Deus Ama-nos, Creio em Jesus Salvador, Jesus é a Vida/Eleitos, A Minha Primeira Comunhão, Discípulo de Cristo, Apóstolo de Cristo, Jesus 1/2, Minha História Divina, Catecismo de Adulto) não existe no modelo. Não se deduz do ano: o "1º B/C" tem 3 catecismos diferentes. | Tabela global nova `catecismos` (`ordem`, `nome`, `status`, SoftDeletes) + `turmas.catecismo_id` nullable. Entra também em `descricaoCurta()` e no PDF da turma. |
| L2 | **Anos catequéticos**: o seeder tem "1º–6º Ano" genéricos; os dados têm Iniciação, 1º–4º, 1º Crisma, 2º Crisma, Perseverança. | Reescrever `CatequeseSeeder` por `ordem`: 0 Iniciação · 1º–4º Ano · 5 "1º Crisma" · 6 "2º Crisma" · 7 Perseverança. Sacramentos já existem (Baptismo, Comunhão, Crisma, Perseverança). |
| L3 | **Turma sem nome/número** (o DOCX identifica-as por nº e há números repetidos: 4, 12, 15, 17, 28, 39, 40). | Coluna nova `turmas.nome` (ex. "Turma 04-A"). Número original preservado no nome; duplicados recebem sufixo A/B. |
| L4 | `publico_alvo` não tem "adultos". | Acrescentar valor `adultos` ao enum (migration reversível). Regra de preenchimento: catecismo = *Adulto* → `adultos`; senão pela idade mediana da turma (≤12 crianças, 13–17 pré-adolescentes… ver pergunta Q4). |
| L5 | `catequizandos.data_nascimento` é NOT NULL, mas o XLSX só dá o **ano** de nascimento e o DOCX só a **idade**. | Coluna nova `data_nascimento_aproximada` (bool). Ano conhecido → `AAAA-07-01`+flag. Só idade → `2026−idade`+flag. 50 colocados só têm idade; **16 não têm nem ano válido nem idade** (ver Q3). |
| L6 | `paroquias` exige `morada`, `responsavel`, `email_contato`, `telefone`; `centros` pede `localizacao`/`responsavel_local`. Os ficheiros só dão nome e "Arquidiocese de Luanda". | Preciso dos valores reais ou aceito marcadores a corrigir depois (Q1). |
| L7 | Ano lectivo 2026/2027 tem de existir (`em_curso`). | Criado no passo de carga; datas a confirmar (Q5). |

Tudo o resto encaixa no modelo existente: `catequizandos` → `inscricoes` (tipo `nova`/`confirmacao`) → `inscricao_turma` (colocação) e `turma_catequista` (titular/auxiliar), `turma_sacramento`, `catequizando_centros`.

## 3. Mapeamento

**Turma** (uma por tabela do DOCX): `ano_catequetico` + `sacramentos` pelo título da secção — "1º BAPTISMO E COMUNHÃO" → 1º Ano + {Baptismo, Comunhão}; "1º COMUNHÃO" → 1º Ano + {Comunhão}; "1º/2º CRISMA" → ano 5/6 + {Crisma}; "INICIAÇÃO" → ano 0 + {} (conjunto vazio, o filtro de "conjunto exacto" já suporta); "PERSEVERANÇA" → ano 7 + {Perseverança}. Atenção: no DOCX as turmas de Iniciação (46, 47) estão **dentro** da secção "2º CRISMA" — o campo "Ano: Iniciação" prevalece sobre o título. Horário "10H00 - 11H00" → `hora_inicio/fim`; `periodo` calculado (<12h manhã, 12–18h tarde); `tipo=normal`; `vagas_*` vazias.

**Catequizando**: nome (lista mestra; DOCX para os 56 só-DOCX), `sexo` (DOCX; para os 68 sem turma → ver Q6), `data_nascimento` (L5), `telefone` (XLSX; `#N/A`→vazio), `status=ativo`, `centro_id=Sede`, linha em `catequizando_centros`. Sem BI, pais, morada (não existem nos ficheiros).

**Inscrição 2026/2027**: `tipo` = Serviço (*Inscrição*→`nova`, *Confirmação*→`confirmacao`; os só-DOCX → `confirmacao`? ver Q6); `estado=inscrito` para todos; `ano_catequetico_id`+`sacramentos` copiados da turma; `numero_ficha` automático (F0001… por centro/ano, no hook `creating`); `inscricao_anterior_id` fica **nulo** (o ano 2025/26 não é carregado). "Situação 2025/2026" (Apto, N/apto, Desistente, Baptismo/Comunhão…) e "Observação" (Falta Foto, Falta Cópia do Cartão…) vão para `observacoes` como texto. **Importante:** 52 constam como "DESISTENTE" em 2025/26 mas 51 estão colocados em turmas de 2026/27 → regressaram; **não** mapear para `estado=desistente`.
`catequista_id` da inscrição (quem atendeu a ficha) **não** é preenchido: a coluna "Catequista" do XLSX é o catequista da turma, não quem processou a ficha — essa relação vive em `turma_catequista`.

**Colocação**: `inscricao_turma` `ativo`, `data_inicio` = início do ano lectivo, para os ≈1 075 que constam do DOCX. Os 68 do XLSX sem turma ficam **inscritos mas sem colocação** (lista "por colocar", já visível na UI). Os 6 nomes repetidos em 2 turmas: uma só colocação; vai para o relatório de problemas para decidir qual turma vale.

**Catequistas** (≈ 95 pessoas): `nome_completo`, `centro_id=Sede`, `ativo`. `turma_catequista` com `papel` titular/auxiliar e `data_inicio` = início do ano. Sem telefone/data de nascimento (as notas soltas da folha "DIVERSOS" não são fiáveis para carga automática). Casos especiais: "Delfina Nzau (Ivete Marques)" → Delfina Nzau, com nota de substituta; "Ir. Rita Ponto" mantém o título.

## 4. Como implementar (em duas fases, com revisão humana no meio)

Não usar os `Importer` do Filament: servem 1 folha → 1 entidade; isto é uma carga relacional multi-ficheiro, única, que precisa de reconciliação de nomes e de um relatório antes de gravar.

1. **`php artisan catequese:preparar-importacao`** — lê o DOCX (ZipArchive+DOM, sem dependência nova) e o XLSX (PhpSpreadsheet, já instalado via maatwebsite/excel) e escreve ficheiros de *staging* em `storage/app/importacao-catequese/` (`turmas.csv`, `catequistas.csv`, `catequizandos.csv`, `colocacoes.csv`, **`problemas.csv`**). `problemas.csv` lista: pares de nomes com grafia próxima a confirmar (catequistas e alunos), nomes repetidos, datas de nascimento em falta/incoerentes, nomes sem turma, catequistas do XLSX que não batem com o DOCX (64 no XLSX vs 54 no DOCX, mesmas pessoas com grafias diferentes). O utilizador corrige os CSV no Excel; as correcções são a verdade, nunca o fuzzy automático.
2. **`php artisan catequese:importar --dry-run`** (por omissão) → imprime contagens e o que seria criado; **`--confirmar`** grava, numa única transacção, por esta ordem: Paróquia → Centros → Ano Lectivo → anos/sacramentos/catecismos → turmas (+pivôs) → catequistas → catequizandos (+`catequizando_centros`) → inscrições (+sacramentos) → `inscricao_turma` → `turma_catequista`. Idempotente (reexecutar não duplica: chave = nome normalizado + centro + ano lectivo).
3. **Seeders de dados reais** separados dos de demonstração: `ParoquiaRealSeeder` (Paróquia + 2 centros), fora do `DatabaseSeeder` de demo, para não misturar com "Paróquia de Teste".

Regras do CLAUDE.md cumpridas: `paroquia_id`/`centro_id` explícitos (CLI não tem utilizador autenticado → `withoutGlobalScopes()` onde preciso, como o `CatequizandoImporter`), soft deletes, migrations com `down()` completo, nada de DELETE físico, commits pequenos em português.

**Privacidade**: os CSV de staging contêm dados pessoais de menores. `storage/app/importacao-catequese/` entra no `.gitignore`; os ficheiros originais **não** são copiados para o repositório. Os testes usam fixtures sintéticas minúsculas.

## 5. Ordem de trabalho e agentes

| Passo | Conteúdo | Agente |
|---|---|---|
| 1 | Migrations L1/L3/L4/L5 (`catecismos`, `turmas.catecismo_id`, `turmas.nome`, enum `adultos`, `data_nascimento_aproximada`) + models | `migration-expert` |
| 2 | Seeders: anos catequéticos/catecismos/sacramentos reais; `ParoquiaRealSeeder` | `migration-expert` |
| 3 | Resource da Turma: campos `nome` e `catecismo`; `descricaoCurta()`; PDF; flag de data aproximada no form do Catequizando | `filament-builder` |
| 4 | Comandos `preparar-importacao` / `importar` | eu (código de domínio) |
| 5 | Testes: parser (fixtures), idempotência, isolamento por centro, ficha por centro, rollback transaccional | `test-writer` |
| 6 | Correr `preparar`, **rever `problemas.csv` consigo**, `--dry-run`, só depois `--confirmar` | consigo |
| 7 | Actualizar `docs/modulos/catequese.md` (nova secção 32) e a nota "por começar" do `CLAUDE.md` | eu |

## 6. Decisões em aberto

Ver perguntas Q1–Q6 na conversa; as respostas serão registadas aqui antes de se escrever código.
