<?php

namespace App\Filament\Resources\TurmaResource\RelationManagers;

use App\Enums\EstadoInscricao;
use App\Enums\EstadoInscricaoTurma;
use App\Enums\TipoInscricao;
use App\Models\Catequizando;
use App\Models\Inscricao;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Catequizandos colocados nesta turma, via inscricao_turma. Adicionar/trocar
 * daqui e o mesmo fluxo da accao "Colocar/Trocar de Turma" em
 * InscricaoResource\RelationManagers\InscricaoTurmaRelationManager, só que a
 * partir da turma: escolhe-se o catequizando, e reaproveita-se a inscricao
 * activa dele no ano lectivo desta turma (ou cria-se uma nova, se ainda não
 * tiver) — nunca edita inscricoes/turmas directamente (docs/modulos/
 * catequese.md secc. 7.1/10).
 */
class CatequizandosRelationManager extends RelationManager
{
    protected static string $relationship = 'inscricoes';

    // Inscricao::turmas() e o inverso real (BelongsToMany, ver Inscricao model).
    protected static ?string $inverseRelationship = 'turmas';

    protected static ?string $title = 'Catequizandos';

    protected static ?string $recordTitleAttribute = 'numero_ficha';

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
            ->description(function () {
                $turma = $this->getOwnerRecord();

                if (! $turma->vagas_maximo) {
                    return null;
                }

                $ocupadas = $turma->vagasOcupadas();

                if ($turma->vagas_bloqueadas) {
                    return "Vagas bloqueadas manualmente ({$ocupadas}/{$turma->vagas_maximo}) — use \"Desbloquear Vagas\" para voltar a aceitar catequizandos.";
                }

                if ($turma->estaCheia()) {
                    return "Atenção: a turma atingiu o número máximo de vagas ({$ocupadas}/{$turma->vagas_maximo}). Continua a aceitar novos catequizandos até bloqueares manualmente — use \"Bloquear Vagas\" ou \"Aumentar Vagas\".";
                }

                return "{$ocupadas}/{$turma->vagas_maximo} vagas ocupadas.";
            })
            ->columns([
                Tables\Columns\TextColumn::make('catequizando.nome_completo')
                    ->label('Catequizando')
                    ->searchable(),
                Tables\Columns\TextColumn::make('numero_ficha')
                    ->label('Nº Ficha'),
                Tables\Columns\TextColumn::make('pivot.data_inicio')
                    ->label('Início')
                    ->date(),
                Tables\Columns\TextColumn::make('pivot.data_fim')
                    ->label('Fim')
                    ->date()
                    ->placeholder('—'),
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
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        EstadoInscricaoTurma::Ativo->value => 'Activo',
                        EstadoInscricaoTurma::Transferido->value => 'Transferido',
                        EstadoInscricaoTurma::Removido->value => 'Removido',
                    ])
                    ->query(function (Builder $query, array $data) {
                        // wherePivot() nao resolve correctamente dentro do query() de um
                        // filtro de RelationManager (mesmo problema do withCount() em
                        // CatequizandosPorTurmaChart) — nome da tabela qualificado directamente.
                        if (filled($data['value'] ?? null)) {
                            $query->where('inscricao_turma.status', $data['value']);
                        }
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('adicionarCatequizando')
                    ->label('Adicionar Catequizando')
                    ->icon('heroicon-o-plus')
                    ->visible(fn () => self::podeGerir())
                    ->disabled(fn () => $this->getOwnerRecord()->vagas_bloqueadas)
                    ->tooltip(fn () => $this->getOwnerRecord()->vagas_bloqueadas ? 'Vagas bloqueadas — desbloqueie ou aumente o limite primeiro.' : null)
                    ->form(function () {
                        $turma = $this->getOwnerRecord();

                        $idsJaAtivos = $turma->inscricoes()->wherePivot('status', EstadoInscricaoTurma::Ativo->value)
                            ->pluck('catequizando_id')->all();

                        $turmaSacramentoIds = $turma->sacramentos()->pluck('sacramentos.id')->sort()->values()->all();

                        // So catequizandos sem inscricao para este ano lectivo (cria-se uma
                        // nova, a seguir o ano catequetico/sacramentos da propria turma) OU
                        // cuja inscricao ja bate certo com o ano catequetico E o conjunto
                        // exacto de sacramentos desta turma — nunca uma inscricao "1º
                        // Baptismo e Comunhão" numa turma "1º Baptismo" (docs/modulos/
                        // catequese.md secc. 12/13). Inscricoes ainda sem ano
                        // catequetico/sacramentos definidos (dados antigos a este campo
                        // existir) sao tratadas como compativeis, nao excluidas.
                        $idsIncompativeis = Inscricao::withoutGlobalScopes()
                            ->where('ano_letivo_id', $turma->ano_letivo_id)
                            ->where('estado', '!=', EstadoInscricao::Cancelado->value)
                            ->get()
                            ->filter(function (Inscricao $inscricao) use ($turma, $turmaSacramentoIds) {
                                if ($inscricao->ano_catequetico_id !== null && $inscricao->ano_catequetico_id !== $turma->ano_catequetico_id) {
                                    return true;
                                }

                                $sacramentoIds = $inscricao->sacramentos()->pluck('sacramentos.id')->sort()->values()->all();

                                return $sacramentoIds !== [] && $sacramentoIds !== $turmaSacramentoIds;
                            })
                            ->pluck('catequizando_id')->all();

                        return [
                            Forms\Components\Select::make('catequizando_id')
                                ->label('Catequizando')
                                ->options(
                                    fn () => Catequizando::withoutGlobalScopes()
                                        ->where('centro_id', $turma->centro_id)
                                        ->whereNotIn('id', array_merge($idsJaAtivos, $idsIncompativeis))
                                        ->pluck('nome_completo', 'id')
                                )
                                ->searchable()
                                ->required()
                                ->helperText('Só mostra catequizandos sem inscrição incompatível — mesmo ano catequético e sacramento(s) desta turma.'),
                            Forms\Components\DatePicker::make('data_movimento')
                                ->label('Data')
                                ->required()
                                ->default(now()),
                            Forms\Components\Textarea::make('motivo')
                                ->label('Observação'),
                        ];
                    })
                    ->action(function (array $data): void {
                        $turma = $this->getOwnerRecord();

                        $inscricao = Inscricao::withoutGlobalScopes()
                            ->where('catequizando_id', $data['catequizando_id'])
                            ->where('ano_letivo_id', $turma->ano_letivo_id)
                            ->where('estado', '!=', EstadoInscricao::Cancelado->value)
                            ->first();

                        if (! $inscricao) {
                            // Sem ficha ainda para este ano lectivo — cria-se uma nova,
                            // seguindo o ano catequetico/sacramentos da propria turma
                            // (é para essa turma que o catequizando está a ser colocado).
                            $inscricao = Inscricao::create([
                                'paroquia_id' => $turma->paroquia_id,
                                'centro_id' => $turma->centro_id,
                                'catequizando_id' => $data['catequizando_id'],
                                'ano_letivo_id' => $turma->ano_letivo_id,
                                'ano_catequetico_id' => $turma->ano_catequetico_id,
                                'tipo' => TipoInscricao::Nova->value,
                                'data_atendimento' => $data['data_movimento'],
                                'estado' => EstadoInscricao::Inscrito->value,
                                'observacoes' => $data['motivo'] ?? null,
                            ]);

                            $inscricao->sacramentos()->sync($turma->sacramentos()->pluck('sacramentos.id'));
                        }

                        $activa = $inscricao->turmaAtiva;

                        if ($activa && $activa->turma_id === $turma->id) {
                            return;
                        }

                        if ($activa) {
                            $activa->update([
                                'status' => EstadoInscricaoTurma::Transferido->value,
                                'data_fim' => $data['data_movimento'],
                                'motivo' => $data['motivo'] ?? null,
                            ]);
                        }

                        $inscricao->turmas()->attach($turma->id, [
                            'status' => EstadoInscricaoTurma::Ativo->value,
                            'data_inicio' => $data['data_movimento'],
                            'motivo' => $data['motivo'] ?? null,
                        ]);
                    }),
                // Bloqueio manual, nunca automatico (pedido explicito do utilizador,
                // docs/modulos/catequese.md secc. 14) — atingir vagas_maximo so
                // mostra o alerta acima, quem gere decide bloquear ou aumentar.
                Tables\Actions\Action::make('bloquearVagas')
                    ->label('Bloquear Vagas')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->visible(fn () => self::podeGerir() && ! $this->getOwnerRecord()->vagas_bloqueadas)
                    ->requiresConfirmation()
                    ->modalDescription('Deixa de ser possível adicionar novos catequizandos a esta turma até desbloquear.')
                    ->action(fn () => $this->getOwnerRecord()->update(['vagas_bloqueadas' => true])),
                Tables\Actions\Action::make('desbloquearVagas')
                    ->label('Desbloquear Vagas')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->visible(fn () => self::podeGerir() && $this->getOwnerRecord()->vagas_bloqueadas)
                    ->action(fn () => $this->getOwnerRecord()->update(['vagas_bloqueadas' => false])),
                Tables\Actions\Action::make('aumentarVagas')
                    ->label('Aumentar Vagas')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->visible(fn () => self::podeGerir() && $this->getOwnerRecord()->vagas_maximo !== null)
                    ->form([
                        Forms\Components\TextInput::make('vagas_maximo')
                            ->label('Novo Nº Máximo de Vagas')
                            ->numeric()
                            ->required()
                            ->default(fn () => $this->getOwnerRecord()->vagas_maximo)
                            ->minValue(fn () => $this->getOwnerRecord()->vagasOcupadas()),
                    ])
                    ->action(fn (array $data) => $this->getOwnerRecord()->update(['vagas_maximo' => $data['vagas_maximo']])),
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
                // Reactivar em vez de obrigar a passar outra vez por "Adicionar
                // Catequizando" — cria uma nova linha activa (nunca reescreve a
                // linha "removido", preserva o historico), fechando primeiro
                // qualquer outra colocacao activa que a inscricao tenha entretanto.
                Tables\Actions\Action::make('reactivar')
                    ->label('Reactivar')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->visible(fn ($record) => self::podeGerir() && $record->pivot->status === EstadoInscricaoTurma::Removido)
                    ->disabled(fn () => $this->getOwnerRecord()->vagas_bloqueadas)
                    ->tooltip(fn () => $this->getOwnerRecord()->vagas_bloqueadas ? 'Vagas bloqueadas — desbloqueie ou aumente o limite primeiro.' : null)
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
                        $turma = $this->getOwnerRecord();
                        $activaNoutraTurma = $record->turmaAtiva;

                        if ($activaNoutraTurma && $activaNoutraTurma->turma_id !== $turma->id) {
                            $activaNoutraTurma->update([
                                'status' => EstadoInscricaoTurma::Transferido->value,
                                'data_fim' => $data['data_movimento'],
                                'motivo' => $data['motivo'] ?? 'Reactivação nesta turma.',
                            ]);
                        }

                        $record->turmas()->attach($turma->id, [
                            'status' => EstadoInscricaoTurma::Ativo->value,
                            'data_inicio' => $data['data_movimento'],
                            'motivo' => $data['motivo'] ?? null,
                        ]);
                    }),
            ])
            ->bulkActions([]);
    }
}
