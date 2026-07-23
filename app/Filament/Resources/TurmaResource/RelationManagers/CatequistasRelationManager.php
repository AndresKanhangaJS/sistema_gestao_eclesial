<?php

namespace App\Filament\Resources\TurmaResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Catequistas da turma (pivot turma_catequista), com papel titular/auxiliar.
 * Espelha CatequistaResource/RelationManagers/TurmasRelationManager, do lado
 * inverso.
 */
class CatequistasRelationManager extends RelationManager
{
    protected static string $relationship = 'catequistas';

    // Catequista::turmas() e o inverso real (BelongsToMany, ver Catequista model).
    protected static ?string $inverseRelationship = 'turmas';

    protected static ?string $title = 'Catequistas';

    protected static ?string $recordTitleAttribute = 'nome_completo';

    private static function podeGerir(): bool
    {
        return Auth::user()?->hasRole([
            'admin_geral',
            'coordenador_catequese_paroquia',
            'coordenador_catequese_centro',
        ]) ?? false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome_completo')
                    ->label('Nome'),
                Tables\Columns\TextColumn::make('telefone')
                    ->placeholder('—'),
                Tables\Columns\BadgeColumn::make('pivot.papel')
                    ->label('Papel')
                    ->formatStateUsing(fn (string $state) => $state === 'titular' ? 'Titular' : 'Auxiliar')
                    ->colors([
                        'success' => 'titular',
                        'gray' => 'auxiliar',
                    ]),
                Tables\Columns\TextColumn::make('pivot.data_inicio')
                    ->label('Início')
                    ->date(),
                Tables\Columns\TextColumn::make('pivot.data_fim')
                    ->label('Fim')
                    ->date()
                    ->placeholder('Activo'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->visible(fn () => self::podeGerir())
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\Select::make('papel')
                            ->label('Papel')
                            ->options([
                                'titular' => 'Titular',
                                'auxiliar' => 'Auxiliar',
                            ])
                            ->default('titular')
                            ->required(),
                        Forms\Components\DatePicker::make('data_inicio')
                            ->label('Data de Início')
                            ->required()
                            ->default(now()),
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('encerrarVinculo')
                    ->label('Encerrar')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn () => self::podeGerir())
                    ->form([
                        Forms\Components\DatePicker::make('data_fim')
                            ->label('Data de Fim')
                            ->required()
                            ->default(now()),
                    ])
                    ->action(fn (array $data, $record) => $record->pivot->update($data)),
                Tables\Actions\DetachAction::make()
                    ->visible(fn () => self::podeGerir()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make()
                        ->visible(fn () => self::podeGerir()),
                ]),
            ]);
    }
}
