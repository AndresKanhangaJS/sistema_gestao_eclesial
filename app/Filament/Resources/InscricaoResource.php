<?php

namespace App\Filament\Resources;

use App\Enums\EstadoInscricao;
use App\Enums\TipoInscricao;
use App\Filament\Resources\InscricaoResource\Pages;
use App\Filament\Resources\InscricaoResource\RelationManagers\InscricaoTurmaRelationManager;
use App\Models\Inscricao;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class InscricaoResource extends Resource
{
    protected static ?string $model = Inscricao::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Catequese';

    protected static ?string $modelLabel = 'Inscrição';

    protected static ?string $pluralModelLabel = 'Inscrições';

    // O plural automatico do Filament para "Inscricao" seria "inscricaos"
    // (pluralizacao ingenua em ingles) — slug explicito para um URL correcto.
    protected static ?string $slug = 'inscricoes';

    // coordenador_catequese_paroquia nao tem centro_id proprio (papel ao
    // nivel da paroquia, cobre varios centros) — por isso, ao contrario dos
    // outros Resources do modulo, tem de poder ESCOLHER o centro no form em
    // vez de o herdar do proprio utilizador (bug corrigido, ver
    // docs/modulos/catequese.md secc. 10).
    private const GESTORES_CENTRO_LIVRE = ['admin_geral', 'coordenador_catequese_paroquia'];

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        // Ao contrario dos outros campos deste form, centro_id PRECISA
                        // de aparecer para quem nao tem centro proprio (admin_geral,
                        // coordenador_catequese_paroquia) — sem isto, a inscricao ficava
                        // sem centro_id e a gravacao falhava (NOT NULL). Para os
                        // restantes papeis mantem-se escondido e herdado do utilizador,
                        // reforcado no servidor nas Pages.
                        Forms\Components\Select::make('centro_id')
                            ->label('Centro')
                            ->relationship('centro', 'nome')
                            ->required()
                            ->live()
                            ->visible(fn (string $operation) => $operation === 'create'
                                && (Auth::user()?->hasRole(self::GESTORES_CENTRO_LIVRE) ?? false))
                            ->default(fn () => Auth::user()?->centro_id),
                        Forms\Components\TextInput::make('centro.nome')
                            ->label('Centro')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (string $operation) => $operation === 'edit'),
                        Forms\Components\Select::make('catequizando_id')
                            ->label('Catequizando')
                            ->relationship(
                                'catequizando',
                                'nome_completo',
                                modifyQueryUsing: fn (Builder $query, Get $get) => self::scopePorCentro($query, 'centro_id', $get),
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),
                        Forms\Components\Select::make('ano_letivo_id')
                            ->label('Ano Lectivo')
                            ->relationship('anoLetivo', 'nome')
                            ->required()
                            ->live()
                            ->rule(function (Get $get, ?Model $record) {
                                return function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                    // Regra de negocio central: uma inscricao activa por
                                    // (catequizando_id, ano_letivo_id) — activa = estado != cancelado.
                                    $query = Inscricao::where('catequizando_id', $get('catequizando_id'))
                                        ->where('ano_letivo_id', $value)
                                        ->where('estado', '!=', EstadoInscricao::Cancelado->value);

                                    if ($record) {
                                        $query->whereKeyNot($record->getKey());
                                    }

                                    if ($query->exists()) {
                                        $fail('Já existe uma inscrição activa para este catequizando neste ano lectivo.');
                                    }
                                };
                            }),
                        Forms\Components\Select::make('ano_catequetico_id')
                            ->label('Ano de Catequese (este ano lectivo)')
                            ->relationship('anoCatequetico', 'nome', fn (Builder $query) => $query->orderBy('ordem'))
                            ->required()
                            ->helperText('Usado para filtrar as turmas compatíveis ao colocar em turma.'),
                        Forms\Components\Select::make('sacramentos')
                            ->label('Sacramento(s)')
                            ->relationship('sacramentos', 'nome', fn (Builder $query) => $query->orderBy('ordem'))
                            ->multiple()
                            ->preload()
                            ->required()
                            ->helperText('O conjunto exacto tem de bater certo com a turma escolhida — ex.: "Baptismo e Comunhão" não entra numa turma só de "Baptismo".'),
                        Forms\Components\Select::make('catequista_id')
                            ->label('Catequista Responsável')
                            ->relationship('catequista', 'nome_completo')
                            ->searchable()
                            ->preload()
                            ->helperText('Quem atendeu/processou a ficha — não é necessariamente quem lecciona a turma.'),
                        Forms\Components\Select::make('tipo')
                            ->label('Tipo')
                            ->options([
                                TipoInscricao::Nova->value => 'Nova',
                                TipoInscricao::Confirmacao->value => 'Confirmação (progressão de ano)',
                            ])
                            ->required()
                            ->default(TipoInscricao::Nova->value),
                        Forms\Components\TextInput::make('numero_ficha')
                            ->label('Nº da Ficha')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (string $operation) => $operation === 'edit')
                            ->helperText('Gerado automaticamente ao criar a ficha (a partir de 0001, por ano lectivo).'),
                        Forms\Components\DatePicker::make('data_atendimento')
                            ->label('Data de Atendimento')
                            ->required()
                            ->default(now()),
                        Forms\Components\Select::make('estado')
                            ->label('Estado')
                            ->options([
                                EstadoInscricao::Inscrito->value => 'Inscrito',
                                EstadoInscricao::Aprovado->value => 'Aprovado',
                                EstadoInscricao::Reprovado->value => 'Reprovado',
                                EstadoInscricao::Desistente->value => 'Desistente',
                                EstadoInscricao::Cancelado->value => 'Cancelado',
                            ])
                            ->required()
                            ->default(EstadoInscricao::Inscrito->value)
                            ->helperText('"Aprovado" habilita a geração da inscrição do ano lectivo seguinte.'),
                        Forms\Components\Textarea::make('observacoes')
                            ->label('Observações')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('data_atendimento', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('catequizando.nome_completo')
                    ->label('Catequizando')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('centro.nome')
                    ->label('Centro'),
                Tables\Columns\TextColumn::make('anoLetivo.nome')
                    ->label('Ano Lectivo'),
                Tables\Columns\TextColumn::make('anoCatequetico.nome')
                    ->label('Ano Catequese')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('sacramentos.nome')
                    ->label('Sacramento(s)')
                    ->badge()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('numero_ficha')
                    ->label('Nº Ficha')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('tipo')
                    ->label('Tipo')
                    ->formatStateUsing(fn (TipoInscricao $state) => match ($state) {
                        TipoInscricao::Nova => 'Nova',
                        TipoInscricao::Confirmacao => 'Confirmação',
                    }),
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
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        EstadoInscricao::Inscrito->value => 'Inscrito',
                        EstadoInscricao::Aprovado->value => 'Aprovado',
                        EstadoInscricao::Reprovado->value => 'Reprovado',
                        EstadoInscricao::Desistente->value => 'Desistente',
                        EstadoInscricao::Cancelado->value => 'Cancelado',
                    ]),
                Tables\Filters\SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options([
                        TipoInscricao::Nova->value => 'Nova',
                        TipoInscricao::Confirmacao->value => 'Confirmação',
                    ]),
                Tables\Filters\SelectFilter::make('ano_letivo_id')
                    ->label('Ano Lectivo')
                    ->relationship('anoLetivo', 'nome'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();

        if ($user && $user->hasRole(['coordenador_catequese_centro', 'secretario_catequese', 'tesoureiro_catequese'])) {
            $query->where('centro_id', $user->centro_id);
        }

        return $query;
    }

    /**
     * Restringe uma relationship (ex.: catequizando_id) ao centro do
     * utilizador autenticado, quando o papel for limitado a 1 centro. Para
     * quem escolhe o centro livremente (admin_geral/coordenador_catequese_
     * paroquia), usa o centro seleccionado no proprio formulario ($get).
     */
    public static function scopePorCentro(Builder $query, string $coluna, ?Get $get = null): Builder
    {
        $user = Auth::user();

        if ($user && $user->hasRole(['coordenador_catequese_centro', 'secretario_catequese', 'tesoureiro_catequese'])) {
            $query->where($coluna, $user->centro_id);
        } elseif ($get !== null && filled($get('centro_id'))) {
            $query->where($coluna, $get('centro_id'));
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            InscricaoTurmaRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInscricaos::route('/'),
            'create' => Pages\CreateInscricao::route('/create'),
            'edit' => Pages\EditInscricao::route('/{record}/edit'),
        ];
    }
}
