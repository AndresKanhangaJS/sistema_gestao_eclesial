# Módulo 8 — Notificações (Email + SMS + WhatsApp)

> Estado: **PARCIAL**. Só o canal Email está implementado, e só para um único evento de negócio. Não existe qualquer código de SMS ou WhatsApp no repositório (sem canal, sem config, sem dependência no `composer.json`) — este documento descreve apenas o que existe hoje; não inventa o que falta.

## 1. Visão geral

O único fluxo de notificação implementado é o aviso a tesoureiros de que há movimentos com comprovativo pendente há mais de 48 horas, enviado por email, disparado por um comando Artisan agendado de hora a hora. Não existem tabelas próprias — reutiliza `movimentos` e `users`.

## 2. O que existe hoje

### Comando: `sge:notificar-comprovativos-pendentes`
`App\Console\Commands\NotificarComprovativosPendentes`:
- Query: `Movimento::whereNull('comprovativo_path')->where('status_conciliacao', 'pendente')->where('created_at', '<', now()->subHours(48))->whereHas('metodoPagamento', fn ($q) => $q->where('exige_comprovativo', true))`, agrupado por `paroquia_id`.
- Para cada paróquia com movimentos pendentes, busca `User::where('paroquia_id', $paroquiaId)->role(['administrador_paroquial', 'tesoureiro_paroquial'])->get()` e envia a notificação a todos.
- Se não houver nenhum utilizador com esses papéis na paróquia, ignora silenciosamente esse grupo (`continue`).

### Agendamento
`routes/console.php`: `Schedule::command('sge:notificar-comprovativos-pendentes')->hourly()`.

### Notificação: `ComprovativoPendenteNotification`
`App\Notifications\ComprovativoPendenteNotification` (`ShouldQueue` — corre em fila, `QUEUE_CONNECTION=redis` no `.env.example`):
- `via()`: só `['mail']` — nenhum outro canal implementado (nem `database`, para aparecer no sino de notificações do Filament).
- `toMail()`: lista cada movimento pendente (número, tipo, valor formatado em Kz, data de lançamento) numa `MailMessage` simples.
- `MAIL_MAILER=log` no `.env.example` — em desenvolvimento, os emails só são escritos no log, não enviados de facto.

## 3. Tabelas de base de dados

Nenhuma tabela própria criada por este módulo. Não existe tabela `notifications` do Laravel (a que o trait `Illuminate\Notifications\Notifiable` usaria para o canal `database`) nas migrations — confirmando que só o canal `mail` está em uso.

## 4. RBAC

Não há Policy nem Resource Filament — é só um comando de consola. Os destinatários são implicitamente `administrador_paroquial`/`tesoureiro_paroquial` de cada paróquia (hardcoded no comando, não configurável).

## 5. Filament

Nenhuma página, resource ou widget Filament associado a este módulo.

## 6. O que falta (não implementado)

- **Canal SMS**: nenhuma dependência (`twilio`, `nexmo`/`vonage`, ou gateway local) no `composer.json`; nenhuma entrada em `config/services.php`.
- **Canal WhatsApp**: idem — nenhuma integração (ex. API do WhatsApp Business) encontrada no código.
- **Qualquer outro evento de notificação** além de "comprovativo pendente há 48h": não há notificações para aprovação/rejeição de movimento, novo utilizador registado, matrícula de catequese, etc.
- **Canal `database`** (sino de notificações no painel Filament): não implementado — as notificações só chegam por email.

## 7. Regras de negócio não óbvias

- O limiar de 48 horas está fixo no código (`now()->subHours(48)`), não é configurável via `.env`/`config`.
- Só movimentos cujo **método de pagamento exige comprovativo** (`metodos_pagamento.exige_comprovativo = true`) entram nesta verificação — um movimento em numerário (que normalmente não exige comprovativo) nunca gera este alerta, mesmo sem `comprovativo_path`.
- O agrupamento é por `paroquia_id` do movimento, não por centro — todos os `administrador_paroquial`/`tesoureiro_paroquial` da paróquia recebem o mesmo email, mesmo que os movimentos pendentes sejam de centros diferentes dentro dela.
