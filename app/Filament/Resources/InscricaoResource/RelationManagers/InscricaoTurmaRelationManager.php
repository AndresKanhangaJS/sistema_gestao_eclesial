<?php

namespace App\Filament\Resources\InscricaoResource\RelationManagers;

use App\Enums\EstadoInscricaoTurma;
use App\Models\Turma;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Historico de colocacao em turma (inscricao_turma). Trocar de turma NUNCA
 * edita inscricoes nem turmas — fecha a linha activa (status=transferido) e
 * cria uma nova (status=ativo), sempre via a accao dedicada abaixo, nunca
 * por edicao livre da tabela (docs/modulos/catequese.md seccao 7.1).
 */
class InscricaoTurmaRelationManager extends RelationManager
{
    protected static string $relationship = 'turmas';

    // Turma::inscricoes() e o inverso real (BelongsToMany, ver Turma model).
    protected static ?string $inverseRelationship = 'inscricoes';

    protected static ?string $title = 'Colocação em Turma';

    protected static ?string $recordTitleAttribute = 'id';

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
                Tables\Columns\TextColumn::make('anoCatequetico.nome')
                    ->label('Ano Catequético'),
                Tables\Columns\TextColumn::make('periodo')
                    ->label('Período')
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),
                Tables\Columns\BadgeColumn::make('pivot.status')
                    ->label('Estado')
                    ->formatStateUsing(fn (EstadoInscricaoTurma $state) => match ($state) {
                        EstadoInscricaoTurma::Ativo => 'Activo',
                        EstadoInscricaoTurma::Transferido => 'Transferido',
                        EstadoInscricaoTurma::Removido => 'Removido',
                    })
                    ->colors([
                        'success' => EstadoInscricaoTurma::Ativo->value,
                        'gray' => EstadoInscricaoTurma::Transferido->value,
                        'danger' => EstadoInscricaoTurma::Removido->value,
                    ]),
                Tables\Columns\TextColumn::make('pivot.data_inicio')
                    ->label('Início')
                    ->date(),
                Tables\Columns\TextColumn::make('pivot.data_fim')
                    ->label('Fim')
                    ->date()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('pivot.motivo')
                    ->label('Motivo')
                    ->limit(30)
                    ->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('colocarOuTrocarTurma')
                    ->label(fn () => $this->getOwnerRecord()->turmaAtiva ? 'Trocar de Turma' : 'Colocar em Turma')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn () => self::podeGerir())
                    ->form(function () {
                        $inscricao = $this->getOwnerRecord();
                        $activa = $inscricao->turmaAtiva;
                        $sacramentoIds = $inscricao->sacramentos()->pluck('sacramentos.id')->sort()->values()->all();

                        return [
                            Forms\Components\Select::make('turma_id')
                                ->label('Turma')
                                ->options(
                                    fn () => Turma::withoutGlobalScopes()
                                        ->where('centro_id', $inscricao->centro_id)
                                        ->where('ano_letivo_id', $inscricao->ano_letivo_id)
                                        ->where('ano_catequetico_id', $inscricao->ano_catequetico_id)
                                        ->where('status', 'ativo')
                                        ->when($activa, fn ($q) => $q->where('id', '!=', $activa->turma_id))
                                        ->get()
                                        // O conjunto de sacramentos tem de bater certo por
                                        // completo com o da inscricao — nao so parcialmente
                                        // (docs/modulos/catequese.md secc. 12).
                                        ->filter(fn (Turma $turma) => $turma->sacramentos()->pluck('sacramentos.id')->sort()->values()->all() === $sacramentoIds)
                                        ->mapWithKeys(fn (Turma $turma) => [
                                            $turma->id => "{$turma->anoCatequetico?->nome} — {$turma->periodo} ({$turma->hora_inicio->format('H:i')}–{$turma->hora_fim->format('H:i')})",
                                        ])
                                )
                                ->helperText(blank($inscricao->ano_catequetico_id) || $inscricao->sacramentos()->count() === 0
                                    ? 'Preencha o Ano de Catequese e o(s) Sacramento(s) na ficha de inscrição antes de colocar em turma.'
                                    : null)
                                ->required(),
                            Forms\Components\DatePicker::make('data_movimento')
                                ->label('Data')
                                ->required()
                                ->default(now()),
                            Forms\Components\Textarea::make('motivo')
                                ->label($activa ? 'Motivo da troca' : 'Observação')
                                ->required((bool) $activa),
                        ];
                    })
                    ->action(function (array $data): void {
                        $inscricao = $this->getOwnerRecord();
                        $activa = $inscricao->turmaAtiva;

                        if ($activa) {
                            $activa->update([
                                'status' => EstadoInscricaoTurma::Transferido->value,
                                'data_fim' => $data['data_movimento'],
                                'motivo' => $data['motivo'] ?? null,
                            ]);
                        }

                        $inscricao->turmas()->attach($data['turma_id'], [
                            'status' => EstadoInscricaoTurma::Ativo->value,
                            'data_inicio' => $data['data_movimento'],
                            'motivo' => $data['motivo'] ?? null,
                        ]);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('removerDaTurma')
                    ->label('Remover')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => self::podeGerir() && $record->pivot->status === EstadoInscricaoTurma::Ativo)
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('motivo')
                            ->label('Motivo da remoção')
                            ->required(),
                    ])
                    ->action(fn (array $data, $record) => $record->pivot->update([
                        'status' => EstadoInscricaoTurma::Removido->value,
                        'data_fim' => now(),
                        'motivo' => $data['motivo'],
                    ])),
                // Reactivar em vez de obrigar a passar outra vez por "Colocar em
                // Turma" — cria uma nova linha activa (preserva o historico da
                // linha "removido"), fechando primeiro qualquer outra colocacao
                // activa entretanto feita noutra turma.
                Tables\Actions\Action::make('reactivar')
                    ->label('Reactivar')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->visible(fn ($record) => self::podeGerir() && $record->pivot->status === EstadoInscricaoTurma::Removido)
                    ->disabled(fn ($record) => $record->vagas_bloqueadas)
                    ->tooltip(fn ($record) => $record->vagas_bloqueadas ? 'Vagas bloqueadas — desbloqueie ou aumente o limite primeiro.' : null)
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\DatePicker::make('data_movimento')
                            ->label('Data')
                            ->required()
                            ->default(now()),
                        Forms\Components\Textarea::make('motivo')
                            ->label('Observação'),
                    ])
                    ->action(function (array $data, $record): void {
                        $inscricao = $this->getOwnerRecord();
                        $activaNoutraTurma = $inscricao->turmaAtiva;

                        if ($activaNoutraTurma && $activaNoutraTurma->turma_id !== $record->id) {
                            $activaNoutraTurma->update([
                                'status' => EstadoInscricaoTurma::Transferido->value,
                                'data_fim' => $data['data_movimento'],
                                'motivo' => $data['motivo'] ?? 'Reactivação nesta turma.',
                            ]);
                        }

                        $inscricao->turmas()->attach($record->id, [
                            'status' => EstadoInscricaoTurma::Ativo->value,
                            'data_inicio' => $data['data_movimento'],
                            'motivo' => $data['motivo'] ?? null,
                        ]);
                    }),
            ])
            ->bulkActions([]);
    }
}
