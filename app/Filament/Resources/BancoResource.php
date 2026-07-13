<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BancoResource\Pages;
use App\Models\Banco;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class BancoResource extends Resource
{
    protected static ?string $model = Banco::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationGroup = 'Financeiro';

    protected static ?string $modelLabel = 'Banco';

    protected static ?string $pluralModelLabel = 'Bancos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // paroquia_id nao esta no formulario: o ForcaParoquiaUtilizadorObserver
                // fixa-o sempre a partir do utilizador autenticado (excepto admin_geral).
                Forms\Components\Select::make('paroquia_id')
                    ->label('Paróquia')
                    ->relationship('paroquia', 'nome')
                    ->required()
                    ->visible(fn () => Auth::user()?->hasRole('admin_geral') ?? false)
                    ->default(fn () => Auth::user()?->paroquia_id),
                Forms\Components\TextInput::make('nome_banco')
                    ->label('Nome do Banco')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('sigla')
                    ->label('Sigla')
                    ->maxLength(255),
                Forms\Components\TextInput::make('numero_conta')
                    ->label('Número de Conta')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('iban')
                    ->label('IBAN')
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
                Tables\Columns\TextColumn::make('nome_banco')
                    ->label('Banco')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sigla')
                    ->label('Sigla'),
                Tables\Columns\TextColumn::make('numero_conta')
                    ->label('Número de Conta')
                    ->searchable(),
                Tables\Columns\TextColumn::make('iban')
                    ->label('IBAN')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                Tables\Filters\TrashedFilter::make(),
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
            'index' => Pages\ListBancos::route('/'),
            'create' => Pages\CreateBanco::route('/create'),
            'edit' => Pages\EditBanco::route('/{record}/edit'),
        ];
    }
}
