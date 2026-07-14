<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FielResource\Pages;
use App\Filament\Resources\FielResource\RelationManagers\CentrosRelationManager;
use App\Filament\Resources\FielResource\RelationManagers\MovimentosRelationManager;
use App\Models\Fiel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class FielResource extends Resource
{
    protected static ?string $model = Fiel::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Fiéis';

    protected static ?string $modelLabel = 'Fiel';

    protected static ?string $pluralModelLabel = 'Fiéis';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Fiel')
                    ->columnSpanFull()
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Dados Pessoais')
                            ->schema([
                                Forms\Components\Section::make()
                                    ->schema([
                                        Forms\Components\Select::make('paroquia_id')
                                            ->label('Paróquia')
                                            ->relationship('paroquia', 'nome')
                                            ->required()
                                            ->visible(fn () => Auth::user()?->hasRole('admin_geral') ?? false)
                                            ->default(fn () => Auth::user()?->paroquia_id),
                                        Forms\Components\TextInput::make('nome')
                                            ->required()
                                            ->maxLength(255),
                                        // Gerado automaticamente (Fiel::creating()) para nao
                                        // haver choques de codigos duplicados — nunca editavel
                                        // a mao. So aparece (desactivado) depois de criado.
                                        Forms\Components\TextInput::make('codigo_dizimista')
                                            ->label('Código de Dizimista')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->visible(fn (string $operation) => $operation === 'edit')
                                            ->helperText('Gerado automaticamente.'),
                                        Forms\Components\DatePicker::make('data_nascimento')
                                            ->label('Data de Nascimento'),
                                        Forms\Components\Select::make('status')
                                            ->label('Estado')
                                            ->options([
                                                'ativo' => 'Activo',
                                                'inativo' => 'Inactivo',
                                            ])
                                            ->required()
                                            ->default('ativo'),
                                    ]),
                            ]),
                        Forms\Components\Tabs\Tab::make('Contacto')
                            ->schema([
                                Forms\Components\Section::make()
                                    ->schema([
                                        Forms\Components\TextInput::make('telefone')
                                            ->tel()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('email')
                                            ->email()
                                            ->maxLength(255),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nome')
            ->columns([
                Tables\Columns\TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('codigo_dizimista')
                    ->label('Código')
                    ->searchable(),
                Tables\Columns\TextColumn::make('telefone'),
                Tables\Columns\TextColumn::make('centro_atual')
                    ->label('Centro actual')
                    ->state(function (Fiel $record): string {
                        $centro = $record->centros()->wherePivotNull('data_fim')->first();

                        return $centro?->nome ?? 'Não vinculado';
                    }),
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

        // A ParoquiaScope ja limita a paroquia; para tesoureiro_centro reforcamos
        // para so mostrar fieis com vinculo activo ao seu proprio centro.
        if ($user && $user->hasRole('tesoureiro_centro')) {
            $query->whereHas(
                'centros',
                fn (Builder $q) => $q->where('centros.id', $user->centro_id)->whereNull('fiel_centros.data_fim')
            );
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            CentrosRelationManager::class,
            MovimentosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFiels::route('/'),
            'create' => Pages\CreateFiel::route('/create'),
            'edit' => Pages\EditFiel::route('/{record}/edit'),
        ];
    }
}
