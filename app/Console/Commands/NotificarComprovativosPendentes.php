<?php

namespace App\Console\Commands;

use App\Models\Movimento;
use App\Models\User;
use App\Notifications\ComprovativoPendenteNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

#[Signature('sge:notificar-comprovativos-pendentes')]
#[Description('Notifica os tesoureiros paroquiais de comprovativos pendentes ha mais de 48h')]
class NotificarComprovativosPendentes extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $movimentosPendentes = Movimento::query()
            ->whereNull('comprovativo_path')
            ->where('status_conciliacao', 'pendente')
            ->where('created_at', '<', now()->subHours(48))
            ->whereHas('metodoPagamento', fn ($q) => $q->where('exige_comprovativo', true))
            ->get()
            ->groupBy('paroquia_id');

        foreach ($movimentosPendentes as $paroquiaId => $movimentos) {
            $tesoureiros = User::where('paroquia_id', $paroquiaId)
                ->role(['administrador_paroquial', 'tesoureiro_paroquial'])
                ->get();

            if ($tesoureiros->isEmpty()) {
                continue;
            }

            Notification::send($tesoureiros, new ComprovativoPendenteNotification($movimentos));

            $this->info("Paroquia {$paroquiaId}: {$movimentos->count()} movimento(s) notificado(s) a {$tesoureiros->count()} tesoureiro(s).");
        }
    }
}
