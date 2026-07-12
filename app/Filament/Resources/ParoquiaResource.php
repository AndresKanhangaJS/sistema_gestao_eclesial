<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ParoquiaResource\Pages;
use App\Models\Paroquia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ParoquiaResource extends Resource
{
    protected static ?string $model = Paroquia::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Estrutura';

    protected static ?string $modelLabel = 'Paróquia';

    protected static ?string $pluralModelLabel = 'Paróquias';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Paroquia')
                    ->columnSpanFull()
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Dados Gerais')
                            ->schema([
                                Forms\Components\TextInput::make('nome')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('diocese')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'ativo' => 'Ativo',
                                        'inativo' => 'Inativo',
                                    ])
                                    ->required()
                                    ->default('ativo'),
                            ]),
                        Forms\Components\Tabs\Tab::make('Contacto')
                            ->schema([
                                Forms\Components\TextInput::make('morada')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('responsavel')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email_contato')
                                    ->email()
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('telefone')
                                    ->tel()
                                    ->required()
                                    ->maxLength(255),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('diocese')
                    ->searchable(),
                Tables\Columns\TextColumn::make('telefone'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'ativo',
                        'danger' => 'inativo',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'ativo' => 'Ativo',
                        'inativo' => 'Inativo',
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
            'index' => Pages\ListParoquias::route('/'),
            'create' => Pages\CreateParoquia::route('/create'),
            'edit' => Pages\EditParoquia::route('/{record}/edit'),
        ];
    }
}
