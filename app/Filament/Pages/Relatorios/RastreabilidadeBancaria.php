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
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class RastreabilidadeBancaria extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Rastreabilidade Bancária';

    protected static ?string $title = 'Relatório — Rastreabilidade Bancária por Conta';

    protected static string $view = 'filament.pages.relatorios.rastreabilidade-bancaria';

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(['admin_geral', 'tesoureiro_paroquial', 'consultor']) ?? false;
    }

    protected function getTableQuery(): Builder
    {
        return Movimento::query()->whereNotNull('banco_id');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('banco.sigla')->label('Banco'),
            Tables\Columns\TextColumn::make('banco.numero_conta')->label('Nº Conta'),
            Tables\Columns\TextColumn::make('centro.nome')->label('Centro'),
            Tables\Columns\TextColumn::make('data_movimento')->date()->sortable(),
            Tables\Columns\TextColumn::make('tipo'),
            Tables\Columns\TextColumn::make('numero_referencia_bancaria')->label('Referência'),
            Tables\Columns\TextColumn::make('valor')->money('AOA')->sortable(),
            Tables\Columns\TextColumn::make('status_conciliacao')->label('Estado'),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            Tables\Filters\SelectFilter::make('banco_id')
                ->label('Banco')
                ->relationship('banco', 'nome_banco'),
        ];
    }

    protected function getTableHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exports([
                    ExcelExport::make('rastreabilidade-bancaria')->fromTable(),
                ]),
        ];
    }
}
