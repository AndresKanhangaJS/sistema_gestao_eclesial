<?php

namespace App\Filament\Resources\FielResource\Pages;

use App\Filament\Resources\FielResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateFiel extends CreateRecord
{
    protected static string $resource = FielResource::class;

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Fiel registado';
    }
}
