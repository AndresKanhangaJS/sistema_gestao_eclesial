<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AnoLetivoResource\Pages;
use App\Models\AnoLetivo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AnoLetivoResource extends Resource
{
    protected static ?string $model = AnoLetivo::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Catequese';

    protected static ?string $modelLabel = 'Ano Lectivo';

    protected static ?string $pluralModelLabel = 'Anos Lectivos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Select::make('paroquia_id')
                            ->label('Paróquia')
                            ->relationship('paroquia', 'nome')
                            ->required()
                            // So admin_geral escolhe a paroquia; coordenador_catequese_paroquia
                            // (unico outro papel que cria anos lectivos) fica preso a sua propria.
                            ->visible(fn () => Auth::user()?->hasRole('admin_geral') ?? false)
                            ->default(fn () => Auth::user()?->paroquia_id),
                        Forms\Components\TextInput::make('nome')
                            ->label('Nome')
                            ->helperText('Ex.: 2026/2027')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('data_inicio')
                            ->label('Data de Início')
                            ->required(),
                        Forms\Components\DatePicker::make('data_fim')
                            ->label('Data de Fim')
                            ->required()
                            ->afterOrEqual('data_inicio'),
                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'em_curso' => 'Em Curso',
                                'encerrado' => 'Encerrado',
                            ])
                            ->required()
                            ->default('em_curso')
                            ->helperText('Deve existir apenas um ano lectivo "Em Curso" por paróquia.')
                            ->rule(function (Get $get, ?Model $record) {
                                return function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                    if ($value !== 'em_curso') {
                                        return;
                                    }

                                    $paroquiaId = $get('paroquia_id') ?? Auth::user()?->paroquia_id;

                                    $query = AnoLetivo::withoutGlobalScopes()
                                        ->where('paroquia_id', $paroquiaId)
                                        ->where('status', 'em_curso');

                                    if ($record) {
                                        $query->whereKeyNot($record->getKey());
                                    }

                                    if ($query->exists()) {
                                        $fail('Já existe um ano lectivo "Em Curso" para esta paróquia.');
                                    }
                                };
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('data_inicio', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('paroquia.nome')
                    ->label('Paróquia')
                    ->searchable()
                    ->sortable()
                    ->visible(fn () => Auth::user()?->hasRole('admin_geral') ?? false),
                Tables\Columns\TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('data_inicio')
                    ->label('Início')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('data_fim')
                    ->label('Fim')
                    ->date()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'em_curso' => 'Em Curso',
                        'encerrado' => 'Encerrado',
                        default => $state,
                    })
                    ->colors([
                        'success' => 'em_curso',
                        'gray' => 'encerrado',
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'em_curso' => 'Em Curso',
                        'encerrado' => 'Encerrado',
                    ]),
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
        // AnoLetivo nao tem centro_id (decisao ao nivel da paroquia, nao do
        // centro — docs/modulos/catequese.md seccao 3); a ParoquiaScope do
        // model ja e suficiente, sem reforco adicional por centro.
        return parent::getEloquentQuery();
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
            'index' => Pages\ListAnoLetivos::route('/'),
            'create' => Pages\CreateAnoLetivo::route('/create'),
            'edit' => Pages\EditAnoLetivo::route('/{record}/edit'),
        ];
    }
}
