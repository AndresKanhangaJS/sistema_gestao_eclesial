<x-filament-panels::page>
    <div class="flex flex-wrap items-end gap-4">
        <div class="w-32">
            <x-filament::input.wrapper>
                <select wire:model.live="ano" class="fi-select-input block w-full">
                    @foreach ($this->getAnosDisponiveis() as $anoOpcao)
                        <option value="{{ $anoOpcao }}">{{ $anoOpcao }}</option>
                    @endforeach
                </select>
            </x-filament::input.wrapper>
        </div>
        <div class="flex gap-2">
            <x-filament::button tag="a" href="{{ route('relatorios.demonstrativo-arrecadacao.excel', ['ano' => $ano]) }}" icon="heroicon-o-table-cells">
                Exportar Excel
            </x-filament::button>
            <x-filament::button tag="a" href="{{ route('relatorios.demonstrativo-arrecadacao.pdf', ['ano' => $ano]) }}" color="gray" icon="heroicon-o-document-arrow-down">
                Baixar PDF
            </x-filament::button>
        </div>
    </div>

    {{-- @livewire com key dependente do ano, em vez de <x-filament-widgets::widgets>:
    esse componente monta os widgets com uma key fixa, por isso ao mudar o ano
    no seletor acima a tabela por baixo actualiza (e computed na propria pagina)
    mas os graficos ficavam presos ao ano do primeiro carregamento — a key
    fixa impede o Livewire de os remontar. --}}
    <x-filament::grid default="1" :lg="2" class="fi-wi gap-6">
        @livewire(\App\Filament\Widgets\ArrecadacaoBarChart::class, ['ano' => $ano], key('arrecadacao-bar-chart-' . $ano))
        @livewire(\App\Filament\Widgets\ArrecadacaoPieChart::class, ['ano' => $ano], key('arrecadacao-pie-chart-' . $ano))
    </x-filament::grid>

    <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2 text-left">Mês</th>
                    <th class="px-3 py-2 text-right">Dízimo</th>
                    <th class="px-3 py-2 text-right">Ofertório</th>
                    <th class="px-3 py-2 text-right">Outras Contribuições</th>
                    <th class="px-3 py-2 text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach (['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'] as $i => $mesLabel)
                    @php $linha = $this->dados['por_mes_tipo'][$i + 1]; @endphp
                    <tr class="border-t border-gray-100 dark:border-gray-700">
                        <td class="px-3 py-2">{{ $mesLabel }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($linha['dizimo'], 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($linha['ofertorio'], 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($linha['campanha'], 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ number_format(array_sum($linha), 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="border-t-2 border-gray-300 dark:border-gray-600 font-bold">
                    <td class="px-3 py-2">Total</td>
                    <td class="px-3 py-2 text-right">{{ number_format($this->dados['por_tipo']['dizimo'], 2, ',', '.') }}</td>
                    <td class="px-3 py-2 text-right">{{ number_format($this->dados['por_tipo']['ofertorio'], 2, ',', '.') }}</td>
                    <td class="px-3 py-2 text-right">{{ number_format($this->dados['por_tipo']['campanha'], 2, ',', '.') }}</td>
                    <td class="px-3 py-2 text-right">{{ number_format($this->dados['total'], 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
