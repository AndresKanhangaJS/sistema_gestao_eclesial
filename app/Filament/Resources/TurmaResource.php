<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TurmaResource\Pages;
use App\Filament\Resources\TurmaResource\RelationManagers\CatequistasRelationManager;
use App\Filament\Resources\TurmaResource\RelationManagers\CatequizandosRelationManager;
use App\Filament\Resources\TurmaResource\RelationManagers\SacramentosRelationManager;
use App\Models\AnoCatequetico;
use App\Models\Centro;
use App\Models\Turma;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TurmaResource extends Resource
{
    protected static ?string $model = Turma::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $navigationGroup = 'Catequese';

    protected static ?string $modelLabel = 'Turma';

    protected static ?string $pluralModelLabel = 'Turmas';

    private const GESTORES_CENTRO_LIVRE = ['admin_geral', 'coordenador_catequese_paroquia'];

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        // O centro e fixo na turma (docs/modulos/catequese.md seccao 7.1) —
                        // so escolhivel na criacao; nao ha accao de "transferir turma de
                        // centro" (encerra-se a turma e abre-se outra no centro certo).
                        Forms\Components\Select::make('centro_id')
                            ->label('Centro')
                            ->relationship('centro', 'nome')
                            ->required()
                            ->visible(fn (string $operation) => $operation === 'create'
                                && (Auth::user()?->hasRole(self::GESTORES_CENTRO_LIVRE) ?? false))
                            ->default(fn () => Auth::user()?->centro_id),
                        Forms\Components\TextInput::make('centro.nome')
                            ->label('Centro')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (string $operation) => $operation === 'edit'),
                        Forms\Components\Select::make('ano_letivo_id')
                            ->label('Ano Lectivo')
                            ->relationship('anoLetivo', 'nome')
                            ->required(),
                        Forms\Components\Select::make('ano_catequetico_id')
                            ->label('Ano Catequético')
                            ->relationship('anoCatequetico', 'nome', fn (Builder $query) => $query->orderBy('ordem'))
                            ->required(),
                        Forms\Components\Select::make('publico_alvo')
                            ->label('Público Alvo')
                            ->options([
                                'criancas' => 'Crianças',
                                'pre_adolescentes' => 'Pré-adolescentes',
                                'adolescentes_jovens' => 'Adolescentes e Jovens',
                            ])
                            ->required(),
                        Forms\Components\Select::make('periodo')
                            ->label('Período')
                            ->options([
                                'manha' => 'Manhã',
                                'tarde' => 'Tarde',
                                'noite' => 'Noite',
                            ])
                            ->required(),
                        Forms\Components\TimePicker::make('hora_inicio')
                            ->label('Hora de Início')
                            ->seconds(false)
                            ->required(),
                        Forms\Components\TimePicker::make('hora_fim')
                            ->label('Hora de Fim')
                            ->seconds(false)
                            ->required()
                            ->after('hora_inicio'),
                        Forms\Components\Select::make('tipo')
                            ->label('Tipo')
                            ->options([
                                'normal' => 'Normal',
                                'intensiva' => 'Intensiva',
                            ])
                            ->required()
                            ->default('normal'),
                        Forms\Components\TextInput::make('vagas_minimo')
                            ->label('Nº Mínimo de Vagas')
                            ->numeric()
                            ->minValue(0),
                        Forms\Components\TextInput::make('vagas_maximo')
                            ->label('Nº Máximo de Vagas')
                            ->numeric()
                            ->minValue(0)
                            ->gte('vagas_minimo')
                            ->helperText('Atingir o limite não bloqueia sozinho — use "Bloquear Vagas" no separador Catequizandos quando quiser.'),
                        Forms\Components\Toggle::make('vagas_bloqueadas')
                            ->label('Vagas Bloqueadas')
                            ->helperText('Enquanto activo, não é possível adicionar mais catequizandos a esta turma.')
                            ->default(false),
                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'ativo' => 'Activa',
                                'inativo' => 'Inactiva',
                                'encerrada' => 'Encerrada',
                            ])
                            ->required()
                            ->default('ativo'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('centro.nome')
                    ->label('Centro')
                    ->sortable(),
                Tables\Columns\TextColumn::make('anoCatequetico.nome')
                    ->label('Ano Catequético')
                    ->sortable(),
                Tables\Columns\TextColumn::make('anoLetivo.nome')
                    ->label('Ano Lectivo')
                    ->sortable(),
                Tables\Columns\TextColumn::make('publico_alvo')
                    ->label('Público Alvo')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'criancas' => 'Crianças',
                        'pre_adolescentes' => 'Pré-adolescentes',
                        'adolescentes_jovens' => 'Adolescentes e Jovens',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('periodo')
                    ->label('Período')
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),
                Tables\Columns\TextColumn::make('hora_inicio')
                    ->label('Horário')
                    ->formatStateUsing(fn ($record) => $record->hora_inicio->format('H:i').' – '.$record->hora_fim->format('H:i')),
                Tables\Columns\TextColumn::make('vagas')
                    ->label('Vagas')
                    ->getStateUsing(function (Turma $record) {
                        $ocupadas = $record->vagasOcupadas();
                        $texto = $record->vagas_maximo ? "{$ocupadas} / {$record->vagas_maximo}" : (string) $ocupadas;

                        return $record->vagas_bloqueadas ? "{$texto} (Bloqueada)" : $texto;
                    })
                    ->color(fn (Turma $record) => $record->vagas_bloqueadas ? 'danger' : ($record->estaCheia() ? 'warning' : null)),
                // Mesmo padrao visual do Catequista/Catequizando (IconColumn booleano),
                // mesmo o status tendo um terceiro valor "encerrada" — aqui so
                // distingue "ativo" de tudo o resto, o filtro abaixo mostra o detalhe.
                Tables\Columns\IconColumn::make('status')
                    ->label('Activo')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->status === 'ativo'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'ativo' => 'Activa',
                        'inativo' => 'Inactiva',
                        'encerrada' => 'Encerrada',
                    ]),
                Tables\Filters\SelectFilter::make('ano_catequetico_id')
                    ->label('Ano Catequético')
                    ->options(fn () => AnoCatequetico::orderBy('ordem')->pluck('nome', 'id')),
                Tables\Filters\SelectFilter::make('centro_id')
                    ->label('Centro')
                    ->options(fn () => Centro::pluck('nome', 'id'))
                    ->visible(fn () => Auth::user()?->hasRole(self::GESTORES_CENTRO_LIVRE) ?? false),
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

    public static function getRelations(): array
    {
        return [
            SacramentosRelationManager::class,
            CatequistasRelationManager::class,
            CatequizandosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTurmas::route('/'),
            'create' => Pages\CreateTurma::route('/create'),
            'edit' => Pages\EditTurma::route('/{record}/edit'),
        ];
    }
}
