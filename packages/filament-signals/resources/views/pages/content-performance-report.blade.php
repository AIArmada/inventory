<x-filament-panels::page>
    @php
        $summary = $this->getSummary();
    @endphp

    <div class="space-y-6">
        <div class="text-sm text-gray-500 dark:text-gray-400">
            {{ \Carbon\CarbonImmutable::parse($this->dateFrom)->format('M j, Y') }} - {{ \Carbon\CarbonImmutable::parse($this->dateTo)->format('M j, Y') }}
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-primary-600">{{ number_format($summary['paths']) }}</div>
                    <div class="text-sm text-gray-500">Paths</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-info-600">{{ number_format($summary['views']) }}</div>
                    <div class="text-sm text-gray-500">Views</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-success-600">{{ number_format($summary['conversions']) }}</div>
                    <div class="text-sm text-gray-500">{{ $this->outcomesLabel() }}</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-success-700">{{ $this->formatMoney($summary['revenue_minor']) }}</div>
                    <div class="text-sm text-gray-500">{{ $this->monetaryValueLabel() }}</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-warning-600">{{ number_format($summary['avg_conversion_rate'], 2) }}%</div>
                    <div class="text-sm text-gray-500">{{ $this->averageOutcomeRateLabel() }}</div>
                </div>
            </x-filament::section>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>