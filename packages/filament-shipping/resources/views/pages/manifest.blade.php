<x-filament-panels::page>
    <x-filament-panels::form wire:submit="submit">
        {{ $this->form }}
    </x-filament-panels::form>

    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>