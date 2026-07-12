# 01 — Autenticação

- Filament Shield para RBAC
- 4 roles: admin_geral, tesoureiro_paroquial, tesoureiro_centro, consultor
- Middleware EnsureParoquiaScope em rotas protegidas
- Multi-tenant: ao login detectar paroquia_id e aplicar scope global
