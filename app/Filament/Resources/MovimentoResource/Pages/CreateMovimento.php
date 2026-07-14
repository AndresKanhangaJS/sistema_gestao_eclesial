<?php

namespace App\Filament\Resources\MovimentoResource\Pages;

use App\Filament\Resources\MovimentoResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateMovimento extends CreateRecord
{
    protected static string $resource = MovimentoResource::class;

    /**
     * O campo centro_id fica oculto no formulario para tesoureiro_centro
     * (nao deve escolher centro) e o seu valor de dehydrate nao e fiavel.
     * Forca-se aqui, no servidor, a mesma fonte de verdade usada no
     * lancamento em lote da Matriz de Dizimos: o centro do utilizador
     * autenticado — de onde o MovimentoObserver deriva o paroquia_id.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();

        if ($user?->hasRole('tesoureiro_centro')) {
            $data['centro_id'] = $user->centro_id;
        }

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Movimento registado';
    }
}
