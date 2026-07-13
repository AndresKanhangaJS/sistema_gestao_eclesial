<?php

namespace App\Filament\Resources\BancoResource\Pages;

use App\Filament\Resources\BancoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBanco extends CreateRecord
{
    protected static string $resource = BancoResource::class;

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Banco registado';
    }
}
