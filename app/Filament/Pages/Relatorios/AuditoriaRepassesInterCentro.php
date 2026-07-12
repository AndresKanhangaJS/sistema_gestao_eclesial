<?php

namespace App\Filament\Pages\Relatorios;

use App\Models\FielCentro;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

/**
 * "Repasses Inter-Centro": nao ha transferencia financeira entre centros no
 * schema (cada movimento pertence a 1 so centro) — reaproveita o historico
 * fiel_centros (Modulo 3), que e a unica movimentacao inter-centro real.
 * So mostra linhas com motivo_transferencia preenchido (transferencias de
 * facto, nao o vinculo inicial de um fiel a um centro).
 */
class AuditoriaRepassesInterCentro extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Repasses Inter-Centro';

    protected static ?string $title = 'Relatório — Auditoria de Repasses Inter-Centro';

    protected static string $view = 'filament.pages.relatorios.auditoria-repasses-inter-centro';

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(['admin_geral', 'tesoureiro_paroquial', 'consultor']) ?? false;
    }

    protected function getTableQuery(): Builder
    {
        $user = Auth::user();

        $query = FielCentro::withoutGlobalScopes()
            ->whereNotNull('motivo_transferencia')
            ->with(['fiel', 'centro']);

        if ($user->hasRole('tesoureiro_paroquial')) {
            $query->whereHas('fiel', fn (Builder $q) => $q->where('paroquia_id', $user->paroquia_id));
        }

        return $query;
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('fiel.nome')->label('Fiel'),
            Tables\Columns\TextColumn::make('centro_origem')
                ->label('Centro origem')
                ->getStateUsing(fn (FielCentro $record) => static::centroOrigem($record)),
            Tables\Columns\TextColumn::make('centro.nome')->label('Centro destino'),
            Tables\Columns\TextColumn::make('data_inicio')->label('Data da transferência')->date(),
            Tables\Columns\TextColumn::make('motivo_transferencia')->label('Motivo'),
        ];
    }

    protected function getTableHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exports([
                    ExcelExport::make('repasses-inter-centro')->fromTable(),
                ]),
        ];
    }

    public static function centroOrigem(FielCentro $record): string
    {
        $anterior = FielCentro::withoutGlobalScopes()
            ->where('fiel_id', $record->fiel_id)
            ->where('centro_id', '!=', $record->centro_id)
            ->whereDate('data_fim', $record->data_inicio)
            ->with('centro')
            ->first();

        return $anterior?->centro?->nome ?? '—';
    }
}
