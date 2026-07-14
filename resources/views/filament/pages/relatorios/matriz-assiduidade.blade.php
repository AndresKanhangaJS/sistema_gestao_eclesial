<x-filament-panels::page>
    <div class="flex flex-wrap items-end gap-4">
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
        <div class="w-32">
            <x-filament::input.wrapper>
                <select wire:model.live="ano" class="fi-select-input block w-full">
                    @foreach ($this->getAnosDisponiveis() as $anoOpcao)
                        <option value="{{ $anoOpcao }}">{{ $anoOpcao }}</option>
                    @endforeach
                </select>
            </x-filament::input.wrapper>
        </div>
        <div class="w-64">
            <x-filament::input.wrapper>
                <input type="text" wire:model.live.debounce.400ms="nomeFiel" placeholder="Pesquisar por nome do fiel" class="fi-input block w-full" />
            </x-filament::input.wrapper>
        </div>
        <div class="flex gap-2">
            <x-filament::button tag="a" href="{{ route('relatorios.matriz-assiduidade.excel', ['centro_id' => $centroId, 'ano' => $ano]) }}" icon="heroicon-o-table-cells">
                Exportar Excel
            </x-filament::button>
            <x-filament::button tag="a" href="{{ route('relatorios.matriz-assiduidade.pdf', ['centro_id' => $centroId, 'ano' => $ano]) }}" color="gray" icon="heroicon-o-document-arrow-down">
                Baixar PDF
            </x-filament::button>
        </div>
    </div>

    <p class="mt-4 text-sm text-gray-500">Total: {{ count($this->linhas) }} {{ count($this->linhas) === 1 ? 'fiel' : 'fiéis' }}</p>

    <div class="mt-2 overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2 text-right">#</th>
                    <th class="px-3 py-2 text-left">Fiel</th>
                    @if ($this->mostrarFiltroCentro())
                        <th class="px-3 py-2 text-left">Centro</th>
                    @endif
                    @foreach (['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'] as $mesLabel)
                        <th class="px-2 py-2 text-center">{{ $mesLabel }}</th>
                    @endforeach
                    <th class="px-3 py-2 text-center">Segmento</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->linhas as $i => $linha)
                    <tr class="border-t border-gray-100 dark:border-gray-700">
                        <td class="px-3 py-2 text-right text-gray-500">{{ $i + 1 }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $linha['fiel']->nome }}</td>
                        @if ($this->mostrarFiltroCentro())
                            <td class="px-3 py-2 whitespace-nowrap text-gray-500">{{ $linha['fiel']->centros->pluck('nome')->join(', ') }}</td>
                        @endif
                        @foreach ($linha['meses'] as $estado)
                            <td class="px-1 py-2 text-center">
                                @if ($estado === 'pago')
                                    <span class="inline-block h-4 w-4 rounded-full" style="background-color: rgba(var(--success-500), 1)"></span>
                                @elseif ($estado === 'em_aberto')
                                    <span class="inline-block h-4 w-4 rounded-full" style="background-color: rgba(var(--warning-400), 1)"></span>
                                @else
                                    <span class="inline-block h-4 w-4 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                                @endif
                            </td>
                        @endforeach
                        <td class="px-3 py-2 text-center">
                            @if ($linha['segmento'])
                                <x-filament::badge :color="match($linha['segmento']) {
                                    'Assíduo' => 'success',
                                    'Regular' => 'info',
                                    'Irregular' => 'warning',
                                    'Inactivo' => 'danger',
                                }">
                                    {{ $linha['segmento'] }}
                                </x-filament::badge>
                            @else
                                <span class="text-gray-400">{{ $linha['total_pagos'] }}/12</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $this->mostrarFiltroCentro() ? 16 : 15 }}" class="px-3 py-6 text-center text-gray-400">Nenhum fiel encontrado para este filtro.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
