<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Date range info --}}
        <div class="flex items-center justify-between">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                <span class="font-medium">Period:</span>
                {{ \Illuminate\Support\Carbon::parse($this->dateFrom)->format('M j, Y') }}
                -
                {{ \Illuminate\Support\Carbon::parse($this->dateTo)->format('M j, Y') }}
                <span class="text-xs ml-2">({{ \Illuminate\Support\Carbon::parse($this->dateFrom)->diffInDays(\Illuminate\Support\Carbon::parse($this->dateTo)) + 1 }} days)</span>
            </div>
        </div>

        {{-- Stats overview is in header widgets --}}

        {{-- Main content grid --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Conversion Funnel --}}
            @livewire(\AIArmada\FilamentCart\Widgets\ConversionFunnelWidget::class)

            {{-- Value Trends Chart --}}
            @livewire(\AIArmada\FilamentCart\Widgets\ValueTrendChartWidget::class)
        </div>

        {{-- Abandonment Analysis --}}
        @if(config('filament-cart.features.abandonment_tracking', true))
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-1">
                @livewire(\AIArmada\FilamentCart\Widgets\AbandonmentAnalysisWidget::class)
            </div>
        @endif

        {{-- Recovery Performance --}}
        @if(config('filament-cart.features.ai_recovery', true))
            @livewire(\AIArmada\FilamentCart\Widgets\RecoveryPerformanceWidget::class)
        @endif
    </div>
</x-filament-panels::page>
