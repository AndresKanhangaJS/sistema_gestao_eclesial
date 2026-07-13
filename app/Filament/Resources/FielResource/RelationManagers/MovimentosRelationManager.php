<?php

namespace App\Filament\Resources\FielResource\RelationManagers;

use App\Enums\StatusConciliacao;
use App\Enums\TipoMovimento;
use App\Filament\Resources\MovimentoResource;
use App\Models\Movimento;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Extracto de movimentos do fiel (ex.: "o dizimo de Janeiro foi pago por
 * este fiel"). So leitura — o lancamento/edicao continua exclusivo do
 * MovimentoResource (validacoes, unique key, upload de comprovativo, etc.).
 */
class MovimentosRelationManager extends RelationManager
{
    protected static string $relationship = 'movimentos';

    protected static ?string $title = 'Movimentos';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('tipo')
                    ->label('Tipo')
                    ->formatStateUsing(fn (TipoMovimento $state) => match ($state) {
                        TipoMovimento::Dizimo => 'Dízimo',
                        TipoMovimento::Ofertorio => 'Ofertório',
                        TipoMovimento::Campanha => 'Outras Contribuições',
                        TipoMovimento::DespesaCentro => 'Despesa',
                    }),
                Tables\Columns\TextColumn::make('centro.nome')
                    ->label('Centro'),
                Tables\Columns\TextColumn::make('ano_competencia')
                    ->label('Ano')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('mes_competencia')
                    ->label('Mês')
                    ->formatStateUsing(fn (?int $state) => MovimentoResource::mesLabel($state))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('valor')
                    ->label('Valor')
                    ->money('AOA'),
                Tables\Columns\TextColumn::make('data_movimento')
                    ->label('Data')
                    ->date()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status_conciliacao')
                    ->label('Estado')
                    ->formatStateUsing(fn (StatusConciliacao $state) => match ($state) {
                        StatusConciliacao::Pendente => 'Pendente',
                        StatusConciliacao::Aprovado => 'Aprovado',
                        StatusConciliacao::Rejeitado => 'Rejeitado',
                    })
                    ->colors([
                        'warning' => StatusConciliacao::Pendente->value,
                        'success' => StatusConciliacao::Aprovado->value,
                        'danger' => StatusConciliacao::Rejeitado->value,
                    ]),
                Tables\Columns\IconColumn::make('tem_comprovativo')
                    ->label('Comprovativo')
                    ->boolean()
                    ->state(fn (Movimento $record) => filled($record->comprovativo_path)),
            ])
            ->defaultSort('data_movimento', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')
                    ->label('Tipo')
                    ->options([
                        TipoMovimento::Dizimo->value => 'Dízimo',
                        TipoMovimento::Ofertorio->value => 'Ofertório',
                        TipoMovimento::Campanha->value => 'Outras Contribuições',
                        TipoMovimento::DespesaCentro->value => 'Despesa de Centro',
                    ]),
            ])
            // Um fiel pode ter passado por varios centros ao longo do tempo; o
            // tesoureiro_centro so ve os movimentos lancados no seu proprio
            // centro, mesmo que o fiel tenha historico noutro.
            ->modifyQueryUsing(function (Builder $query) {
                $user = Auth::user();

                if ($user?->hasRole('tesoureiro_centro')) {
                    $query->where('centro_id', $user->centro_id);
                }

                return $query;
            })
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('verComprovativo')
                    ->label('Ver comprovativo')
                    ->icon('heroicon-o-paper-clip')
                    ->visible(fn (Movimento $record) => filled($record->comprovativo_path)
                        && (config('filesystems.default') !== 's3' || filled(config('filesystems.disks.s3.bucket'))))
                    ->url(function (Movimento $record) {
                        $disk = config('filesystems.default');

                        try {
                            return $disk === 's3'
                                ? Storage::disk($disk)->temporaryUrl($record->comprovativo_path, now()->addMinutes(60))
                                : Storage::disk($disk)->url($record->comprovativo_path);
                        } catch (\Throwable $e) {
                            return null;
                        }
                    }, shouldOpenInNewTab: true),
            ])
            ->bulkActions([]);
    }
}
