<?php

namespace App\Filament\Resources;

use App\Enums\StatusConciliacao;
use App\Enums\TipoMovimento;
use App\Filament\Resources\MovimentoResource\Pages;
use App\Models\CategoriaReceita;
use App\Models\Centro;
use App\Models\MetodoPagamento;
use App\Models\Movimento;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
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

    public const MESES = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
    ];

    public static function mesLabel(?int $mes): ?string
    {
        return $mes ? (self::MESES[$mes] ?? null) : null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Movimento')
                    ->columnSpanFull()
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Lançamento')
                            ->schema([
                                Forms\Components\Select::make('natureza')
                                    ->label('Natureza')
                                    ->options([
                                        'receita' => 'Receita',
                                        'despesa' => 'Despesa',
                                    ])
                                    ->required()
                                    ->live()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(function (Forms\Components\Select $component, ?Model $record) {
                                        if (! $record) {
                                            return;
                                        }

                                        $component->state($record->tipo === TipoMovimento::DespesaCentro ? 'despesa' : 'receita');
                                    })
                                    ->afterStateUpdated(function (?string $state, Set $set) {
                                        if ($state === 'despesa') {
                                            $set('tipo', TipoMovimento::DespesaCentro->value);
                                            $set('categoria_receita_id', null);
                                            $set('categoria_movimento', null);
                                        } else {
                                            $set('tipo', null);
                                            $set('categoria_despesa_id', null);
                                        }
                                    }),
                                // Campo virtual (nao existe na BD): junta dizimo/ofertorio e as
                                // categorias de receita registadas numa unica lista, ao mesmo
                                // nivel — pedido do cliente ("tudo e receita"). A escolha aqui
                                // determina o tipo (e categoria_receita_id, quando aplicavel)
                                // reais, guardados nos campos escondidos abaixo.
                                Forms\Components\Select::make('categoria_movimento')
                                    ->label('Categoria')
                                    ->options(fn () => collect([
                                        TipoMovimento::Dizimo->value => 'Dízimo',
                                        TipoMovimento::Ofertorio->value => 'Ofertório',
                                    ])
                                        ->merge(
                                            CategoriaReceita::where('status', 'ativo')
                                                ->orderBy('nome')
                                                ->pluck('nome', 'id')
                                                ->mapWithKeys(fn ($nome, $id) => ["cr_{$id}" => $nome])
                                        )
                                        ->put(TipoMovimento::Campanha->value, 'Outras Contribuições (sem categoria específica)')
                                        ->all())
                                    ->required(fn (Get $get) => $get('natureza') === 'receita')
                                    ->visible(fn (Get $get) => $get('natureza') === 'receita')
                                    ->live()
                                    ->dehydrated(false)
                                    ->afterStateHydrated(function (Forms\Components\Select $component, ?Model $record) {
                                        if (! $record || $record->tipo === TipoMovimento::DespesaCentro) {
                                            return;
                                        }

                                        $component->state(
                                            $record->tipo === TipoMovimento::Campanha && $record->categoria_receita_id
                                                ? "cr_{$record->categoria_receita_id}"
                                                : $record->tipo->value
                                        );
                                    })
                                    ->afterStateUpdated(function (?string $state, Set $set) {
                                        if (! filled($state)) {
                                            return;
                                        }

                                        if (str_starts_with($state, 'cr_')) {
                                            $set('tipo', TipoMovimento::Campanha->value);
                                            $set('categoria_receita_id', (int) substr($state, 3));

                                            return;
                                        }

                                        $set('tipo', $state);
                                        $set('categoria_receita_id', null);
                                    }),
                                Forms\Components\Hidden::make('tipo'),
                                Forms\Components\Hidden::make('categoria_receita_id'),
                                Forms\Components\Select::make('centro_id')
                                    ->label('Centro')
                                    ->relationship('centro', 'nome')
                                    ->required()
                                    ->live()
                                    ->visible(fn () => ! (Auth::user()?->hasRole(['tesoureiro_centro', 'coordenador_centro']) ?? false))
                                    ->default(fn () => Auth::user()?->centro_id)
                                    ->afterStateUpdated(fn (Set $set) => $set('fiel_id', null)),
                                Forms\Components\Select::make('fiel_id')
                                    ->label('Fiel')
                                    ->relationship(
                                        'fiel',
                                        'nome',
                                        modifyQueryUsing: fn (Builder $query, Get $get) => $query
                                            ->where('status', 'ativo')
                                            ->when(
                                                $get('centro_id'),
                                                fn (Builder $query, $centroId) => $query->whereHas(
                                                    'centros',
                                                    fn (Builder $query) => $query->where('centros.id', $centroId)->whereNull('fiel_centros.data_fim')
                                                )
                                            ),
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->required(fn (Get $get) => $get('tipo') === TipoMovimento::Dizimo->value)
                                    ->visible(fn (Get $get) => $get('tipo') === TipoMovimento::Dizimo->value)
                                    ->helperText('Só mostra fiéis activos vinculados ao centro seleccionado acima.'),
                                Forms\Components\Select::make('categoria_despesa_id')
                                    ->label('Categoria de Despesa')
                                    ->relationship('categoriaDespesa', 'nome')
                                    ->required(fn (Get $get) => $get('natureza') === 'despesa')
                                    ->visible(fn (Get $get) => $get('natureza') === 'despesa'),
                                Forms\Components\TextInput::make('valor')
                                    ->label('Valor')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->prefix('Kz'),
                                Forms\Components\DatePicker::make('data_movimento')
                                    ->label('Data do Movimento')
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
                                    ->options(self::MESES)
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
                                    ->label('Método de Pagamento')
                                    ->relationship('metodoPagamento', 'nome')
                                    ->required()
                                    ->live(),
                                Forms\Components\Select::make('banco_id')
                                    ->label('Banco')
                                    ->relationship('banco', 'nome_banco'),
                                Forms\Components\TextInput::make('numero_referencia_bancaria')
                                    ->label('Número de Referência Bancária')
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),
                                Forms\Components\FileUpload::make('comprovativo_path')
                                    ->label('Comprovativo')
                                    ->disk(config('filesystems.default'))
                                    ->directory('comprovativos')
                                    ->getUploadedFileNameForStorageUsing(
                                        fn ($file) => Str::uuid().'.'.$file->getClientOriginalExtension()
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
                    ->label('Tipo')
                    ->formatStateUsing(fn (TipoMovimento $state) => match ($state) {
                        TipoMovimento::Dizimo => 'Dízimo',
                        TipoMovimento::Ofertorio => 'Ofertório',
                        TipoMovimento::Campanha => 'Outras Contribuições',
                        TipoMovimento::DespesaCentro => 'Despesa',
                    }),
                Tables\Columns\TextColumn::make('centro.nome')
                    ->label('Centro')
                    ->sortable(),
                Tables\Columns\TextColumn::make('fiel.nome')
                    ->label('Fiel')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('ano_competencia')
                    ->label('Ano')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('mes_competencia')
                    ->label('Mês')
                    ->formatStateUsing(fn (?int $state) => self::mesLabel($state))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('valor')
                    ->label('Valor')
                    ->money('AOA')
                    ->sortable(),
                Tables\Columns\TextColumn::make('data_movimento')
                    ->label('Data')
                    ->date()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status_conciliacao')
                    ->label('Estado da Conciliação')
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
                    ->label('Tipo')
                    ->options([
                        TipoMovimento::Dizimo->value => 'Dízimo',
                        TipoMovimento::Ofertorio->value => 'Ofertório',
                        TipoMovimento::Campanha->value => 'Outras Contribuições',
                        TipoMovimento::DespesaCentro->value => 'Despesa de Centro',
                    ]),
                Tables\Filters\SelectFilter::make('status_conciliacao')
                    ->label('Estado da Conciliação')
                    ->options([
                        StatusConciliacao::Pendente->value => 'Pendente',
                        StatusConciliacao::Aprovado->value => 'Aprovado',
                        StatusConciliacao::Rejeitado->value => 'Rejeitado',
                    ]),
                Tables\Filters\SelectFilter::make('centro_id')
                    ->label('Centro')
                    ->options(fn () => Centro::pluck('nome', 'id'))
                    ->visible(fn () => ! (Auth::user()?->hasRole(['tesoureiro_centro', 'coordenador_centro']) ?? false)),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('verComprovativo')
                        ->label('Ver comprovativo')
                        ->icon('heroicon-o-paper-clip')
                        // So aparece se houver ficheiro E o disco activo estiver
                        // mesmo utilizavel (o s3 exige bucket configurado —
                        // gerar a URL assinada falha, e rebentaria o render da
                        // tabela, sem isso).
                        ->visible(fn (Movimento $record) => filled($record->comprovativo_path)
                            && (config('filesystems.default') !== 's3' || filled(config('filesystems.disks.s3.bucket'))))
                        ->url(function (Movimento $record) {
                            $disk = config('filesystems.default');

                            try {
                                return $disk === 's3'
                                    ? Storage::disk($disk)->temporaryUrl($record->comprovativo_path, now()->addMinutes(60))
                                    : Storage::disk($disk)->url($record->comprovativo_path);
                            } catch (\Throwable $e) {
                                return null;
                            }
                        }, shouldOpenInNewTab: true),
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
                ]),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();

        if ($user && $user->hasRole(['tesoureiro_centro', 'coordenador_centro'])) {
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
