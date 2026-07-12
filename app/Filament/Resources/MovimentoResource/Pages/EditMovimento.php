<?php

namespace App\Filament\Resources\MovimentoResource\Pages;

use App\Filament\Resources\MovimentoResource;
use Filament\Resources\Pages\EditRecord;

class EditMovimento extends EditRecord
{
    protected static string $resource = MovimentoResource::class;

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
