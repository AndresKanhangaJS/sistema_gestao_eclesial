<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AnoCatequeticoResource\Pages;
use App\Models\AnoCatequetico;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Tabela partilhada entre todas as paroquias (programa oficial da
 * Arquidiocese) — sem paroquia_id/centro_id, sem scoping adicional em
 * getEloquentQuery(). AnoCatequeticoPolicy so permite create/update ao
 * admin_geral (via Gate::before); os restantes papeis da catequese so leem.
 */
class AnoCatequeticoResource extends Resource
{
    protected static ?string $model = AnoCatequetico::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'Catequese · Configuração';

    protected static ?string $modelLabel = 'Ano Catequético';

    protected static ?string $pluralModelLabel = 'Anos Catequéticos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('ordem')
                            ->label('Ordem')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        Forms\Components\TextInput::make('nome')
                            ->label('Nome')
                            ->helperText('Ex.: "1º Ano", "2º Ano"')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'ativo' => 'Activo',
                                'inativo' => 'Inactivo',
                            ])
                            ->required()
                            ->default('ativo'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ordem')
            ->columns([
                Tables\Columns\TextColumn::make('ordem')
                    ->label('Ordem')
                    ->sortable(),
                Tables\Columns\TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('status')
                    ->label('Estado')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->status === 'ativo'),
            ])
            ->filters([
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
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListAnoCatequeticos::route('/'),
            'create' => Pages\CreateAnoCatequetico::route('/create'),
            'edit' => Pages\EditAnoCatequetico::route('/{record}/edit'),
        ];
    }
}
