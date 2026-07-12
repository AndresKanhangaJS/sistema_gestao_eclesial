<?php

namespace App\Filament\Pages;

use App\Enums\StatusConciliacao;
use App\Enums\TipoMovimento;
use App\Models\Banco;
use App\Models\Centro;
use App\Models\Fiel;
use App\Models\MetodoPagamento;
use App\Models\Movimento;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

class MatrizDizimos extends Page implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationGroup = 'Fiéis';

    protected static ?string $navigationLabel = 'Matriz de Dízimos';

    protected static ?string $title = 'Matriz de Dízimos';

    protected static string $view = 'filament.pages.matriz-dizimos';

    public ?int $centroId = null;

    public int $ano;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(['admin_geral', 'tesoureiro_paroquial', 'tesoureiro_centro']) ?? false;
    }

    public function mount(): void
    {
        $this->ano = (int) now()->year;

        $user = Auth::user();

        $this->centroId = $user->hasRole('tesoureiro_centro')
            ? $user->centro_id
            : Centro::query()->orderBy('nome')->value('id');
    }

    public function getCentrosDisponiveis(): array
    {
        $user = Auth::user();

        if ($user->hasRole('tesoureiro_centro')) {
            return Centro::where('id', $user->centro_id)->pluck('nome', 'id')->all();
        }

        return Centro::orderBy('nome')->pluck('nome', 'id')->all();
    }

    public function getAnosDisponiveis(): array
    {
        $anoAtual = (int) now()->year;

        return array_combine(range($anoAtual - 2, $anoAtual), range($anoAtual - 2, $anoAtual));
    }

    #[Computed]
    public function matriz(): array
    {
        if (! $this->centroId) {
            return [];
        }

        $inicioAno = Carbon::createFromDate($this->ano, 1, 1)->startOfDay();
        $fimAno = Carbon::createFromDate($this->ano, 12, 31)->endOfDay();
        $centroId = $this->centroId;

        // Fieis com qualquer vinculo (activo ou historico) a este centro
        // sobreposto ao ano seleccionado.
        $fieis = Fiel::whereHas('centros', function ($q) use ($centroId, $inicioAno, $fimAno) {
            $q->where('centros.id', $centroId)
                ->where('fiel_centros.data_inicio', '<=', $fimAno)
                ->where(function ($q2) use ($inicioAno) {
                    $q2->whereNull('fiel_centros.data_fim')
                        ->orWhere('fiel_centros.data_fim', '>=', $inicioAno);
                });
        })
            ->with(['centros' => function ($q) use ($centroId) {
                $q->where('centros.id', $centroId);
            }])
            ->orderBy('nome')
            ->get();

        $pagos = Movimento::where('centro_id', $centroId)
            ->where('tipo', TipoMovimento::Dizimo)
            ->where('ano_competencia', $this->ano)
            ->where('status_conciliacao', StatusConciliacao::Aprovado)
            ->get()
            ->groupBy('fiel_id');

        $linhas = [];

        foreach ($fieis as $fiel) {
            $pagosDoFiel = $pagos->get($fiel->id, collect())->pluck('mes_competencia')->all();
            $meses = [];
            $totalPagos = 0;

            foreach (range(1, 12) as $mes) {
                if (in_array($mes, $pagosDoFiel, true)) {
                    $meses[$mes] = 'pago';
                    $totalPagos++;

                    continue;
                }

                $inicioMes = Carbon::createFromDate($this->ano, $mes, 1)->startOfMonth();
                $fimMes = $inicioMes->copy()->endOfMonth();

                $vinculado = $fiel->centros->contains(function ($centro) use ($inicioMes, $fimMes) {
                    $inicio = Carbon::parse($centro->pivot->data_inicio);
                    $fim = $centro->pivot->data_fim ? Carbon::parse($centro->pivot->data_fim) : null;

                    return $inicio->lte($fimMes) && ($fim === null || $fim->gte($inicioMes));
                });

                $meses[$mes] = $vinculado ? 'em_aberto' : 'nao_vinculado';
            }

            $segmento = match (true) {
                $totalPagos === 12 => 'Assíduo',
                $totalPagos === 0 => 'Inativo',
                $totalPagos <= 6 => 'Irregular',
                default => null,
            };

            $linhas[] = [
                'fiel' => $fiel,
                'meses' => $meses,
                'total_pagos' => $totalPagos,
                'segmento' => $segmento,
            ];
        }

        return $linhas;
    }

    public function lancarLoteAction(): Action
    {
        return Action::make('lancarLote')
            ->label('Lançar dízimos em lote')
            ->modalHeading('Lançar dízimos em lote')
            ->form([
                Forms\Components\CheckboxList::make('meses')
                    ->label('Meses a lançar')
                    ->options([
                        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
                        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
                        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
                    ])
                    ->columns(3)
                    ->required(),
                Forms\Components\TextInput::make('valor')
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->prefix('Kz'),
                Forms\Components\Select::make('metodo_pagamento_id')
                    ->label('Método de pagamento')
                    ->options(fn () => MetodoPagamento::pluck('nome', 'id'))
                    ->required(),
                Forms\Components\Select::make('banco_id')
                    ->label('Banco')
                    ->options(fn () => Banco::pluck('nome_banco', 'id')),
                Forms\Components\DatePicker::make('data_movimento')
                    ->required()
                    ->default(now()),
            ])
            ->action(function (array $data, array $arguments): void {
                $this->processarLancamentoLote((int) $arguments['fielId'], $data);
            });
    }

    /**
     * Cria um Movimento (tipo=dizimo) por cada mes seleccionado, ignorando
     * meses que entretanto ja tenham um dizimo lancado (Unique Key protege
     * contra duplicados a nivel de BD; aqui so evitamos o erro feio).
     */
    public function processarLancamentoLote(int $fielId, array $data): void
    {
        $criados = 0;
        $ignorados = 0;

        foreach ($data['meses'] as $mes) {
            $existe = Movimento::where('fiel_id', $fielId)
                ->where('tipo', TipoMovimento::Dizimo)
                ->where('ano_competencia', $this->ano)
                ->where('mes_competencia', $mes)
                ->exists();

            if ($existe) {
                $ignorados++;

                continue;
            }

            DB::transaction(function () use ($fielId, $mes, $data) {
                Movimento::create([
                    'paroquia_id' => Fiel::withoutGlobalScopes()->find($fielId)->paroquia_id,
                    'centro_id' => $this->centroId,
                    'fiel_id' => $fielId,
                    'metodo_pagamento_id' => $data['metodo_pagamento_id'],
                    'banco_id' => $data['banco_id'] ?? null,
                    'tipo' => TipoMovimento::Dizimo,
                    'valor' => $data['valor'],
                    'ano_competencia' => $this->ano,
                    'mes_competencia' => $mes,
                    'data_movimento' => $data['data_movimento'],
                ]);
            });

            $criados++;
        }

        unset($this->matriz);

        Notification::make()
            ->title("Lançados {$criados} mes(es); {$ignorados} já estavam pagos e foram ignorados.")
            ->success()
            ->send();
    }
}
