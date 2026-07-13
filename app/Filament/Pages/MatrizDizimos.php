<?php

namespace App\Filament\Pages;

use App\Enums\TipoMovimento;
use App\Models\Banco;
use App\Models\Centro;
use App\Models\Fiel;
use App\Models\MetodoPagamento;
use App\Models\Movimento;
use App\Services\MatrizDizimosService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;

class MatrizDizimos extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationGroup = 'Fiéis';

    protected static ?string $navigationLabel = 'Matriz de Dízimos';

    protected static ?string $title = 'Matriz de Dízimos';

    protected static string $view = 'filament.pages.matriz-dizimos';

    public ?int $centroId = null;

    public int $ano;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro']) ?? false;
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

        return MatrizDizimosService::calcular($this->centroId, $this->ano);
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
                    ->label('Valor')
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->prefix('Kz'),
                Forms\Components\Select::make('metodo_pagamento_id')
                    ->label('Método de Pagamento')
                    ->options(fn () => MetodoPagamento::pluck('nome', 'id'))
                    ->required(),
                Forms\Components\Select::make('banco_id')
                    ->label('Banco')
                    ->options(fn () => Banco::pluck('nome_banco', 'id')),
                Forms\Components\DatePicker::make('data_movimento')
                    ->label('Data do Movimento')
                    ->required()
                    ->default(now()),
                Forms\Components\FileUpload::make('comprovativo_path')
                    ->label('Comprovativo')
                    ->disk(config('filesystems.default'))
                    ->directory('comprovativos')
                    ->getUploadedFileNameForStorageUsing(
                        fn ($file) => Str::uuid().'.'.$file->getClientOriginalExtension()
                    )
                    ->required(
                        fn (Get $get) => MetodoPagamento::find($get('metodo_pagamento_id'))?->exige_comprovativo ?? false
                    ),
            ])
            ->action(function (array $data, array $arguments): void {
                $this->processarLancamentoLote((int) $arguments['fielId'], $data);
            });
    }

    /**
     * O centroId e propriedade publica Livewire — adulteravel no cliente.
     * Confirma que pertence mesmo ao utilizador autenticado antes de o usar
     * para escrever movimentos financeiros.
     */
    private function centroPertenceAoUtilizador(int $centroId): bool
    {
        $user = Auth::user();

        if ($user->hasRole('admin_geral')) {
            return Centro::withoutGlobalScopes()->whereKey($centroId)->exists();
        }

        if ($user->hasRole('tesoureiro_centro')) {
            return $centroId === $user->centro_id;
        }

        // tesoureiro_paroquial: Centro tem ParoquiaScope, um centro de outra
        // paroquia simplesmente nao existe nesta query.
        return Centro::whereKey($centroId)->exists();
    }

    /**
     * Confirma que o fiel (id vindo do argumento da action, tambem
     * controlavel pelo cliente) esta mesmo vinculado ao centro validado.
     */
    private function fielPertenceAoCentro(int $fielId, int $centroId): bool
    {
        return Fiel::whereKey($fielId)
            ->whereHas('centros', fn ($q) => $q->where('centros.id', $centroId))
            ->exists();
    }

    /**
     * Cria um Movimento (tipo=dizimo) por cada mes seleccionado, ignorando
     * meses que entretanto ja tenham um dizimo lancado (Unique Key protege
     * contra duplicados a nivel de BD; aqui so evitamos o erro feio).
     */
    public function processarLancamentoLote(int $fielId, array $data): void
    {
        if (! $this->centroId || ! $this->centroPertenceAoUtilizador($this->centroId)) {
            abort(403, 'Centro inválido.');
        }

        if (! $this->fielPertenceAoCentro($fielId, $this->centroId)) {
            abort(403, 'Fiel não vinculado a este centro.');
        }

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
                // paroquia_id nao e definido aqui de proposito: o
                // MovimentoObserver deriva-o do centro_id (ja validado acima),
                // a mesma fonte de verdade usada no MovimentoResource.
                Movimento::create([
                    'centro_id' => $this->centroId,
                    'fiel_id' => $fielId,
                    'metodo_pagamento_id' => $data['metodo_pagamento_id'],
                    'banco_id' => $data['banco_id'] ?? null,
                    'tipo' => TipoMovimento::Dizimo,
                    'valor' => $data['valor'],
                    'ano_competencia' => $this->ano,
                    'mes_competencia' => $mes,
                    'data_movimento' => $data['data_movimento'],
                    'comprovativo_path' => $data['comprovativo_path'] ?? null,
                ]);
            });

            $criados++;
        }

        unset($this->matriz);

        Notification::make()
            ->title("Lançados {$criados} mês(es); {$ignorados} já estavam pagos e foram ignorados.")
            ->success()
            ->send();
    }
}
