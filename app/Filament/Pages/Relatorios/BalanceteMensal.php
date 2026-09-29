<?php

namespace App\Filament\Pages\Relatorios;

use App\Models\Banco;
use App\Models\BalanceteMensal as BalanceteMensalModel;
use App\Models\Centro;
use App\Models\MetodoPagamento;
use App\Services\BalanceteService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use RuntimeException;

/**
 * Balancete mensal no modelo oficial da Arquidiocese (Modulo 7, pedido do
 * cliente 2026-09-29) — ver App\Services\BalanceteService. Sempre por
 * centro (cada Movimento pertence a 1 so centro, Modulo 4), por isso nao ha
 * selector "Todos os centros" como nos outros relatorios do modulo.
 *
 * "Fechar Balancete" fica restrito aos GESTORES_PAROQUIA (mesmo criterio de
 * quem aprova/rejeita Movimentos, MovimentoPolicy) — gera Movimentos reais
 * de repasse, por isso e uma accao de conciliacao, nao so de leitura.
 */
class BalanceteMensal extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    private const PAPEIS_CENTRO = ['tesoureiro_centro', 'coordenador_centro'];

    private const GESTORES_PAROQUIA = ['administrador_paroquial', 'tesoureiro_paroquial'];

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Balancete Mensal';

    protected static ?string $title = 'Balancete Mensal';

    protected static string $view = 'filament.pages.relatorios.balancete-mensal';

    public ?int $centroId = null;

    public int $ano;

    public int $mes;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro', 'coordenador_centro', 'consultor']) ?? false;
    }

    public function mount(): void
    {
        $this->ano = (int) now()->year;
        $this->mes = (int) now()->month;

        $user = Auth::user();
        $this->centroId = $user->hasRole(self::PAPEIS_CENTRO)
            ? $user->centro_id
            : (array_key_first($this->getCentrosDisponiveis()) ?: null);
    }

    public function getCentrosDisponiveis(): array
    {
        $user = Auth::user();

        if ($user->hasRole(self::PAPEIS_CENTRO)) {
            return Centro::where('id', $user->centro_id)->pluck('nome', 'id')->all();
        }

        return Centro::orderBy('nome')->pluck('nome', 'id')->all();
    }

    public function mostrarSeletorCentro(): bool
    {
        return ! (Auth::user()?->hasRole(self::PAPEIS_CENTRO) ?? false);
    }

    public function getAnosDisponiveis(): array
    {
        $anoAtual = (int) now()->year;

        return array_combine(range($anoAtual - 2, $anoAtual), range($anoAtual - 2, $anoAtual));
    }

    public function getMesesDisponiveis(): array
    {
        return [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];
    }

    /**
     * centroId e propriedade publica Livewire — adulteravel no cliente. Um
     * valor fora de getCentrosDisponiveis() (ex.: centro de outra paroquia)
     * e ignorado, cai para null (sem dados) em vez de vazar outra paroquia.
     */
    public function centroIdValido(): ?int
    {
        $user = Auth::user();

        if ($user->hasRole(self::PAPEIS_CENTRO)) {
            return $user->centro_id;
        }

        if ($this->centroId && array_key_exists($this->centroId, $this->getCentrosDisponiveis())) {
            return $this->centroId;
        }

        return null;
    }

    #[Computed]
    public function dados(): ?array
    {
        $centroId = $this->centroIdValido();

        if (! $centroId) {
            return null;
        }

        $centro = Centro::withoutGlobalScopes()->find($centroId);

        return BalanceteService::calcular($this->ano, $this->mes, $centroId, $centro->paroquia_id);
    }

    #[Computed]
    public function jaFechado(): bool
    {
        $centroId = $this->centroIdValido();

        if (! $centroId) {
            return false;
        }

        return BalanceteMensalModel::withoutGlobalScopes()
            ->where('centro_id', $centroId)
            ->where('ano', $this->ano)
            ->where('mes', $this->mes)
            ->exists();
    }

    public function fecharAction(): Action
    {
        return Action::make('fechar')
            ->label('Fechar Balancete')
            ->icon('heroicon-o-lock-closed')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Gera os Movimentos de repasse (Contributo Bispado e Contributo Dízimo) já aprovados e bloqueia este mês contra novo fecho. Confira os valores antes de continuar. A acção não pode ser desfeita.')
            ->visible(fn (): bool => (Auth::user()?->hasRole(self::GESTORES_PAROQUIA) ?? false)
                && $this->centroIdValido() !== null
                && ! $this->jaFechado)
            ->form([
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
            ])
            ->action(function (array $data): void {
                $this->processarFecho($data);
            });
    }

    private function processarFecho(array $data): void
    {
        $centroId = $this->centroIdValido();

        if (! $centroId) {
            abort(403, 'Centro inválido.');
        }

        try {
            BalanceteService::fechar(
                $this->ano,
                $this->mes,
                $centroId,
                Auth::id(),
                (int) $data['metodo_pagamento_id'],
                filled($data['banco_id'] ?? null) ? (int) $data['banco_id'] : null,
                $data['data_movimento'],
            );
        } catch (RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        unset($this->dados, $this->jaFechado);

        Notification::make()->title('Balancete fechado com sucesso.')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->fecharAction(),
        ];
    }
}
