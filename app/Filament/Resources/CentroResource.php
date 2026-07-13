<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CentroResource\Pages;
use App\Models\Centro;
use App\Models\Paroquia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CentroResource extends Resource
{
    protected static ?string $model = Centro::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Estrutura';

    protected static ?string $modelLabel = 'Centro';

    protected static ?string $pluralModelLabel = 'Centros';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('paroquia_id')
                    ->label('Paróquia')
                    ->relationship('paroquia', 'nome')
                    ->required()
                    // So admin_geral escolhe a paroquia; as outras roles ficam
                    // automaticamente presas a paroquia do proprio utilizador.
                    ->visible(fn () => Auth::user()?->hasRole('admin_geral') ?? false)
                    ->default(fn () => Auth::user()?->paroquia_id),
                Forms\Components\TextInput::make('nome')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('localizacao')
                    ->label('Localização')
                    ->maxLength(255),
                Forms\Components\TextInput::make('responsavel_local')
                    ->label('Responsável Local')
                    ->maxLength(255),
                Forms\Components\Select::make('status')
                    ->label('Estado')
                    ->options([
                        'ativo' => 'Activo',
                        'inativo' => 'Inactivo',
                    ])
                    ->required()
                    ->default('ativo'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('paroquia.nome')
                    ->label('Paróquia')
                    ->searchable()
                    ->sortable()
                    ->visible(fn () => Auth::user()?->hasRole('admin_geral') ?? false),
                Tables\Columns\TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('localizacao')
                    ->label('Localização')
                    ->searchable(),
                Tables\Columns\TextColumn::make('responsavel_local')
                    ->label('Responsável Local')
                    ->searchable(),
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
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'ativo' => 'Activo',
                        'inativo' => 'Inactivo',
                    ]),
                Tables\Filters\SelectFilter::make('paroquia_id')
                    ->label('Paróquia')
                    ->options(fn () => Paroquia::pluck('nome', 'id'))
                    ->visible(fn () => Auth::user()?->hasRole('admin_geral') ?? false),
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

        // A ParoquiaScope ja limita a paroquia; aqui reforcamos para 1 centro
        // especifico quando o papel for tesoureiro_centro.
        if ($user && $user->hasRole('tesoureiro_centro')) {
            $query->where('id', $user->centro_id);
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
            'index' => Pages\ListCentros::route('/'),
            'create' => Pages\CreateCentro::route('/create'),
            'edit' => Pages\EditCentro::route('/{record}/edit'),
        ];
    }
}
