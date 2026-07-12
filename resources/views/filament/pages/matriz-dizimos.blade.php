<x-filament-panels::page>
    <div class="flex flex-wrap gap-4">
        <div class="w-48">
            <x-filament::input.wrapper>
                <select wire:model.live="centroId" class="fi-select-input block w-full">
                    @foreach ($this->getCentrosDisponiveis() as $id => $nome)
                        <option value="{{ $id }}">{{ $nome }}</option>
                    @endforeach
                </select>
            </x-filament::input.wrapper>
        </div>
        <div class="w-32">
            <x-filament::input.wrapper>
                <select wire:model.live="ano" class="fi-select-input block w-full">
                    @foreach ($this->getAnosDisponiveis() as $ano)
                        <option value="{{ $ano }}">{{ $ano }}</option>
                    @endforeach
                </select>
            </x-filament::input.wrapper>
        </div>
    </div>

    <div class="mt-4 flex gap-4 text-sm">
        <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded-full bg-success-500"></span> Pago</span>
        <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded-full bg-warning-400"></span> Em aberto</span>
        <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded-full bg-gray-300 dark:bg-gray-600"></span> Não vinculado</span>
    </div>

    <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-3 py-2 text-left">Fiel</th>
                    @foreach (['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'] as $mesLabel)
                        <th class="px-2 py-2 text-center">{{ $mesLabel }}</th>
                    @endforeach
                    <th class="px-3 py-2 text-center">Segmento</th>
                    <th class="px-3 py-2 text-center">Acções</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->matriz as $linha)
                    <tr class="border-t border-gray-100 dark:border-gray-700">
                        <td class="px-3 py-2 whitespace-nowrap">{{ $linha['fiel']->nome }}</td>
                        @foreach ($linha['meses'] as $estado)
                            <td class="px-1 py-2 text-center">
                                @if ($estado === 'pago')
                                    <span class="inline-block h-4 w-4 rounded-full bg-success-500" title="Pago"></span>
                                @elseif ($estado === 'em_aberto')
                                    <span class="inline-block h-4 w-4 rounded-full bg-warning-400" title="Em aberto"></span>
                                @else
                                    <span class="inline-block h-4 w-4 rounded-full bg-gray-300 dark:bg-gray-600" title="Não vinculado"></span>
                                @endif
                            </td>
                        @endforeach
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
                                <span class="text-gray-400">{{ $linha['total_pagos'] }}/12</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-center">
                            <x-filament::button
                                size="xs"
                                wire:click="mountAction('lancarLote', { fielId: {{ $linha['fiel']->id }} })"
                            >
                                Lançar em lote
                            </x-filament::button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="15" class="px-3 py-6 text-center text-gray-400">
                            Nenhum fiel vinculado a este centro neste ano.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
