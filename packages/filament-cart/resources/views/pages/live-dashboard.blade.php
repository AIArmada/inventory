<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header with last update time --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <x-heroicon-o-signal class="w-4 h-4 text-green-500 animate-pulse" />
                <span>Live updates every 10 seconds</span>
            </div>
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Last update: <span x-data="{ time: new Date().toLocaleTimeString() }" x-text="time" x-init="setInterval(() => time = new Date().toLocaleTimeString(), 1000)"></span>
            </div>
        </div>

        {{-- Stats section --}}
        @if(count($this->getHeaderWidgets()))
            <x-filament-widgets::widgets
                :widgets="$this->getHeaderWidgets()"
                :columns="$this->getHeaderWidgetsColumns()"
            />
        @endif

        {{-- Activity & Alerts section --}}
        @if(count($this->getFooterWidgets()))
            <x-filament-widgets::widgets
                :widgets="$this->getFooterWidgets()"
                :columns="$this->getFooterWidgetsColumns()"
            />
        @endif
    </div>
</x-filament-panels::page>
