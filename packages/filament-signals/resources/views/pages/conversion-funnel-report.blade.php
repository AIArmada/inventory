<x-filament-panels::page>
    @php
        $summary = $this->getSummary();
        $stages = $this->getStages();
    @endphp

    <div class="space-y-6">
        <div class="text-sm text-gray-500 dark:text-gray-400">
            {{ \Carbon\CarbonImmutable::parse($this->dateFrom)->format('M j, Y') }} - {{ \Carbon\CarbonImmutable::parse($this->dateTo)->format('M j, Y') }}
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-primary-600">{{ number_format($summary['started']) }}</div>
                    <div class="text-sm text-gray-500">{{ $summary['started_label'] }}</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-info-600">{{ number_format($summary['completed']) }}</div>
                    <div class="text-sm text-gray-500">{{ $summary['completed_label'] }}</div>
                    <div class="text-xs text-gray-400">{{ number_format($summary['start_to_complete_rate'], 2) }}%</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-success-600">{{ number_format($summary['paid']) }}</div>
                    <div class="text-sm text-gray-500">{{ $summary['paid_label'] }}</div>
                    <div class="text-xs text-gray-400">{{ number_format($summary['overall_rate'], 2) }}% overall</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-warning-600">{{ number_format($summary['start_drop_off']) }}</div>
                    <div class="text-sm text-gray-500">Dropped After Start</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-success-700">{{ $this->formatMoney($summary['revenue_minor']) }}</div>
                    <div class="text-sm text-gray-500">{{ $this->monetaryValueLabel() }}</div>
                </div>
            </x-filament::section>
        </div>

        <x-filament::section>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-medium">Stage</th>
                            <th class="px-4 py-3 font-medium">Event</th>
                            <th class="px-4 py-3 font-medium">Count</th>
                            <th class="px-4 py-3 font-medium">From Previous</th>
                            <th class="px-4 py-3 font-medium">From Start</th>
                            <th class="px-4 py-3 font-medium">Drop Off</th>
                            <th class="px-4 py-3 font-medium">{{ $this->monetaryValueLabel() }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($stages as $stage)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $stage['label'] }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $stage['event_name'] }}</td>
                                <td class="px-4 py-3">{{ number_format($stage['count']) }}</td>
                                <td class="px-4 py-3">{{ number_format($stage['rate_from_previous'], 2) }}%</td>
                                <td class="px-4 py-3">{{ number_format($stage['rate_from_start'], 2) }}%</td>
                                <td class="px-4 py-3">{{ number_format($stage['drop_off']) }}</td>
                                <td class="px-4 py-3">{{ $this->formatMoney($stage['revenue_minor']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    Funnel activity will appear here once the selected steps start recording events.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>