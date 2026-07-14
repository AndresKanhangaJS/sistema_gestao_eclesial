<?php

namespace App\Filament\Resources\MovimentoResource\Pages;

use App\Filament\Resources\MovimentoResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditMovimento extends EditRecord
{
    protected static string $resource = MovimentoResource::class;

    /**
     * O campo centro_id fica oculto para tesoureiro_centro — impede-se aqui,
     * no servidor, que consiga mover um movimento para outro centro
     * adulterando o formulario (o mesmo motivo do fix em CreateMovimento).
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = Auth::user();

        if ($user?->hasRole('tesoureiro_centro')) {
            $data['centro_id'] = $user->centro_id;
        }

        return $data;
    }

    /**
     * Movimento nunca e apagavel pela UI (CLAUDE.md: "nunca DELETE fisico —
     * usar estornos"). O MovimentoPolicy ja bloqueia delete/deleteAny para
     * todos os roles, mas o admin_geral contorna Policies via Gate::before
     * (Modulo 1) — sem esta lista vazia, ainda conseguiria apagar por aqui.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Movimento actualizado';
    }
}
