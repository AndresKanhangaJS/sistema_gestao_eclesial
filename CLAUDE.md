# SGE — Sistema de Gestão Eclesial

## Stack
Laravel 12 | Filament v3 | Livewire | Tailwind | MySQL 8.0 | Redis | Docker

## Regras ABSOLUTAS (nunca violar)
1. Soft Deletes obrigatórios em TODAS as tabelas financeiras
2. Nunca usar DELETE físico em tabelas financeiras — usar estornos
3. Todos os models financeiros têm Global Scope por paroquia_id
4. Migrations sempre com down() completo e funcional
5. Respostas JSON: { data, message, status }
6. Nunca commitar credenciais — sempre .env
7. Commits em português: feat: | fix: | refactor: | docs:

## Perfis RBAC (hierarquia)
- admin_geral → acesso total ao sistema; CRUD de Paróquias e regista qualquer papel, incl. administrador_paroquial
- administrador_paroquial → faz tudo o que está ligado à sua própria paróquia: financeiro completo + conciliação bancária (paridade com tesoureiro_paroquial), regista utilizadores (tesoureiro_paroquial/tesoureiro_centro) e vincula-os aos centros da paróquia — nunca atribui admin_geral/consultor/outro administrador_paroquial
- tesoureiro_paroquial → financeiro completo + conciliação bancária, sem gerir utilizadores
- tesoureiro_centro → apenas o seu centro, sem conciliação
- consultor → read-only global

## Convenções
- Models: singular PascalCase (Movimento, Paroquia, Fiel)
- Controllers: Resource Controllers
- Policies: uma por model com RBAC aplicado
- Testes PHPUnit para toda lógica financeira
- Idioma do código: português (variáveis, comentários, commits)

## Ordem de Desenvolvimento dos Módulos
1. Autenticação + RBAC (Filament Shield)
2. Paróquias e Centros
3. Fiéis + pivot fiel_centros
4. Movimentos Financeiros + Conciliação
5. Matriz de Dízimos (Assiduidade)
6. Despesas + Categorias
7. Relatórios + Exportação PDF/Excel
8. Notificações (Email + SMS + WhatsApp)

## Constraint Crítica
Unique Key em movimentos: (fiel_id, ano_competencia, mes_competencia) WHERE tipo = 'dizimo'

## Ambiente
- URL local: http://localhost:8080
- Credenciais: ver .env.example
