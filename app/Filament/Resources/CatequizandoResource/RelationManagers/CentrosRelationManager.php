<?php

namespace App\Filament\Resources\CatequizandoResource\RelationManagers;

use App\Models\Centro;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Historico de centros do catequizando (catequizando_centros), mesmo molde
 * do CentrosRelationManager do Fiel.
 *
 * Sem AttachAction aqui (ao contrario do Fiel): o Centro model nao tem uma
 * relacao belongsToMany de volta para Catequizando (so tem catequizandos()
 * como HasMany directo por centro_id) — a adivinhacao automatica do
 * inverseRelationship do Filament cairia nesse metodo e produziria um
 * whereDoesntHave semanticamente errado (excluiria centros com QUALQUER
 * catequizando, nao so os ja vinculados a este). Como o vinculo inicial ja e
 * criado automaticamente em CreateCatequizando::afterCreate(), a unica accao
 * de escrita necessaria aqui e a transferencia dedicada.
 */
class CentrosRelationManager extends RelationManager
{
    protected static string $relationship = 'centros';

    protected static ?string $title = 'Centros';

    protected static ?string $recordTitleAttribute = 'nome';

    private static function podeGerir(): bool
    {
        return Auth::user()?->hasRole([
            'admin_geral',
            'coordenador_catequese_paroquia',
            'coordenador_catequese_centro',
            'secretario_catequese',
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
                Tables\Columns\TextColumn::make('nome')
                    ->label('Centro'),
                Tables\Columns\TextColumn::make('pivot.data_inicio')
                    ->label('Início')
                    ->date(),
                Tables\Columns\TextColumn::make('pivot.data_fim')
                    ->label('Fim')
                    ->date()
                    ->placeholder('Activo'),
                Tables\Columns\TextColumn::make('pivot.motivo_transferencia')
                    ->label('Motivo da Transferência')
                    ->limit(30),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('transferir')
                    ->label('Transferir de Centro')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn ($record) => self::podeGerir() && $record->pivot->data_fim === null)
                    ->form(function ($record) {
                        // O novo centro tem de pertencer a mesma paroquia do
                        // catequizando (fixa desde a criacao) — independente
                        // do papel de quem transfere, incl. admin_geral, que
                        // nao tem ParoquiaScope aplicado e por isso via aqui
                        // todos os centros sem este filtro explicito.
                        $paroquiaId = $this->getOwnerRecord()->paroquia_id;

                        return [
                            Forms\Components\Select::make('novo_centro_id')
                                ->label('Novo centro')
                                ->options(
                                    fn () => Centro::withoutGlobalScopes()
                                        ->where('paroquia_id', $paroquiaId)
                                        ->where('id', '!=', $record->id)
                                        ->pluck('nome', 'id')
                                )
                                ->required(),
                            Forms\Components\DatePicker::make('data_transferencia')
                                ->label('Data da Transferência')
                                ->required()
                                ->default(now()),
                            Forms\Components\Textarea::make('motivo')
                                ->label('Motivo da transferência')
                                ->required(),
                        ];
                    })
                    ->action(function (array $data, $record): void {
                        $catequizando = $this->getOwnerRecord();

                        $novoCentro = Centro::withoutGlobalScopes()->find($data['novo_centro_id']);

                        if (! $novoCentro || $novoCentro->paroquia_id !== $catequizando->paroquia_id) {
                            abort(403, 'O novo centro tem de pertencer à mesma paróquia do catequizando.');
                        }

                        $centroAntigoId = $record->id;

                        // Fecha a linha activa e abre uma nova no historico de centros
                        $record->pivot->update(['data_fim' => $data['data_transferencia']]);

                        $catequizando->centros()->attach($data['novo_centro_id'], [
                            'data_inicio' => $data['data_transferencia'],
                            'motivo_transferencia' => $data['motivo'],
                        ]);

                        // catequizandos.centro_id denormalizado — actualiza para reflectir o centro corrente
                        $catequizando->update(['centro_id' => $novoCentro->id]);

                        // Mudanca de centro implica sempre mudanca de turma (secc. 7.1): fecha
                        // qualquer colocacao activa em turmas do centro antigo e actualiza o
                        // centro_id das inscricoes afectadas — a colocacao na turma do novo
                        // centro fica para uma accao dedicada em InscricaoTurmaRelationManager.
                        $catequizando->inscricoes()
                            ->whereHas('turmaAtiva', fn ($q) => $q->whereHas('turma', fn ($q2) => $q2->where('centro_id', $centroAntigoId)))
                            ->get()
                            ->each(function ($inscricao) use ($data, $novoCentro) {
                                $inscricao->turmaAtiva?->update([
                                    'status' => 'transferido',
                                    'data_fim' => $data['data_transferencia'],
                                    'motivo' => 'Mudança de centro do catequizando: '.$data['motivo'],
                                ]);

                                $inscricao->update(['centro_id' => $novoCentro->id]);
                            });
                    }),
                Tables\Actions\Action::make('editarVinculo')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil')
                    ->visible(fn () => self::podeGerir())
                    ->form([
                        Forms\Components\DatePicker::make('data_fim')
                            ->label('Data de Fim'),
                        Forms\Components\Textarea::make('motivo_transferencia')
                            ->label('Motivo da Transferência'),
                    ])
                    ->fillForm(fn ($record): array => [
                        'data_fim' => $record->pivot->data_fim,
                        'motivo_transferencia' => $record->pivot->motivo_transferencia,
                    ])
                    ->action(fn (array $data, $record) => $record->pivot->update($data)),
            ])
            ->bulkActions([]);
    }
}
