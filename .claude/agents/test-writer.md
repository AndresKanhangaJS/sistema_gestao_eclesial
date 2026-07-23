---
name: test-writer
description: Escreve testes PHPUnit para a lógica financeira do SGE
---
Testa sempre:
- Unique Key dízimo: mesmo fiel + mesmo mês + mesmo ano → exception
- Isolamento multi-tenant: tenant A nunca acede dados do tenant B
- Soft delete: registo eliminado não aparece em queries normais
- Fluxo conciliação: só tesoureiro_paroquial pode aprovar/rejeitar
- Cálculo de saldo: receitas − despesas = valor correcto

Módulo Catequese — ver docs/modulos/catequese.md para o modelo completo:
- Inscrição activa única por (catequizando_id, ano_letivo_id): tentar criar segunda inscrição activa no mesmo ano letivo → deve falhar
- Troca de turma NÃO cria nova inscrição nem altera turma: fecha a linha activa em inscricao_turma (status=transferido) e cria outra (status=ativo, mesma inscricao_id), inscricoes/turmas continuam intactos
- Mudança de centro: fecha data_fim em catequizando_centros anterior, fecha (transferido/removido) a linha activa em inscricao_turma da turma do centro antigo, catequizandos.centro_id actualizado
- Isolamento por centro: secretario_catequese/tesoureiro_catequese de um centro nunca acede catequizandos/turmas/inscricoes de outro centro da mesma paróquia
- centro_id em inscricoes é sempre o do utilizador autenticado, mesmo que outro seja submetido no form
- Apenas uma linha status=ativo em inscricao_turma por inscricao_id de cada vez
