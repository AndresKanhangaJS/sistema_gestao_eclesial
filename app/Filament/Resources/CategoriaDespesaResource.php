<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoriaDespesaResource\Pages;
use App\Models\CategoriaDespesa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class CategoriaDespesaResource extends Resource
{
    protected static ?string $model = CategoriaDespesa::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Financeiro';

    protected static ?string $modelLabel = 'Categoria de Despesa';

    protected static ?string $pluralModelLabel = 'Categorias de Despesa';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('paroquia_id')
                    ->default(fn () => Auth::user()?->paroquia_id),
                Forms\Components\TextInput::make('nome')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('descricao')
                    ->columnSpanFull(),
                Forms\Components\Select::make('status')
                    ->options([
                        'ativo' => 'Ativo',
                        'inativo' => 'Inativo',
                    ])
                    ->required()
                    ->default('ativo'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('descricao')
                    ->limit(50),
                Tables\Columns\TextColumn::make('movimentos_count')
                    ->label('Despesas lançadas')
                    ->counts('movimentos'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'ativo',
                        'danger' => 'inativo',
                    ]),
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
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListCategoriaDespesas::route('/'),
            'create' => Pages\CreateCategoriaDespesa::route('/create'),
            'edit' => Pages\EditCategoriaDespesa::route('/{record}/edit'),
        ];
    }
}
