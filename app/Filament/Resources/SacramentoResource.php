<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SacramentoResource\Pages;
use App\Models\Sacramento;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Tabela partilhada entre todas as paroquias, mesmo espirito de
 * AnoCatequeticoResource — sem paroquia_id/centro_id, sem scoping adicional.
 */
class SacramentoResource extends Resource
{
    protected static ?string $model = Sacramento::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Catequese · Configuração';

    protected static ?string $modelLabel = 'Sacramento';

    protected static ?string $pluralModelLabel = 'Sacramentos';

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
                            ->helperText('Ex.: Baptismo, Comunhão, Crisma')
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
            'index' => Pages\ListSacramentos::route('/'),
            'create' => Pages\CreateSacramento::route('/create'),
            'edit' => Pages\EditSacramento::route('/{record}/edit'),
        ];
    }
}
