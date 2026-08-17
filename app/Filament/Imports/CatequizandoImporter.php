<?php

namespace App\Filament\Imports;

use App\Models\Catequizando;
use App\Models\Centro;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Importacao em massa de Catequizandos (modulo Catequese), mesmo padrao do
 * FielImporter: o ficheiro so traz dados do proprio catequizando — nunca
 * centro nem paroquia, sempre definidos aqui (centro escolhido no modal de
 * importacao para todas as linhas, paroquia derivada desse centro).
 * fiel_id nunca vem do ficheiro — vinculo opcional, feito manualmente
 * depois no formulario se for preciso.
 *
 * O Importer corre dentro de um job em fila (sem Auth::user() disponivel),
 * por isso a paroquia e o centro sao sempre lidos a partir de $this->import
 * (persistido em BD) ou validados com withoutGlobalScopes(), nunca por uma
 * global scope dependente de sessao autenticada.
 */
class CatequizandoImporter extends Importer
{
    protected static ?string $model = Catequizando::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('nome_completo')
                ->label('Nome Completo')
                ->requiredMapping()
                ->example('João Manuel dos Santos')
                ->rules(['required', 'string', 'max:200']),
            ImportColumn::make('data_nascimento')
                ->label('Data de Nascimento')
                ->requiredMapping()
                ->helperText('Formato AAAA-MM-DD.')
                ->example('2014-06-15')
                ->rules(['required', 'date']),
            ImportColumn::make('sexo')
                ->label('Sexo')
                ->requiredMapping()
                ->helperText('"M" ou "F".')
                ->example('M')
                ->rules(['required', 'in:M,F']),
            ImportColumn::make('nome_pai')
                ->label('Nome do Pai')
                ->example('Manuel dos Santos')
                ->ignoreBlankState()
                ->rules(['nullable', 'string', 'max:150']),
            ImportColumn::make('nome_mae')
                ->label('Nome da Mãe')
                ->example('Maria dos Santos')
                ->ignoreBlankState()
                ->rules(['nullable', 'string', 'max:150']),
            ImportColumn::make('numero_identificacao')
                ->label('Nº de Identificação (BI)')
                ->example('003456789LA042')
                ->ignoreBlankState()
                ->rules(['nullable', 'string', 'max:30']),
            ImportColumn::make('telefone')
                ->label('Telefone')
                ->example('923456789')
                ->ignoreBlankState()
                ->rules(['nullable', 'string', 'max:20']),
            ImportColumn::make('email')
                ->label('Email')
                ->example('joao.santos@email.com')
                ->ignoreBlankState()
                ->rules(['nullable', 'email', 'max:100']),
            ImportColumn::make('residencia')
                ->label('Residência')
                ->example('Luanda')
                ->ignoreBlankState()
                ->rules(['nullable', 'string', 'max:150']),
            ImportColumn::make('status')
                ->label('Estado')
                ->helperText('"ativo" ou "inativo", em branco fica "ativo".')
                ->example('ativo')
                ->ignoreBlankState()
                ->rules(['nullable', 'in:ativo,inativo']),
        ];
    }

    public static function getOptionsFormComponents(): array
    {
        return [
            // Quem esta preso a 1 centro (coordenador_catequese_centro,
            // secretario_catequese) nem escolhe: o campo fica desactivado,
            // sempre com o proprio centro. Só admin_geral/coordenador_
            // catequese_paroquia (paroquia inteira) escolhem livremente —
            // mesmo padrao GESTORES_CENTRO_LIVRE dos Resources.
            //
            // Bug corrigido: usar ->visible(false) em vez de ->disabled()
            // para "esconder e fixar" fazia o valor desaparecer por completo
            // do array $options que chega ao job da fila — o getState() do
            // formulario da ImportAction, ao contrario de um form normal de
            // Resource, nao inclui campos invisiveis. Todas as linhas
            // falhavam com "Centro invalido" porque centro_id chegava a 0.
            // ->disabled() mantem o campo no formulario (visivel, so
            // bloqueado) e ->dehydrated() forca a inclusao mesmo desactivado
            // (por omissao, campos desactivados tambem sao excluidos).
            Select::make('centro_id')
                ->label('Centro')
                ->helperText('Todos os catequizandos importados ficam vinculados a este centro.')
                ->options(fn () => Centro::orderBy('nome')->pluck('nome', 'id'))
                ->required()
                ->default(fn () => Auth::user()?->centro_id)
                ->disabled(fn () => ! (Auth::user()?->hasRole(['admin_geral', 'coordenador_catequese_paroquia']) ?? false))
                ->dehydrated(),
        ];
    }

    public function resolveRecord(): ?Model
    {
        // A importacao serve so para registar catequizandos novos — nunca
        // actualiza um existente (o ficheiro nao traz nenhum identificador).
        return new Catequizando;
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

    /**
     * catequizando_centros guarda o historico completo de centros (molde
     * fiel_centros) — mesma primeira linha criada em
     * CreateCatequizando::afterCreate(), aqui para cada linha importada.
     */
    protected function afterCreate(): void
    {
        $this->record->centros()->attach($this->options['centro_id'], [
            'data_inicio' => now(),
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'A importação de '.number_format($import->successful_rows).' '
            .($import->successful_rows === 1 ? 'catequizando foi concluída' : 'catequizandos foi concluída').'.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '
                .($failedRowsCount === 1 ? 'linha falhou' : 'linhas falharam').' e não foram importadas.';
        }

        return $body;
    }
}
