<x-filament-panels::page>
    <div class="flex flex-wrap items-end gap-4">
        <div class="w-40">
            <x-filament::input.wrapper>
                <select wire:model.live="mes" class="fi-select-input block w-full">
                    @foreach ($this->getMesesDisponiveis() as $mesOpcao => $mesLabel)
                        <option value="{{ $mesOpcao }}">{{ $mesLabel }}</option>
                    @endforeach
                </select>
            </x-filament::input.wrapper>
        </div>
        <div class="w-32">
            <x-filament::input.wrapper>
                <select wire:model.live="ano" class="fi-select-input block w-full">
                    @foreach ($this->getAnosDisponiveis() as $anoOpcao)
                        <option value="{{ $anoOpcao }}">{{ $anoOpcao }}</option>
                    @endforeach
                </select>
            </x-filament::input.wrapper>
        </div>
        @if ($this->mostrarSeletorCentro())
            <div class="w-56">
                <x-filament::input.wrapper>
                    <select wire:model.live="centroId" class="fi-select-input block w-full">
                        @foreach ($this->getCentrosDisponiveis() as $id => $nome)
                            <option value="{{ $id }}">{{ $nome }}</option>
                        @endforeach
                    </select>
                </x-filament::input.wrapper>
            </div>
        @endif
        <div class="flex gap-2">
            <x-filament::button tag="a" :href="route('relatorios.balancete-mensal.excel', ['ano' => $ano, 'mes' => $mes, 'centro_id' => $centroId])" icon="heroicon-o-table-cells">
                Exportar Excel
            </x-filament::button>
            <x-filament::button tag="a" :href="route('relatorios.balancete-mensal.pdf', ['ano' => $ano, 'mes' => $mes, 'centro_id' => $centroId])" color="gray" icon="heroicon-o-document-arrow-down">
                Baixar PDF
            </x-filament::button>
        </div>
    </div>

    @if ($this->jaFechado)
        <div class="mt-4">
            <x-filament::badge color="success" icon="heroicon-o-lock-closed">
                Balancete deste mês já fechado
            </x-filament::badge>
        </div>
    @endif

    @if (! $this->dados)
        <div class="mt-4">
            <x-filament::section>
                Escolha um centro para ver o Balancete.
            </x-filament::section>
        </div>
    @else
        @php $dados = $this->dados; @endphp

        <div class="mt-4 grid grid-cols-3 gap-4">
            <x-filament::section>
                <x-slot name="heading">Total de Receitas</x-slot>
                <p class="text-2xl font-bold" style="color: rgba(var(--success-600), 1)">{{ number_format($dados['receitas']['total'], 2, ',', '.') }}</p>
            </x-filament::section>
            <x-filament::section>
                <x-slot name="heading">Total de Despesas</x-slot>
                <p class="text-2xl font-bold" style="color: rgba(var(--danger-600), 1)">{{ number_format($dados['despesas']['total'], 2, ',', '.') }}</p>
            </x-filament::section>
            <x-filament::section>
                <x-slot name="heading">Saldo do Mês</x-slot>
                <p class="text-2xl font-bold">{{ number_format($dados['saldo_mes'], 2, ',', '.') }}</p>
            </x-filament::section>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">1. Receitas</x-slot>
                <table class="w-full text-sm">
                    <tbody>
                        <tr><td>1.1.1 Subsídios</td><td class="text-right">{{ number_format($dados['receitas']['subsidios'], 2, ',', '.') }}</td></tr>
                        <tr><td>1.1.2 Donativos</td><td class="text-right">{{ number_format($dados['receitas']['donativos'], 2, ',', '.') }}</td></tr>
                        <tr><td>1.1.3 Peditórios</td><td class="text-right">{{ number_format($dados['receitas']['peditorios'], 2, ',', '.') }}</td></tr>
                        <tr><td>1.1.4 Entradas do Cartório</td><td class="text-right">{{ number_format($dados['receitas']['cartorio'], 2, ',', '.') }}</td></tr>
                        @foreach ($dados['receitas']['diversas'] as $i => $linha)
                            <tr><td>1.1.5.{{ $i + 1 }} {{ $linha['nome'] }}</td><td class="text-right">{{ number_format($linha['valor'], 2, ',', '.') }}</td></tr>
                        @endforeach
                        <tr class="font-semibold border-t border-gray-200 dark:border-gray-700"><td>Subtotal Ordinárias</td><td class="text-right">{{ number_format($dados['receitas']['subtotal_ordinarias'], 2, ',', '.') }}</td></tr>
                        <tr><td class="pt-3">1.2 Côngruas</td><td class="text-right pt-3">{{ number_format($dados['receitas']['congruas'], 2, ',', '.') }}</td></tr>
                        <tr><td class="pt-3">1.3 Intenções de Missa</td><td class="text-right pt-3">{{ number_format($dados['receitas']['intencoes_missa'], 2, ',', '.') }}</td></tr>
                        <tr><td class="pt-3">Saldo Mês Anterior</td><td class="text-right pt-3">{{ number_format($dados['receitas']['saldo_mes_anterior'], 2, ',', '.') }}</td></tr>
                        <tr class="font-bold border-t border-gray-200 dark:border-gray-700"><td>TOTAL DE RECEITAS</td><td class="text-right">{{ number_format($dados['receitas']['total'], 2, ',', '.') }}</td></tr>
                    </tbody>
                </table>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">2. Despesas</x-slot>
                <table class="w-full text-sm">
                    <tbody>
                        <tr><td>2.1.1 Subsídio aos Padres</td><td class="text-right">{{ number_format($dados['despesas']['subsidio_padres'], 2, ',', '.') }}</td></tr>
                        <tr><td>2.1.2 Estipêndios</td><td class="text-right">{{ number_format($dados['despesas']['estipendios'], 2, ',', '.') }}</td></tr>
                        <tr><td>2.1.3 Subsídio Casa Paroquial</td><td class="text-right">{{ number_format($dados['despesas']['subsidio_casa_paroquial'], 2, ',', '.') }}</td></tr>
                        <tr class="font-semibold border-t border-gray-200 dark:border-gray-700"><td>Subtotal Clero</td><td class="text-right">{{ number_format($dados['despesas']['subtotal_clero'], 2, ',', '.') }}</td></tr>

                        <tr><td class="pt-3">2.2.1 Salário Funcionários + INSS</td><td class="text-right pt-3">{{ number_format($dados['despesas']['salario_funcionarios'], 2, ',', '.') }}</td></tr>
                        <tr><td>2.2.2 Despesas de Culto</td><td class="text-right">{{ number_format($dados['despesas']['despesas_culto'], 2, ',', '.') }}</td></tr>
                        <tr><td>2.2.3 Transporte - Viagens</td><td class="text-right">{{ number_format($dados['despesas']['transporte'], 2, ',', '.') }}</td></tr>
                        <tr><td>2.2.4 Energia</td><td class="text-right">{{ number_format($dados['despesas']['energia'], 2, ',', '.') }}</td></tr>
                        <tr><td>2.2.5 Água</td><td class="text-right">{{ number_format($dados['despesas']['agua'], 2, ',', '.') }}</td></tr>
                        <tr><td>2.2.6 Internet - Comunicações</td><td class="text-right">{{ number_format($dados['despesas']['internet'], 2, ',', '.') }}</td></tr>
                        <tr><td>2.2.7 Donativos e esmolas</td><td class="text-right">{{ number_format($dados['despesas']['donativos_esmolas'], 2, ',', '.') }}</td></tr>
                        @foreach ($dados['despesas']['outras_despesas'] as $i => $linha)
                            <tr><td>2.2.8.{{ $i + 1 }} {{ $linha['nome'] }}</td><td class="text-right">{{ number_format($linha['valor'], 2, ',', '.') }}</td></tr>
                        @endforeach
                        <tr class="font-semibold border-t border-gray-200 dark:border-gray-700"><td>Subtotal Despesas Correntes</td><td class="text-right">{{ number_format($dados['despesas']['subtotal_correntes'], 2, ',', '.') }}</td></tr>

                        <tr><td class="pt-3">2.3.1 Contributo Bispado ({{ number_format($dados['despesas']['percentual_bispado'], 0) }}%)</td><td class="text-right pt-3">{{ number_format($dados['despesas']['contributo_bispado'], 2, ',', '.') }}</td></tr>
                        <tr><td>2.3.2 Contributo do Dízimo ({{ number_format($dados['despesas']['percentual_dizimo'], 0) }}%)</td><td class="text-right">{{ number_format($dados['despesas']['contributo_dizimo'], 2, ',', '.') }}</td></tr>
                        <tr><td>2.3.3 Missas Binadas</td><td class="text-right">{{ number_format($dados['despesas']['missas_binadas'], 2, ',', '.') }}</td></tr>
                        @foreach ($dados['despesas']['colectas_obrigatorias'] as $i => $linha)
                            <tr><td>2.3.4.{{ $i + 1 }} {{ $linha['nome'] }}</td><td class="text-right">{{ number_format($linha['valor'], 2, ',', '.') }}</td></tr>
                        @endforeach
                        <tr class="font-semibold border-t border-gray-200 dark:border-gray-700"><td>Subtotal Contributos à Arquidiocese</td><td class="text-right">{{ number_format($dados['despesas']['subtotal_contributos'], 2, ',', '.') }}</td></tr>

                        <tr class="font-bold border-t border-gray-200 dark:border-gray-700"><td>TOTAL DE DESPESAS</td><td class="text-right">{{ number_format($dados['despesas']['total'], 2, ',', '.') }}</td></tr>
                    </tbody>
                </table>
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
