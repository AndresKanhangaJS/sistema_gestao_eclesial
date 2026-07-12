<?php

namespace App\Filament\Pages\Relatorios;

use App\Models\Movimento;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

/**
 * Log de Auditoria do Sistema — so PDF, so admin_geral (CLAUDE.md/spec 05).
 * Cobre as alteracoes a Movimento (LogsActivity, Modulo 7).
 */
class LogAuditoria extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Log de Auditoria';

    protected static ?string $title = 'Relatório — Log de Auditoria do Sistema';

    protected static string $view = 'filament.pages.relatorios.log-auditoria';

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole('admin_geral') ?? false;
    }

    protected function getTableQuery(): Builder
    {
        return Activity::query()->where('subject_type', Movimento::class)->latest();
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('created_at')->label('Data/Hora')->dateTime(),
            Tables\Columns\TextColumn::make('causer.name')->label('Utilizador')->default('—'),
            Tables\Columns\TextColumn::make('description')->label('Evento'),
            Tables\Columns\TextColumn::make('subject_id')->label('Movimento #'),
            Tables\Columns\TextColumn::make('changes')
                ->label('Alterações')
                ->formatStateUsing(fn (Activity $record) => json_encode($record->changes()->get('attributes', []))),
        ];
    }
}
