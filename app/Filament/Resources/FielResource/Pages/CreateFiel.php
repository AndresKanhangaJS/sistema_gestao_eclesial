<?php

namespace App\Filament\Resources\FielResource\Pages;

use App\Filament\Resources\FielResource;
use App\Models\Fiel;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;

class CreateFiel extends CreateRecord
{
    protected static string $resource = FielResource::class;

    /**
     * codigo_dizimista nao vem no form (Fiel::creating() gera-o sozinho) —
     * numa colisao rara por concorrencia (dois registos em simultaneo a
     * calcular o mesmo proximo numero), a unique key da BD rejeita o
     * segundo; tenta de novo, o que gera um codigo seguinte fresco.
     */
    protected function handleRecordCreation(array $data): Model
    {
        for ($tentativa = 1; $tentativa <= 5; $tentativa++) {
            try {
                return Fiel::create($data);
            } catch (UniqueConstraintViolationException $e) {
                if ($tentativa === 5) {
                    throw $e;
                }
            }
        }
    }

    /**
     * O form do Fiel nao tem nenhum campo de centro (o vinculo vive so na
     * pivot fiel_centros, gerida pela CentrosRelationManager) — sem isto, um
     * coordenador_centro/secretario_centro criaria fieis sem nenhum centro
     * vinculado, apesar de estarem presos a um so centro.
     */
    protected function afterCreate(): void
    {
        $user = Auth::user();

        if ($user?->hasRole(['coordenador_centro', 'secretario_centro'])) {
            $this->record->centros()->attach($user->centro_id, [
                'data_inicio' => now(),
                'principal' => true,
            ]);
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Fiel registado';
    }
}
