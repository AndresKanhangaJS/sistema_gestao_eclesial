<?php

namespace App\Filament\Resources;

use App\Enums\StatusConciliacao;
use App\Enums\TipoMovimento;
use App\Filament\Resources\MovimentoResource\Pages;
use App\Models\Centro;
use App\Models\MetodoPagamento;
use App\Models\Movimento;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MovimentoResource extends Resource
{
    protected static ?string $model = Movimento::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Financeiro';

    protected static ?string $modelLabel = 'Movimento';

    protected static ?string $pluralModelLabel = 'Movimentos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Movimento')
                    ->columnSpanFull()
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Lançamento')
                            ->schema([
                                Forms\Components\Select::make('tipo')
                                    ->options([
                                        TipoMovimento::Dizimo->value => 'Dízimo',
                                        TipoMovimento::Ofertorio->value => 'Ofertório',
                                        TipoMovimento::Campanha->value => 'Campanha',
                                        TipoMovimento::DespesaCentro->value => 'Despesa de Centro',
                                    ])
                                    ->required()
                                    ->live(),
                                Forms\Components\Select::make('centro_id')
                                    ->relationship('centro', 'nome')
                                    ->required()
                                    ->visible(fn () => ! (Auth::user()?->hasRole('tesoureiro_centro') ?? false))
                                    ->default(fn () => Auth::user()?->centro_id),
                                Forms\Components\Select::make('fiel_id')
                                    ->relationship('fiel', 'nome')
                                    ->searchable()
                                    ->preload()
                                    ->required(fn (Get $get) => $get('tipo') === TipoMovimento::Dizimo->value)
                                    ->visible(fn (Get $get) => $get('tipo') === TipoMovimento::Dizimo->value),
                                Forms\Components\Select::make('categoria_despesa_id')
                                    ->relationship('categoriaDespesa', 'nome')
                                    ->required(fn (Get $get) => $get('tipo') === TipoMovimento::DespesaCentro->value)
                                    ->visible(fn (Get $get) => $get('tipo') === TipoMovimento::DespesaCentro->value),
                                Forms\Components\TextInput::make('valor')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->prefix('Kz'),
                                Forms\Components\DatePicker::make('data_movimento')
                                    ->required()
                                    ->default(now()),
                                Forms\Components\Select::make('ano_competencia')
                                    ->label('Ano de competência')
                                    ->options(fn () => array_combine(
                                        range(now()->year - 2, now()->year),
                                        range(now()->year - 2, now()->year),
                                    ))
                                    ->required(fn (Get $get) => $get('tipo') === TipoMovimento::Dizimo->value)
                                    ->visible(fn (Get $get) => $get('tipo') === TipoMovimento::Dizimo->value),
                                Forms\Components\Select::make('mes_competencia')
                                    ->label('Mês de competência')
                                    ->options([
                                        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
                                        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
                                        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
                                    ])
                                    ->required(fn (Get $get) => $get('tipo') === TipoMovimento::Dizimo->value)
                                    ->visible(fn (Get $get) => $get('tipo') === TipoMovimento::Dizimo->value)
                                    ->rule(function (Get $get, ?Model $record) {
                                        return function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                            if ($get('tipo') !== TipoMovimento::Dizimo->value) {
                                                return;
                                            }

                                            $query = Movimento::where('fiel_id', $get('fiel_id'))
                                                ->where('ano_competencia', $get('ano_competencia'))
                                                ->where('mes_competencia', $value)
                                                ->where('tipo', TipoMovimento::Dizimo->value);

                                            if ($record) {
                                                $query->whereKeyNot($record->getKey());
                                            }

                                            if ($query->exists()) {
                                                $fail('Já existe um dízimo lançado para este fiel neste mês/ano.');
                                            }
                                        };
                                    }),
                            ]),
                        Forms\Components\Tabs\Tab::make('Pagamento')
                            ->schema([
                                Forms\Components\Select::make('metodo_pagamento_id')
                                    ->relationship('metodoPagamento', 'nome')
                                    ->required()
                                    ->live(),
                                Forms\Components\Select::make('banco_id')
                                    ->relationship('banco', 'nome_banco'),
                                Forms\Components\TextInput::make('numero_referencia_bancaria')
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),
                                Forms\Components\FileUpload::make('comprovativo_path')
                                    ->label('Comprovativo')
                                    ->disk('s3')
                                    ->directory('comprovativos')
                                    ->getUploadedFileNameForStorageUsing(
                                        fn ($file) => Str::uuid() . '.' . $file->getClientOriginalExtension()
                                    )
                                    ->required(
                                        fn (Get $get) => MetodoPagamento::find($get('metodo_pagamento_id'))?->exige_comprovativo ?? false
                                    ),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('tipo')
                    ->formatStateUsing(fn (TipoMovimento $state) => match ($state) {
                        TipoMovimento::Dizimo => 'Dízimo',
                        TipoMovimento::Ofertorio => 'Ofertório',
                        TipoMovimento::Campanha => 'Campanha',
                        TipoMovimento::DespesaCentro => 'Despesa',
                    }),
                Tables\Columns\TextColumn::make('centro.nome')
                    ->label('Centro')
                    ->sortable(),
                Tables\Columns\TextColumn::make('fiel.nome')
                    ->label('Fiel')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('valor')
                    ->money('AOA')
                    ->sortable(),
                Tables\Columns\TextColumn::make('data_movimento')
                    ->date()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status_conciliacao')
                    ->formatStateUsing(fn (StatusConciliacao $state) => match ($state) {
                        StatusConciliacao::Pendente => 'Pendente',
                        StatusConciliacao::Aprovado => 'Aprovado',
                        StatusConciliacao::Rejeitado => 'Rejeitado',
                    })
                    ->colors([
                        'warning' => StatusConciliacao::Pendente->value,
                        'success' => StatusConciliacao::Aprovado->value,
                        'danger' => StatusConciliacao::Rejeitado->value,
                    ]),
                Tables\Columns\IconColumn::make('tem_comprovativo')
                    ->label('Comprovativo')
                    ->boolean()
                    ->state(fn (Movimento $record) => filled($record->comprovativo_path)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')
                    ->options([
                        TipoMovimento::Dizimo->value => 'Dízimo',
                        TipoMovimento::Ofertorio->value => 'Ofertório',
                        TipoMovimento::Campanha->value => 'Campanha',
                        TipoMovimento::DespesaCentro->value => 'Despesa de Centro',
                    ]),
                Tables\Filters\SelectFilter::make('status_conciliacao')
                    ->options([
                        StatusConciliacao::Pendente->value => 'Pendente',
                        StatusConciliacao::Aprovado->value => 'Aprovado',
                        StatusConciliacao::Rejeitado->value => 'Rejeitado',
                    ]),
                Tables\Filters\SelectFilter::make('centro_id')
                    ->label('Centro')
                    ->options(fn () => Centro::pluck('nome', 'id'))
                    ->visible(fn () => ! (Auth::user()?->hasRole('tesoureiro_centro') ?? false)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('verComprovativo')
                    ->label('Ver comprovativo')
                    ->icon('heroicon-o-paper-clip')
                    ->visible(fn (Movimento $record) => filled($record->comprovativo_path))
                    ->url(fn (Movimento $record) => Storage::disk('s3')->temporaryUrl(
                        $record->comprovativo_path,
                        now()->addMinutes(60)
                    ), shouldOpenInNewTab: true),
                Tables\Actions\Action::make('aprovar')
                    ->label('Aprovar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Movimento $record) => $record->status_conciliacao === StatusConciliacao::Pendente
                        && (Auth::user()?->can('aprovar', $record) ?? false))
                    ->action(fn (Movimento $record) => $record->update(['status_conciliacao' => StatusConciliacao::Aprovado])),
                Tables\Actions\Action::make('rejeitar')
                    ->label('Rejeitar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Movimento $record) => $record->status_conciliacao === StatusConciliacao::Pendente
                        && (Auth::user()?->can('rejeitar', $record) ?? false))
                    ->form([
                        Forms\Components\Textarea::make('motivo_rejeicao')
                            ->label('Motivo da rejeição')
                            ->required(),
                    ])
                    ->action(fn (array $data, Movimento $record) => $record->update([
                        'status_conciliacao' => StatusConciliacao::Rejeitado,
                        'motivo_rejeicao' => $data['motivo_rejeicao'],
                    ])),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();

        if ($user && $user->hasRole('tesoureiro_centro')) {
            $query->where('centro_id', $user->centro_id);
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMovimentos::route('/'),
            'create' => Pages\CreateMovimento::route('/create'),
            'edit' => Pages\EditMovimento::route('/{record}/edit'),
        ];
    }
}
