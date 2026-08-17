<?php

namespace App\Filament\Imports;

use App\Models\Catequista;
use App\Models\Centro;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Importacao em massa de Catequistas (modulo Catequese), mesmo padrao do
 * FielImporter/CatequizandoImporter: o ficheiro so traz dados do proprio
 * catequista — nunca centro nem paroquia, sempre definidos aqui (centro
 * escolhido no modal de importacao para todas as linhas, como "Centro
 * Principal"; paroquia derivada desse centro). fiel_id/user_id nunca vem do
 * ficheiro — vinculos opcionais, feitos manualmente depois no formulario.
 *
 * O Importer corre dentro de um job em fila (sem Auth::user() disponivel),
 * por isso a paroquia e o centro sao sempre lidos a partir de $this->import
 * (persistido em BD) ou validados com withoutGlobalScopes(), nunca por uma
 * global scope dependente de sessao autenticada.
 */
class CatequistaImporter extends Importer
{
    protected static ?string $model = Catequista::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('nome_completo')
                ->label('Nome Completo')
                ->requiredMapping()
                ->example('Ana Paula Ferreira')
                ->rules(['required', 'string', 'max:150']),
            ImportColumn::make('data_nascimento')
                ->label('Data de Nascimento')
                ->helperText('Formato AAAA-MM-DD.')
                ->example('1990-11-02')
                ->ignoreBlankState()
                ->rules(['nullable', 'date']),
            ImportColumn::make('telefone')
                ->label('Telefone')
                ->example('923456789')
                ->ignoreBlankState()
                ->rules(['nullable', 'string', 'max:20']),
            ImportColumn::make('email')
                ->label('Email')
                ->example('ana.ferreira@email.com')
                ->ignoreBlankState()
                ->rules(['nullable', 'email', 'max:100']),
            ImportColumn::make('ativo')
                ->label('Estado')
                ->helperText('"ativo" ou "inativo", em branco fica "ativo".')
                ->example('ativo')
                ->ignoreBlankState()
                ->rules(['nullable', 'in:ativo,inativo'])
                // fillRecordUsing (nao castStateUsing): as rules() validam o
                // valor ja transformado por castStateUsing, por isso um
                // castStateUsing para booleano faz "inativo" falhar contra
                // in:ativo,inativo (false nao esta nessa lista). fillRecord()
                // recebe sempre o texto original, ja validado.
                ->fillRecordUsing(function (Catequista $record, ?string $state) {
                    if ($state !== null) {
                        $record->ativo = $state === 'ativo';
                    }
                }),
        ];
    }

    public static function getOptionsFormComponents(): array
    {
        return [
            // Quem esta preso a 1 centro (coordenador_catequese_centro) nem
            // escolhe: o campo fica desactivado, sempre com o proprio
            // centro. Só admin_geral/coordenador_catequese_paroquia
            // (paroquia inteira) escolhem livremente — mesmo padrao
            // GESTORES_CENTRO_LIVRE dos Resources.
            //
            // Bug corrigido: ->visible(false) fazia o valor desaparecer do
            // array $options que chega ao job da fila (getState() da
            // ImportAction nao inclui campos invisiveis, ao contrario de um
            // form normal de Resource) — todas as linhas falhavam com
            // "Centro invalido". ->disabled()+->dehydrated() mantem o campo
            // visivel (so bloqueado) e força a inclusao do valor.
            Select::make('centro_id')
                ->label('Centro Principal')
                ->helperText('Todos os catequistas importados ficam com este centro principal.')
                ->options(fn () => Centro::orderBy('nome')->pluck('nome', 'id'))
                ->required()
                ->default(fn () => Auth::user()?->centro_id)
                ->disabled(fn () => ! (Auth::user()?->hasRole(['admin_geral', 'coordenador_catequese_paroquia']) ?? false))
                ->dehydrated(),
        ];
    }

    public function resolveRecord(): ?Model
    {
        // A importacao serve so para registar catequistas novos — nunca
        // actualiza um existente (o ficheiro nao traz nenhum identificador).
        return new Catequista;
    }

    protected function beforeCreate(): void
    {
        $paroquiaId = $this->import->user->paroquia_id;
        $centroId = (int) ($this->options['centro_id'] ?? 0);

        $centroValido = Centro::withoutGlobalScopes()
            ->whereKey($centroId)
            ->where('paroquia_id', $paroquiaId)
            ->exists();

        if (! $centroValido) {
            throw new RowImportFailedException('Centro inválido para a paróquia de quem está a importar.');
        }

        $this->record->paroquia_id = $paroquiaId;
        $this->record->centro_id = $centroId;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'A importação de '.number_format($import->successful_rows).' '
            .($import->successful_rows === 1 ? 'catequista foi concluída' : 'catequistas foi concluída').'.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '
                .($failedRowsCount === 1 ? 'linha falhou' : 'linhas falharam').' e não foram importadas.';
        }

        return $body;
    }
}
