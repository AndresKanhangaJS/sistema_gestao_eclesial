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

Módulo Catequese — ver docs/modulos/catequese.md para o modelo completo:
- getEloquentQuery() reforçado por centro_id para coordenador_catequese_centro, secretario_catequese e tesoureiro_catequese (mesmo padrão de FielResource/CentroResource)
- inscricoes NÃO tem turma_id directo — a turma actual vive em inscricao_turma. Troca de turma nunca edita inscricoes nem turmas: fecha a linha activa em inscricao_turma (status=transferido/removido) e cria outra (status=ativo), sempre como ação dedicada no RelationManager, nunca edição livre de campo
- Campo centro_id em inscricoes nunca editável no form — sempre herdado do utilizador autenticado (Observer)
- RelationManagers com $inverseRelationship explícito nos pivots (turma_sacramento, turma_catequista, catequizando_centros, inscricao_turma)
