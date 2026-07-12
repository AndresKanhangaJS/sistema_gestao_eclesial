<x-filament-panels::page>
    <div class="mb-4">
        <x-filament::button tag="a" href="{{ route('relatorios.log-auditoria.pdf') }}" color="gray" icon="heroicon-o-document-arrow-down">
            Baixar PDF
        </x-filament::button>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
