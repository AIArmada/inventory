<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Policy Details Form --}}
        <x-filament::section>
            <x-slot name="heading">Policy Configuration</x-slot>
            {{ $this->form }}
        </x-filament::section>

        {{-- Conditions Builder --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center justify-between">
                    <span>Conditions</span>
                    <x-filament::button wire:click="addCondition" size="sm" icon="heroicon-o-plus">
                        Add Condition
                    </x-filament::button>
                </div>
            </x-slot>

            <div class="space-y-4">
                @forelse($this->conditions as $index => $condition)
                    <div class="flex items-center gap-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="flex-shrink-0">
                            <span class="text-sm font-medium text-gray-500">{{ $index + 1 }}</span>
                        </div>

                        <div class="flex-1 grid grid-cols-4 gap-3">
                            <select wire:change="updateConditionType({{ $index }}, $event.target.value)"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                @foreach($this->getConditionTemplates() as $type => $template)
                                    <option value="{{ $type }}" @selected($condition['type'] === $type)>
                                        {{ $template['label'] }}
                                    </option>
                                @endforeach
                            </select>

                            <select wire:model.live="conditions.{{ $index }}.operator"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                @foreach($this->getOperatorOptions() as $op => $label)
                                    <option value="{{ $op }}">{{ $label }}</option>
                                @endforeach
                            </select>

                            <input type="text" wire:model.live="conditions.{{ $index }}.value" placeholder="Value"
                                class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500" />

                            <div class="flex justify-end">
                                <x-filament::icon-button wire:click="removeCondition({{ $index }})" icon="heroicon-o-trash"
                                    color="danger" size="sm" />
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-gray-500">
                        <x-heroicon-o-funnel class="w-12 h-12 mx-auto mb-2 opacity-50" />
                        <p>No conditions added yet.</p>
                        <p class="text-sm">Click "Add Condition" to start building your policy.</p>
                    </div>
                @endforelse
            </div>
        </x-filament::section>

        {{-- Preview Panel --}}
        <div class="grid grid-cols-2 gap-6">
            <x-filament::section>
                <x-slot name="heading">JSON Preview</x-slot>
                <pre
                    class="text-xs bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto"><code>{{ $this->getPreviewJson() }}</code></pre>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Code Preview</x-slot>
                <pre
                    class="text-xs bg-gray-900 text-blue-400 p-4 rounded-lg overflow-x-auto"><code>{{ $this->getPreviewCode() }}</code></pre>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>