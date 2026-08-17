<x-filament-panels::page.simple>
    <div class="flex items-start gap-x-3 rounded-lg bg-success-50 p-4 ring-1 ring-inset ring-success-600/20 dark:bg-success-500/10 dark:ring-success-500/25">
        <x-filament::icon
            icon="heroicon-o-check-circle"
            class="h-6 w-6 shrink-0 text-success-600 dark:text-success-400"
        />

        <div>
            <p class="text-sm font-medium text-success-800 dark:text-success-200">
                Login efetuado com sucesso!
            </p>
            <p class="text-sm text-success-700 dark:text-success-300">
                Bem-vindo(a), {{ auth()->user()->name }}.
            </p>
        </div>
    </div>

    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
        Por segurança, precisa de escolher uma senha pessoal antes de continuar.
    </p>

    <form wire:submit="guardar" class="mt-6 space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit" class="w-full">
            Alterar senha
        </x-filament::button>
    </form>
</x-filament-panels::page.simple>
