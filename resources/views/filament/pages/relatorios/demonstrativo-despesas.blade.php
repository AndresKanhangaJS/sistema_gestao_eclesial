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
        @if ($this->mostrarFiltroCentro())
            <div class="w-56">
                <x-filament::input.wrapper>
                    <select wire:model.live="centroId" class="fi-select-input block w-full">
                        <option value="">Todos os centros</option>
                        @foreach ($this->getCentrosDisponiveis() as $id => $nome)
                            <option value="{{ $id }}">{{ $nome }}</option>
                        @endforeach
                    </select>
                </x-filament::input.wrapper>
            </div>
        @endif
        <div class="flex gap-2">
            <x-filament::button tag="a" href="{{ route('relatorios.demonstrativo-despesas.excel', ['ano' => $ano, 'centro_id' => $centroId]) }}" icon="heroicon-o-table-cells">
                Exportar Excel
            </x-filament::button>
            <x-filament::button tag="a" href="{{ route('relatorios.demonstrativo-despesas.pdf', ['ano' => $ano, 'centro_id' => $centroId]) }}" color="gray" icon="heroicon-o-document-arrow-down">
                Baixar PDF
            </x-filament::button>
        </div>
    </div>

    {{-- @livewire com key dependente do ano, mesmo motivo do demonstrativo-arrecadacao:
    sem a key variavel os graficos ficam presos ao ano do primeiro carregamento.
    Em linhas separadas (não lado a lado): o gráfico de barras tem sempre mais
    conteúdo em largura (várias séries, 12 meses) do que o de pizza, e ficavam
    desproporcionados um ao lado do outro. --}}
    <div class="fi-wi grid grid-cols-1 gap-6">
        @livewire(\App\Filament\Widgets\DespesasBarChart::class, ['ano' => $ano, 'centroId' => $this->centroIdParaConsulta()], key('despesas-bar-chart-' . $ano . '-' . $centroId))
        @livewire(\App\Filament\Widgets\DespesasPieChart::class, ['ano' => $ano, 'centroId' => $this->centroIdParaConsulta()], key('despesas-pie-chart-' . $ano . '-' . $centroId))
    </div>

    <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2 text-left">Mês</th>
                    @foreach ($this->dados['categorias'] as $categoria)
                        <th class="px-3 py-2 text-right">{{ $categoria->nome }}</th>
                    @endforeach
                    <th class="px-3 py-2 text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach (['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'] as $i => $mesLabel)
                    @php $linha = $this->dados['por_mes_categoria'][$i + 1]; @endphp
                    <tr class="border-t border-gray-100 dark:border-gray-700">
                        <td class="px-3 py-2">{{ $mesLabel }}</td>
                        @foreach ($this->dados['categorias'] as $categoria)
                            <td class="px-3 py-2 text-right">{{ number_format($linha[$categoria->id], 2, ',', '.') }}</td>
                        @endforeach
                        <td class="px-3 py-2 text-right font-semibold">{{ number_format(array_sum($linha), 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="border-t-2 border-gray-300 dark:border-gray-600 font-bold">
                    <td class="px-3 py-2">Total</td>
                    @foreach ($this->dados['categorias'] as $categoria)
                        <td class="px-3 py-2 text-right">{{ number_format($this->dados['por_categoria'][$categoria->id], 2, ',', '.') }}</td>
                    @endforeach
                    <td class="px-3 py-2 text-right">{{ number_format($this->dados['total'], 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
