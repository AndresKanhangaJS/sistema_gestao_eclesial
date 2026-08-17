<?php

namespace App\Filament\Concerns;

use App\Models\AnoLetivo;
use Closure;
use Filament\Forms;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Tables;

/**
 * Botao "Exportar" unico (Excel/PDF agrupados) com um modal previo a
 * escolher o Estado (e, onde fizer sentido, o Ano Lectivo) a incluir —
 * reutilizado pelas listas de Catequizandos, Catequistas, Inscricoes e
 * pelos Catequizandos de uma Turma (docs/modulos/catequese.md secc. 18/19).
 * Nao acede a $this: quem chama passa, via $parametrosRota, o que for
 * preciso do proprio contexto (ex.: id da turma).
 *
 * O PDF nao redirecciona directamente: closures definidos aqui (metodo
 * estatico) nunca tem $this ligado ao componente Livewire, por isso nao ha
 * como abrir uma nova aba via $this->js('window.open(...)') — e mesmo que
 * houvesse, um window.open() disparado depois do pedido AJAX do formulario
 * arrisca ser bloqueado como pop-up. Em vez disso, envia-se uma notificacao
 * com um botao "Abrir PDF" (link real, target=_blank), garantindo que a
 * nova aba abre sempre a partir de um clique genuino do utilizador.
 */
trait TemExportacaoComEstado
{
    private static function accaoExportarComEstado(
        array $opcoesEstado,
        string $estadoPorOmissao,
        string $rotaExcel,
        string $rotaPdf,
        ?Closure $parametrosRota = null,
        bool $comAnoLectivo = false,
    ): Tables\Actions\ActionGroup {
        $parametros = $parametrosRota ?? fn () => [];

        $campos = function () use ($opcoesEstado, $estadoPorOmissao, $comAnoLectivo) {
            $campos = [
                Forms\Components\Select::make('estado')
                    ->label('Estado')
                    ->options($opcoesEstado)
                    ->default($estadoPorOmissao)
                    ->native(false)
                    ->required(),
            ];

            if ($comAnoLectivo) {
                // Por omissao, o ano lectivo em curso da paroquia — o trabalho
                // do modulo e sempre organizado por ano lectivo, raramente faz
                // sentido exportar "tudo desde sempre" sem pensar nisso primeiro.
                $campos[] = Forms\Components\Select::make('ano_letivo_id')
                    ->label('Ano Lectivo')
                    ->options(fn () => ['todos' => 'Todos'] + AnoLetivo::query()->orderByDesc('nome')->pluck('nome', 'id')->all())
                    ->default(fn () => AnoLetivo::where('status', 'em_curso')->value('id') ?? 'todos')
                    ->native(false)
                    ->required();
            }

            return $campos;
        };

        $parametrosRotaComDados = function (array $data) use ($parametros, $comAnoLectivo) {
            return [
                ...$parametros(),
                'estado' => $data['estado'],
                ...($comAnoLectivo ? ['ano_letivo' => $data['ano_letivo_id']] : []),
            ];
        };

        return Tables\Actions\ActionGroup::make([
            Tables\Actions\Action::make('exportarExcel')
                ->label('Excel')
                ->icon('heroicon-o-table-cells')
                ->modalWidth('sm')
                ->form($campos())
                ->action(fn (array $data) => redirect(route($rotaExcel, $parametrosRotaComDados($data)))),
            Tables\Actions\Action::make('exportarPdf')
                ->label('PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->modalWidth('sm')
                ->form($campos())
                ->action(function (array $data) use ($rotaPdf, $parametrosRotaComDados): void {
                    $url = route($rotaPdf, $parametrosRotaComDados($data));

                    Notification::make()
                        ->title('PDF pronto')
                        ->success()
                        ->actions([
                            NotificationAction::make('abrir')
                                ->label('Abrir PDF')
                                ->url($url)
                                ->openUrlInNewTab()
                                ->button(),
                        ])
                        ->send();
                }),
        ])
            ->label('Exportar')
            ->icon('heroicon-o-arrow-down-tray')
            ->button();
    }
}
