<?php

namespace App\Filament\Imports;

use App\Models\Centro;
use App\Models\Fiel;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;

/**
 * Importacao em massa de Fieis (Modulo 3). O ficheiro so traz dados do
 * proprio fiel — nunca centro nem paroquia, que sao sempre definidos aqui:
 * o centro escolhido no modal de importacao (options()) para todas as
 * linhas, e a paroquia do utilizador que importa. E a mesma regra "nunca
 * confiar em dados externos para paroquia_id" ja aplicada ao Movimento.
 *
 * O Importer corre dentro de um job em fila (sem Auth::user() disponivel),
 * por isso a paroquia e o centro sao sempre lidos a partir de $this->import
 * (persistido em BD) ou validados com withoutGlobalScopes(), nunca por uma
 * global scope dependente de sessao autenticada.
 */
class FielImporter extends Importer
{
    protected static ?string $model = Fiel::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('nome')
                ->label('Nome')
                ->requiredMapping()
                ->example('Maria João dos Santos')
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('telefone')
                ->label('Telefone')
                ->example('923456789')
                ->ignoreBlankState()
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('email')
                ->label('Email')
                ->example('maria.santos@email.com')
                ->ignoreBlankState()
                ->rules(['nullable', 'email', 'max:255']),
            ImportColumn::make('data_nascimento')
                ->label('Data de Nascimento')
                ->helperText('Formato AAAA-MM-DD.')
                ->example('1985-03-20')
                ->ignoreBlankState()
                ->rules(['nullable', 'date']),
            ImportColumn::make('status')
                ->label('Estado')
                ->helperText('"ativo" ou "inativo" — em branco fica "ativo".')
                ->example('ativo')
                ->ignoreBlankState()
                ->rules(['nullable', 'in:ativo,inativo']),
        ];
    }

    public static function getOptionsFormComponents(): array
    {
        return [
            Select::make('centro_id')
                ->label('Centro')
                ->helperText('Todos os fiéis importados ficam vinculados a este centro.')
                ->options(fn () => Centro::orderBy('nome')->pluck('nome', 'id'))
                ->required(),
        ];
    }

    public function resolveRecord(): ?Model
    {
        // A importacao serve so para registar fieis novos — nunca actualiza
        // um existente (o ficheiro nao traz nenhum identificador de fiel).
        return new Fiel;
    }

    protected function beforeCreate(): void
    {
        $paroquiaId = $this->import->user->paroquia_id;
        $centroId = (int) ($this->options['centro_id'] ?? 0);

        // Sem withoutGlobalScopes() a ParoquiaScope nao faria nada aqui: o
        // job corre em fila, sem utilizador autenticado na sessao, por isso
        // a comparacao tem de ser feita explicitamente contra o dono real
        // da importacao (persistido em imports.user_id).
        $centroValido = Centro::withoutGlobalScopes()
            ->whereKey($centroId)
            ->where('paroquia_id', $paroquiaId)
            ->exists();

        if (! $centroValido) {
            throw new RowImportFailedException('Centro inválido para a paróquia de quem está a importar.');
        }

        $this->record->paroquia_id = $paroquiaId;
    }

    protected function afterCreate(): void
    {
        $this->record->centros()->attach($this->options['centro_id'], [
            'data_inicio' => now(),
            'principal' => true,
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'A importação de '.number_format($import->successful_rows).' '
            .($import->successful_rows === 1 ? 'fiel foi concluída' : 'fiéis foi concluída').'.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '
                .($failedRowsCount === 1 ? 'linha falhou' : 'linhas falharam').' e não foram importadas.';
        }

        return $body;
    }
}
