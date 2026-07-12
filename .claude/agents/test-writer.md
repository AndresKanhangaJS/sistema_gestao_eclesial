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
