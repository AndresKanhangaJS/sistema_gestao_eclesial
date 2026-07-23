<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CatequizandoResource\Pages;
use App\Filament\Resources\CatequizandoResource\RelationManagers\CentrosRelationManager;
use App\Filament\Resources\CatequizandoResource\RelationManagers\InscricoesRelationManager;
use App\Models\Catequizando;
use App\Models\Fiel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CatequizandoResource extends Resource
{
    protected static ?string $model = Catequizando::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Catequese';

    protected static ?string $modelLabel = 'Catequizando';

    protected static ?string $pluralModelLabel = 'Catequizandos';

    // Papeis que podem escolher livremente o centro; os restantes ficam
    // presos ao seu proprio centro (mesmo padrao do paroquia_id no
    // FielResource, adaptado a centro — docs/modulos/catequese.md seccao 2).
    private const GESTORES_CENTRO_LIVRE = ['admin_geral', 'coordenador_catequese_paroquia'];

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Catequizando')
                    ->columnSpanFull()
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Dados Pessoais')
                            ->schema([
                                // So escolhivel na criacao — mudar de centro depois exige
                                // a accao dedicada "Transferir de Centro" (RelationManager),
                                // porque tem efeitos em catequizando_centros e inscricao_turma
                                // (docs/modulos/catequese.md seccao 7.1), nunca edicao livre.
                                Forms\Components\Select::make('centro_id')
                                    ->label('Centro')
                                    ->relationship('centro', 'nome')
                                    ->required()
                                    ->visible(fn (string $operation) => $operation === 'create'
                                        && (Auth::user()?->hasRole(self::GESTORES_CENTRO_LIVRE) ?? false))
                                    ->default(fn () => Auth::user()?->centro_id),
                                Forms\Components\TextInput::make('centro.nome')
                                    ->label('Centro actual')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visible(fn (string $operation) => $operation === 'edit')
                                    ->helperText('Para mudar de centro, use "Transferir de Centro" no separador Centros.'),
                                Forms\Components\Select::make('fiel_id')
                                    ->label('Fiel vinculado')
                                    ->relationship('fiel', 'nome')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function (?string $state, Forms\Set $set) {
                                        if (filled($state)) {
                                            $set('nome_completo', Fiel::find($state)?->nome);
                                        }
                                    })
                                    ->helperText('Opcional — só se o catequizando já estiver cadastrado como Fiel. Ao seleccionar, copia o nome automaticamente (pode editar a seguir).'),
                                Forms\Components\TextInput::make('nome_completo')
                                    ->label('Nome Completo')
                                    ->required()
                                    ->maxLength(200)
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('nome_pai')
                                    ->label('Nome do Pai')
                                    ->maxLength(150),
                                Forms\Components\TextInput::make('nome_mae')
                                    ->label('Nome da Mãe')
                                    ->maxLength(150),
                                Forms\Components\TextInput::make('profissao')
                                    ->label('Profissão (dos pais/encarregado)')
                                    ->maxLength(100),
                                Forms\Components\DatePicker::make('data_nascimento')
                                    ->label('Data de Nascimento')
                                    ->required()
                                    ->maxDate(now()),
                                Forms\Components\Select::make('sexo')
                                    ->label('Sexo')
                                    ->options([
                                        'M' => 'Masculino',
                                        'F' => 'Feminino',
                                    ])
                                    ->required(),
                                Forms\Components\TextInput::make('numero_identificacao')
                                    ->label('Nº de Identificação (BI)')
                                    ->maxLength(30)
                                    ->unique(ignoreRecord: true),
                                Forms\Components\Select::make('status')
                                    ->label('Estado')
                                    ->options([
                                        'ativo' => 'Activo',
                                        'inativo' => 'Inactivo',
                                    ])
                                    ->required()
                                    ->default('ativo'),
                            ])
                            ->columns(2),
                        Forms\Components\Tabs\Tab::make('Naturalidade')
                            ->schema([
                                Forms\Components\TextInput::make('municipio_nascimento')
                                    ->label('Município de Nascimento')
                                    ->maxLength(100),
                                Forms\Components\TextInput::make('provincia_nascimento')
                                    ->label('Província de Nascimento')
                                    ->maxLength(100),
                                Forms\Components\TextInput::make('pais_nascimento')
                                    ->label('País de Nascimento')
                                    ->default('Angola')
                                    ->maxLength(80),
                            ])
                            ->columns(2),
                        Forms\Components\Tabs\Tab::make('Contacto')
                            ->schema([
                                Forms\Components\TextInput::make('residencia')
                                    ->label('Residência')
                                    ->maxLength(150),
                                Forms\Components\TextInput::make('rua_numero')
                                    ->label('Rua/Número')
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('edificio')
                                    ->label('Edifício')
                                    ->maxLength(80),
                                Forms\Components\TextInput::make('casa_ap')
                                    ->label('Casa/Apartamento')
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('telefone')
                                    ->label('Telefone')
                                    ->tel()
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('telefone_casa')
                                    ->label('Telefone de Casa')
                                    ->tel()
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(100),
                            ])
                            ->columns(2),
                        Forms\Components\Tabs\Tab::make('Dados Religiosos')
                            ->schema([
                                Forms\Components\Group::make()
                                    ->relationship('dadosReligiosos')
                                    ->schema([
                                        Forms\Components\Fieldset::make('Baptismo')
                                            ->schema([
                                                Forms\Components\TextInput::make('paroquia_baptismo')
                                                    ->label('Paróquia de Baptismo')
                                                    ->maxLength(150),
                                                Forms\Components\DatePicker::make('data_baptismo')
                                                    ->label('Data de Baptismo'),
                                                Forms\Components\TextInput::make('pais_baptismo')
                                                    ->label('País de Baptismo')
                                                    ->maxLength(80),
                                            ])
                                            ->columns(3),
                                        Forms\Components\Fieldset::make('Comunhão')
                                            ->schema([
                                                Forms\Components\TextInput::make('paroquia_comunhao')
                                                    ->label('Paróquia de Comunhão')
                                                    ->maxLength(150),
                                                Forms\Components\DatePicker::make('data_comunhao')
                                                    ->label('Data de Comunhão'),
                                                Forms\Components\TextInput::make('pais_comunhao')
                                                    ->label('País de Comunhão')
                                                    ->maxLength(80),
                                            ])
                                            ->columns(3),
                                        Forms\Components\Fieldset::make('Padrinhos')
                                            ->schema([
                                                Forms\Components\TextInput::make('padrinho_nome')
                                                    ->label('Nome do Padrinho')
                                                    ->maxLength(150),
                                                Forms\Components\TextInput::make('padrinho_telefone')
                                                    ->label('Telefone do Padrinho')
                                                    ->tel()
                                                    ->maxLength(20),
                                                Forms\Components\TextInput::make('madrinha_nome')
                                                    ->label('Nome da Madrinha')
                                                    ->maxLength(150),
                                                Forms\Components\TextInput::make('madrinha_telefone')
                                                    ->label('Telefone da Madrinha')
                                                    ->tel()
                                                    ->maxLength(20),
                                            ])
                                            ->columns(2),
                                        Forms\Components\Fieldset::make('Transferência')
                                            ->schema([
                                                Forms\Components\TextInput::make('paroquia_transferencia')
                                                    ->label('Paróquia de Transferência')
                                                    ->maxLength(150),
                                                Forms\Components\TextInput::make('ano_transferencia')
                                                    ->label('Ano de Transferência')
                                                    ->numeric()
                                                    ->minValue(1900)
                                                    ->maxValue(2100),
                                            ])
                                            ->columns(2),
                                        Forms\Components\Toggle::make('pertence_grupo')
                                            ->label('Pertence a Grupo')
                                            ->default(false),
                                    ]),
                            ]),
                    ]),
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
                    ->sortable(),
                Tables\Columns\TextColumn::make('data_nascimento')
                    ->label('Data de Nascimento')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sexo')
                    ->label('Sexo')
                    ->formatStateUsing(fn (string $state) => $state === 'M' ? 'Masculino' : 'Feminino'),
                Tables\Columns\TextColumn::make('telefone')
                    ->label('Telefone')
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('status')
                    ->label('Activo')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->status === 'ativo'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'ativo' => 'Activo',
                        'inativo' => 'Inactivo',
                    ]),
                Tables\Filters\SelectFilter::make('sexo')
                    ->label('Sexo')
                    ->options([
                        'M' => 'Masculino',
                        'F' => 'Feminino',
                    ]),
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

        // A ParoquiaScope ja limita a paroquia; reforca para o centro proprio
        // nos papeis que so gerem/leem o seu centro (docs/modulos/catequese.md
        // seccao 2).
        if ($user && $user->hasRole(['coordenador_catequese_centro', 'secretario_catequese', 'tesoureiro_catequese'])) {
            $query->where('centro_id', $user->centro_id);
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            CentrosRelationManager::class,
            InscricoesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCatequizandos::route('/'),
            'create' => Pages\CreateCatequizando::route('/create'),
            'edit' => Pages\EditCatequizando::route('/{record}/edit'),
        ];
    }
}
