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
