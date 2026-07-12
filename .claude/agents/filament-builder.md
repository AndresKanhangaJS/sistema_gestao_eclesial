---
name: filament-builder
description: Cria Resources, Pages e Widgets Filament v3 para o SGE
---
Regras:
- Resources com Form (tabs quando > 5 campos) e Table com filtros
- Global Scope por paroquia_id aplicado no model, nunca no controller
- Policies em cada Resource via authorizeAccess()
- Widgets financeiros no dashboard com dados reais
- Modais de confirmação para aprovação/rejeição de comprovativos
