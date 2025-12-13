<x-filament-panels::page>
    @php
        $summary = $this->getAgingSummary();
        $currency = config('docs.defaults.currency', 'MYR');
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-success-600">
                    {{ $summary['current']['count'] }}
                </div>
                <div class="text-sm text-gray-500">Current</div>
                <div class="text-lg font-semibold">
                    {{ $currency }} {{ number_format($summary['current']['amount'], 2) }}
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-warning-600">
                    {{ $summary['1-30']['count'] }}
                </div>
                <div class="text-sm text-gray-500">1-30 Days</div>
                <div class="text-lg font-semibold">
                    {{ $currency }} {{ number_format($summary['1-30']['amount'], 2) }}
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-orange-600">
                    {{ $summary['31-60']['count'] }}
                </div>
                <div class="text-sm text-gray-500">31-60 Days</div>
                <div class="text-lg font-semibold">
                    {{ $currency }} {{ number_format($summary['31-60']['amount'], 2) }}
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-danger-600">
                    {{ $summary['61-90']['count'] }}
                </div>
                <div class="text-sm text-gray-500">61-90 Days</div>
                <div class="text-lg font-semibold">
                    {{ $currency }} {{ number_format($summary['61-90']['amount'], 2) }}
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-2xl font-bold text-danger-700">
                    {{ $summary['90+']['count'] }}
                </div>
                <div class="text-sm text-gray-500">90+ Days</div>
                <div class="text-lg font-semibold">
                    {{ $currency }} {{ number_format($summary['90+']['amount'], 2) }}
                </div>
            </div>
        </x-filament::section>
    </div>

    {{ $this->table }}
</x-filament-panels::page>