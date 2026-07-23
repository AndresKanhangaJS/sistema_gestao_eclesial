<?php

namespace App\Filament\Resources\TurmaResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Pivot simples turma_sacramento (sem colunas extra) — permite combinar uma
 * turma com 1+ sacramentos ("1º Baptismo e Comunhão", etc.).
 */
class SacramentosRelationManager extends RelationManager
{
    protected static string $relationship = 'sacramentos';

    // Sacramento::turmas() e o inverso real (BelongsToMany, ver Sacramento model).
    protected static ?string $inverseRelationship = 'turmas';

    protected static ?string $title = 'Sacramentos';

    protected static ?string $recordTitleAttribute = 'nome';

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
                Tables\Columns\TextColumn::make('ordem')
                    ->label('Ordem'),
                Tables\Columns\TextColumn::make('nome')
                    ->label('Sacramento'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Adicionar Sacramento(s)')
                    ->visible(fn () => self::podeGerir())
                    ->preloadRecordSelect()
                    ->multiple(),
            ])
            ->actions([
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
