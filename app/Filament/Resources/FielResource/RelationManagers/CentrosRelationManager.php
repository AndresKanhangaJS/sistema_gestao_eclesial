<?php

namespace App\Filament\Resources\FielResource\RelationManagers;

use App\Models\Centro;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Gere o vinculo fiel <-> centro (tabela pivot fiel_centros), incluindo
 * o historico de transferencias entre centros.
 */
class CentrosRelationManager extends RelationManager
{
    protected static string $relationship = 'centros';

    protected static ?string $title = 'Vínculos a Centros';

    protected static ?string $recordTitleAttribute = 'nome';

    private static function podeEscrever(): bool
    {
        return Auth::user()?->hasRole(['admin_geral', 'tesoureiro_paroquial']) ?? false;
    }

    public function form(Form $form): Form
    {
        // Nao usamos CreateAction/EditAction sobre o Centro em si aqui, so
        // acoes customizadas sobre o pivot (attach/transferir/editar vinculo).
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome')
                    ->label('Centro'),
                Tables\Columns\TextColumn::make('pivot.data_inicio')
                    ->label('Início')
                    ->date(),
                Tables\Columns\TextColumn::make('pivot.data_fim')
                    ->label('Fim')
                    ->date()
                    ->placeholder('Activo'),
                Tables\Columns\IconColumn::make('pivot.principal')
                    ->label('Principal')
                    ->boolean(),
                Tables\Columns\TextColumn::make('pivot.motivo_transferencia')
                    ->label('Motivo transferência')
                    ->limit(30),
            ])
            ->headerActions([
                AttachAction::make()
                    ->visible(fn () => self::podeEscrever())
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\DatePicker::make('data_inicio')
                            ->required()
                            ->default(now()),
                        Forms\Components\Toggle::make('principal')
                            ->default(false),
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('transferir')
                    ->label('Transferir')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn ($record) => self::podeEscrever() && $record->pivot->data_fim === null)
                    ->form([
                        Forms\Components\Select::make('novo_centro_id')
                            ->label('Novo centro')
                            ->options(
                                fn ($record) => Centro::where('id', '!=', $record->id)->pluck('nome', 'id')
                            )
                            ->required(),
                        Forms\Components\DatePicker::make('data_transferencia')
                            ->required()
                            ->default(now()),
                        Forms\Components\Textarea::make('motivo')
                            ->label('Motivo da transferência')
                            ->required(),
                    ])
                    ->action(function (array $data, $record): void {
                        $fiel = $this->getOwnerRecord();

                        $record->pivot->update(['data_fim' => $data['data_transferencia']]);

                        $fiel->centros()->attach($data['novo_centro_id'], [
                            'data_inicio' => $data['data_transferencia'],
                            'principal' => $record->pivot->principal,
                            'motivo_transferencia' => $data['motivo'],
                        ]);
                    }),
                Tables\Actions\Action::make('editarVinculo')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil')
                    ->visible(fn () => self::podeEscrever())
                    ->form([
                        Forms\Components\DatePicker::make('data_fim'),
                        Forms\Components\Toggle::make('principal'),
                        Forms\Components\Textarea::make('motivo_transferencia')
                            ->label('Motivo transferência'),
                    ])
                    ->fillForm(fn ($record): array => [
                        'data_fim' => $record->pivot->data_fim,
                        'principal' => (bool) $record->pivot->principal,
                        'motivo_transferencia' => $record->pivot->motivo_transferencia,
                    ])
                    ->action(fn (array $data, $record) => $record->pivot->update($data)),
                Tables\Actions\DetachAction::make()
                    ->visible(fn () => self::podeEscrever()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make()
                        ->visible(fn () => self::podeEscrever()),
                ]),
            ]);
    }
}
