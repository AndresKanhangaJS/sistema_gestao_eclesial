<?php

namespace App\Filament\Resources\ParoquiaResource\Pages;

use App\Filament\Resources\ParoquiaResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateParoquia extends CreateRecord
{
    protected static string $resource = ParoquiaResource::class;

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Paróquia registada';
    }
}
