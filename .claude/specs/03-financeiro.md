# 03 — Financeiro

- Lançamento de receitas e despesas com comprovativo obrigatório se metodo_pagamento.exige_comprovativo = true
- Fluxo conciliação: pendente → aprovado | rejeitado (motivo obrigatório)
- Storage: S3-compatible, UUID como nome do ficheiro, URL assinada 60min
- Notificar tesoureiro_paroquial se comprovativo pendente > 48h (job agendado)
- Despesas acima de valor configurável requerem aprovação do tesoureiro_paroquial
