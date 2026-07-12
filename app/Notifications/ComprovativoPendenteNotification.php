<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica o tesoureiro_paroquial de movimentos com comprovativo pendente
 * ha mais de 48h (spec 03-financeiro.md).
 */
class ComprovativoPendenteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Collection $movimentos)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('SGE — Comprovativos pendentes há mais de 48h')
            ->greeting('Olá, ' . $notifiable->name)
            ->line('Os seguintes movimentos estão pendentes de comprovativo há mais de 48 horas:');

        foreach ($this->movimentos as $movimento) {
            $mail->line(sprintf(
                '- Movimento #%d, %s, valor Kz %s, lançado em %s',
                $movimento->id,
                $movimento->tipo->value,
                number_format((float) $movimento->valor, 2, ',', '.'),
                $movimento->data_movimento->format('d/m/Y')
            ));
        }

        return $mail->line('Por favor regularize o comprovativo o mais brevemente possível.');
    }
}
