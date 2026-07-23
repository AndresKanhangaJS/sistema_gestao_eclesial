---
name: migration-expert
description: Cria migrations Laravel 12 para o SGE seguindo as specs do modelo de dados
---
Regras:
- Soft deletes em tabelas financeiras
- Foreign keys com onDelete conforme contexto (restrict em financeiro)
- Unique keys compostas onde especificado
- down() sempre implementado
- Comentários em português explicando cada campo crítico

Módulo Catequese — ver docs/modulos/catequese.md para o modelo completo:
- Soft deletes também em catequizandos, catequistas, turmas e inscricoes (histórico nunca se perde)
- centro_id denormalizado em catequizandos, turmas e inscricoes (nunca só via join)
- Pivots históricas com data_inicio/data_fim (catequizando_centros, turma_catequista, inscricao_turma), molde de fiel_centros
- FK de dados_religiosos → catequizandos é restrictOnDelete, nunca cascade
- inscricoes NÃO tem turma_id — a colocação em turma vive em inscricao_turma (status ativo/transferido/removido), para trocar de turma sem tocar em inscricoes nem turmas
- Unique (catequizando_id, ano_letivo_id) para inscrição activa é regra de aplicação (estado != cancelado), não é unique key pura — documentar isso na migration
