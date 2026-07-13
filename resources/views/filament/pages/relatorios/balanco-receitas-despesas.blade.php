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
            <x-filament::button tag="a" href="{{ route('relatorios.balanco-receitas-despesas.excel', ['ano' => $ano]) }}" icon="heroicon-o-table-cells">
                Exportar Excel
            </x-filament::button>
            <x-filament::button tag="a" href="{{ route('relatorios.balanco-receitas-despesas.pdf', ['ano' => $ano]) }}" color="gray" icon="heroicon-o-document-arrow-down">
                Baixar PDF
            </x-filament::button>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-3 gap-4">
        <x-filament::section>
            <x-slot name="heading">Receitas</x-slot>
            <p class="text-2xl font-bold" style="color: rgba(var(--success-600), 1)">{{ number_format($this->dados['total_receitas'], 2, ',', '.') }}</p>
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Despesas</x-slot>
            <p class="text-2xl font-bold" style="color: rgba(var(--danger-600), 1)">{{ number_format($this->dados['total_despesas'], 2, ',', '.') }}</p>
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Saldo</x-slot>
            <p class="text-2xl font-bold">{{ number_format($this->dados['saldo'], 2, ',', '.') }}</p>
        </x-filament::section>
    </div>

    <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2 text-left">Mês</th>
                    <th class="px-3 py-2 text-right">Receitas</th>
                    <th class="px-3 py-2 text-right">Despesas</th>
                    <th class="px-3 py-2 text-right">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @foreach (['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'] as $i => $mesLabel)
                    @php $linha = $this->dados['por_mes'][$i + 1]; @endphp
                    <tr class="border-t border-gray-100 dark:border-gray-700">
                        <td class="px-3 py-2">{{ $mesLabel }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($linha['receitas'], 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($linha['despesas'], 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ number_format($linha['saldo'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
