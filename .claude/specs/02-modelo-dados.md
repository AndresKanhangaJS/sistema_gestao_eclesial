# 02 — Modelo de Dados

Tabelas por ordem de criação:

1. paroquias: id, nome(unique), diocese, morada, responsavel, email_contato, telefone, status, timestamps
2. centros: id, paroquia_id(FK), nome, localizacao, responsavel_local, status
3. metodos_pagamento: id, nome, exige_comprovativo(bool), status
4. bancos: id, paroquia_id(FK), nome_banco, sigla, numero_conta, iban, status
5. fieis: id, paroquia_id(FK), nome, codigo_dizimista(unique), telefone, email, data_nascimento, status, soft_delete
6. fiel_centros: id, fiel_id(FK), centro_id(FK), data_inicio, data_fim(null), principal(bool), motivo_transferencia — Unique(fiel_id, centro_id, data_inicio)
7. categorias_despesa: id, paroquia_id(FK), nome, descricao, status
8. movimentos: id, paroquia_id(FK), centro_id(FK), usuario_id(FK), fiel_id(FK nullable), metodo_pagamento_id(FK), banco_id(FK nullable), tipo(enum: dizimo|ofertorio|campanha|despesa_centro), categoria_despesa_id(FK nullable), valor(decimal 10,2), ano_competencia(int nullable), mes_competencia(int 1-12 nullable), data_movimento, comprovativo_path, numero_referencia_bancaria(unique nullable), status_conciliacao(enum: pendente|aprovado|rejeitado default pendente), motivo_rejeicao(text nullable), soft_delete
   UNIQUE KEY: (fiel_id, ano_competencia, mes_competencia) WHERE tipo = 'dizimo'
