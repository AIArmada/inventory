<x-filament-panels::page>
    <x-filament-widgets::widgets :columns="$this->getHeaderWidgetsColumns()" :widgets="$this->getHeaderWidgets()" />

    <x-filament-widgets::widgets :columns="$this->getFooterWidgetsColumns()" :widgets="$this->getFooterWidgets()" />
</x-filament-panels::page>