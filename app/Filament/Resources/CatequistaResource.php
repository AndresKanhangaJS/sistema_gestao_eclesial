<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\TemExportacaoComEstado;
use App\Filament\Resources\CatequistaResource\Pages;
use App\Filament\Resources\CatequistaResource\RelationManagers\TurmasRelationManager;
use App\Models\Catequista;
use App\Models\Fiel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CatequistaResource extends Resource
{
    use TemExportacaoComEstado;

    protected static ?string $model = Catequista::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationGroup = 'Catequese';

    protected static ?string $modelLabel = 'Catequista';

    protected static ?string $pluralModelLabel = 'Catequistas';

    private const GESTORES_CENTRO_LIVRE = ['admin_geral', 'coordenador_catequese_paroquia'];

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        // paroquia_id e sempre NOT NULL, mas centro_id (abaixo) e opcional
                        // ("centro principal") — por isso, ao contrario do Catequizando/
                        // Turma/Inscricao, nao da para derivar sempre a paroquia a partir
                        // do centro; expomos o campo directamente, mesmo padrao do
                        // paroquia_id em FielResource/CentroResource.
                        Forms\Components\Select::make('paroquia_id')
                            ->label('Paróquia')
                            ->relationship('paroquia', 'nome')
                            ->required()
                            ->live()
                            ->visible(fn () => Auth::user()?->hasRole('admin_geral') ?? false)
                            ->default(fn () => Auth::user()?->paroquia_id),
                        Forms\Components\Select::make('centro_id')
                            ->label('Centro Principal')
                            ->relationship('centro', 'nome')
                            ->live()
                            ->visible(fn () => Auth::user()?->hasRole(self::GESTORES_CENTRO_LIVRE) ?? false)
                            ->default(fn () => Auth::user()?->centro_id)
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('fiel_id', null)),
                        Forms\Components\Select::make('fiel_id')
                            ->label('Fiel vinculado')
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
                            ->live()
                            ->afterStateUpdated(function (?string $state, Forms\Set $set) {
                                if (! filled($state)) {
                                    return;
                                }

                                $fiel = Fiel::find($state);

                                $set('nome_completo', $fiel?->nome);
                                $set('data_nascimento', $fiel?->data_nascimento?->toDateString());
                                $set('telefone', $fiel?->telefone);
                                $set('email', $fiel?->email);
                            })
                            ->helperText('Opcional: só fiéis activos vinculados ao Centro Principal acima (se escolhido). Ao seleccionar, copia nome, data de nascimento, telefone e email automaticamente (pode editar a seguir).'),
                        Forms\Components\Select::make('user_id')
                            ->label('Utilizador (login)')
                            ->relationship(
                                'user',
                                'name',
                                modifyQueryUsing: fn (Builder $query, Get $get) => $query->where(
                                    'paroquia_id',
                                    $get('paroquia_id') ?? Auth::user()?->paroquia_id
                                ),
                            )
                            ->searchable()
                            ->preload()
                            ->helperText('Opcional: só se o catequista tiver acesso próprio ao sistema.'),
                        Forms\Components\TextInput::make('nome_completo')
                            ->label('Nome Completo')
                            ->required()
                            ->maxLength(150)
                            ->columnSpanFull(),
                        Forms\Components\DatePicker::make('data_nascimento')
                            ->label('Data de Nascimento')
                            ->maxDate(now()),
                        Forms\Components\TextInput::make('telefone')
                            ->label('Telefone')
                            ->tel()
                            ->maxLength(20),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(100),
                        Forms\Components\Toggle::make('ativo')
                            ->label('Activo')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nome_completo')
            ->columns([
                Tables\Columns\TextColumn::make('nome_completo')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('centro.nome')
                    ->label('Centro')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('data_nascimento')
                    ->label('Data de Nascimento')
                    ->date()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('telefone')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('email')
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('ativo')
                    ->label('Activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('ativo')
                    ->label('Activo'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->headerActions([
                self::accaoExportarComEstado(
                    opcoesEstado: ['todos' => 'Todos', 'ativo' => 'Activo', 'inativo' => 'Inactivo'],
                    estadoPorOmissao: 'ativo',
                    rotaExcel: 'relatorios.catequistas.excel',
                    rotaPdf: 'relatorios.catequistas.pdf',
                    comAnoLectivo: true,
                ),
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

    public static function getRelations(): array
    {
        return [
            TurmasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCatequistas::route('/'),
            'create' => Pages\CreateCatequista::route('/create'),
            'edit' => Pages\EditCatequista::route('/{record}/edit'),
        ];
    }
}
