<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Gestao de utilizadores e atribuicao de papel/paroquia/centro.
 * admin_geral gere tudo, sem restricoes (UserPolicy).
 * administrador_paroquial gere utilizadores da sua propria paroquia, mas so
 * pode atribuir os papeis tesoureiro_paroquial/tesoureiro_centro — nunca
 * admin_geral, consultor ou outro administrador_paroquial (ver
 * papeisAtribuiveis()/papelPermitido(), reforcado em UserPolicy).
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Acessos';

    protected static ?string $modelLabel = 'Utilizador';

    protected static ?string $pluralModelLabel = 'Utilizadores';

    private const PAPEIS = [
        'admin_geral' => 'Administrador Geral',
        'administrador_paroquial' => 'Administrador Paroquial',
        'tesoureiro_paroquial' => 'Tesoureiro Paroquial',
        'tesoureiro_centro' => 'Tesoureiro de Centro',
        'consultor' => 'Consultor',
    ];

    private const PAPEIS_COM_PAROQUIA = ['administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro'];

    /**
     * Papeis que o utilizador autenticado pode atribuir a outros. admin_geral
     * escolhe livremente; administrador_paroquial so pode criar/editar
     * tesoureiro_paroquial e tesoureiro_centro na sua propria paroquia.
     *
     * @return array<string, string>
     */
    public static function papeisAtribuiveis(): array
    {
        if (Auth::user()?->hasRole('admin_geral')) {
            return self::PAPEIS;
        }

        return [
            'tesoureiro_paroquial' => self::PAPEIS['tesoureiro_paroquial'],
            'tesoureiro_centro' => self::PAPEIS['tesoureiro_centro'],
        ];
    }

    /**
     * Validacao server-side do papel submetido (defesa contra adulteracao do
     * Select no cliente — o mesmo princípio do ForcaParoquiaUtilizadorObserver):
     * um administrador_paroquial nunca consegue promover alguem a admin_geral,
     * consultor ou outro administrador_paroquial, mesmo manipulando o form.
     */
    public static function papelPermitido(string $role): string
    {
        abort_unless(array_key_exists($role, self::papeisAtribuiveis()), 403, 'Papel não permitido.');

        return $role;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Utilizador')
                    ->columnSpanFull()
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Dados de Acesso')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nome')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('password')
                                    ->label('Palavra-passe')
                                    ->password()
                                    ->revealable()
                                    // O model faz cast 'hashed' — nao voltar a
                                    // fazer Hash::make() aqui (dupla cifragem).
                                    ->dehydrated(fn (?string $state) => filled($state))
                                    ->required(fn (string $operation) => $operation === 'create')
                                    ->maxLength(255),
                                Forms\Components\Select::make('role')
                                    ->label('Papel')
                                    ->options(fn () => self::papeisAtribuiveis())
                                    ->required()
                                    ->live(),
                                Forms\Components\Select::make('status')
                                    ->label('Estado')
                                    ->options([
                                        'ativo' => 'Activo',
                                        'inativo' => 'Inactivo',
                                    ])
                                    ->required()
                                    ->default('ativo'),
                            ]),
                        Forms\Components\Tabs\Tab::make('Atribuição')
                            ->schema([
                                // So o admin_geral escolhe a paroquia livremente;
                                // o administrador_paroquial fica sempre preso a
                                // sua propria (ForcaParoquiaUtilizadorObserver
                                // reforca isto no servidor, mesmo que este campo
                                // seja adulterado no cliente).
                                Forms\Components\Select::make('paroquia_id')
                                    ->label('Paróquia')
                                    ->relationship('paroquia', 'nome')
                                    ->required(fn (Get $get) => in_array($get('role'), self::PAPEIS_COM_PAROQUIA, true))
                                    ->visible(fn (Get $get) => in_array($get('role'), self::PAPEIS_COM_PAROQUIA, true)
                                        && (Auth::user()?->hasRole('admin_geral') ?? false))
                                    ->default(fn () => Auth::user()?->paroquia_id)
                                    ->live(),
                                Forms\Components\Select::make('centro_id')
                                    ->label('Centro')
                                    ->relationship(
                                        'centro',
                                        'nome',
                                        function (Builder $query, Get $get) {
                                            // $get('paroquia_id') so resolve depois do admin_geral
                                            // escolher uma paroquia; para administrador_paroquial o
                                            // campo esta escondido, por isso cai sempre no fallback
                                            // da sua propria paroquia. Sem valor nenhum (admin_geral
                                            // ainda nao escolheu), nao filtra — Centro::where(...,
                                            // null) nunca devolveria nada.
                                            $paroquiaId = $get('paroquia_id') ?? Auth::user()?->paroquia_id;

                                            if ($paroquiaId) {
                                                $query->where('paroquia_id', $paroquiaId);
                                            }
                                        },
                                    )
                                    ->required(fn (Get $get) => $get('role') === 'tesoureiro_centro')
                                    ->visible(fn (Get $get) => $get('role') === 'tesoureiro_centro'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Papel')
                    ->formatStateUsing(fn (?string $state) => self::PAPEIS[$state] ?? $state)
                    ->badge(),
                Tables\Columns\TextColumn::make('paroquia.nome')
                    ->label('Paróquia')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('centro.nome')
                    ->label('Centro')
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('status')
                    ->label('Estado')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->status === 'ativo'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Papel')
                    ->options(self::PAPEIS)
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->whereHas('roles', fn (Builder $q) => $q->where('name', $data['value']));
                    }),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'ativo' => 'Activo',
                        'inativo' => 'Inactivo',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();

        // Sem ParoquiaScope no model User (evita efeitos colaterais noutros
        // pontos do sistema que consultam User, ex.: login, comando de
        // notificacoes) — o isolamento e feito aqui, so para esta Resource.
        if ($user && $user->hasRole('administrador_paroquial')) {
            $query->where('paroquia_id', $user->paroquia_id);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
