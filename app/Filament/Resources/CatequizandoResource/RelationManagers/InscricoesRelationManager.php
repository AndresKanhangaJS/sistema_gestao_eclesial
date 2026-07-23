<?php

namespace App\Filament\Resources\CatequizandoResource\RelationManagers;

use App\Enums\EstadoInscricao;
use App\Enums\TipoInscricao;
use App\Models\Inscricao;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Extracto das inscricoes do catequizando por ano lectivo. So leitura — o
 * lancamento/edicao de inscricoes e a colocacao em turma continuam
 * exclusivos do InscricaoResource (numero_ficha unico, regra de uma
 * inscricao activa por ano lectivo, etc.).
 */
class InscricoesRelationManager extends RelationManager
{
    protected static string $relationship = 'inscricoes';

    protected static ?string $title = 'Inscrições';

    protected static ?string $recordTitleAttribute = 'numero_ficha';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('data_atendimento', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('anoLetivo.nome')
                    ->label('Ano Lectivo'),
                Tables\Columns\TextColumn::make('numero_ficha')
                    ->label('Nº Ficha'),
                Tables\Columns\BadgeColumn::make('tipo')
                    ->label('Tipo')
                    ->formatStateUsing(fn (TipoInscricao $state) => match ($state) {
                        TipoInscricao::Nova => 'Nova',
                        TipoInscricao::Confirmacao => 'Confirmação',
                    }),
                Tables\Columns\TextColumn::make('turmaAtiva.turma.publico_alvo')
                    ->label('Turma actual')
                    ->formatStateUsing(fn ($record) => $record->turmaAtiva?->turma
                        ? "{$record->turmaAtiva->turma->anoCatequetico?->nome} ({$record->turmaAtiva->turma->periodo})"
                        : null)
                    ->placeholder('Sem turma'),
                Tables\Columns\TextColumn::make('data_atendimento')
                    ->label('Data')
                    ->date()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('estado')
                    ->label('Estado')
                    ->formatStateUsing(fn (EstadoInscricao $state) => match ($state) {
                        EstadoInscricao::Inscrito => 'Inscrito',
                        EstadoInscricao::Aprovado => 'Aprovado',
                        EstadoInscricao::Reprovado => 'Reprovado',
                        EstadoInscricao::Desistente => 'Desistente',
                        EstadoInscricao::Cancelado => 'Cancelado',
                    })
                    ->colors([
                        'warning' => EstadoInscricao::Inscrito->value,
                        'success' => EstadoInscricao::Aprovado->value,
                        'danger' => fn ($state) => in_array($state, [EstadoInscricao::Reprovado, EstadoInscricao::Cancelado], true),
                        'gray' => EstadoInscricao::Desistente->value,
                    ]),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('verFicha')
                    ->label('Ver ficha')
                    ->icon('heroicon-o-eye')
                    ->url(fn (Inscricao $record) => route('filament.admin.resources.inscricoes.edit', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }
}
