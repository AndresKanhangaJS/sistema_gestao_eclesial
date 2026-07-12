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
            <x-filament::button tag="a" href="{{ route('relatorios.fieis-por-situacao.excel', ['ano' => $ano]) }}" icon="heroicon-o-table-cells">
                Exportar Excel
            </x-filament::button>
            <x-filament::button tag="a" href="{{ route('relatorios.fieis-por-situacao.pdf', ['ano' => $ano]) }}" color="gray" icon="heroicon-o-document-arrow-down">
                Baixar PDF
            </x-filament::button>
        </div>
    </div>

    <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2 text-left">Fiel</th>
                    <th class="px-3 py-2 text-center">Dízimos pagos</th>
                    <th class="px-3 py-2 text-center">Situação</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->linhas as $linha)
                    <tr class="border-t border-gray-100 dark:border-gray-700">
                        <td class="px-3 py-2">{{ $linha['fiel']->nome }}</td>
                        <td class="px-3 py-2 text-center">{{ $linha['total_pagos'] }}/12</td>
                        <td class="px-3 py-2 text-center">
                            @if ($linha['segmento'])
                                <x-filament::badge :color="match($linha['segmento']) {
                                    'Assíduo' => 'success',
                                    'Irregular' => 'warning',
                                    'Inativo' => 'danger',
                                }">
                                    {{ $linha['segmento'] }}
                                </x-filament::badge>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-3 py-6 text-center text-gray-400">Sem fiéis para este ano/centro.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
