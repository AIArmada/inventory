<x-filament-panels::page>
    @php
        $summary = $this->getSummary();
        $rows = $this->getRows();
    @endphp

    <div class="space-y-6">
        <div class="text-sm text-gray-500 dark:text-gray-400">
            {{ \Carbon\CarbonImmutable::parse($this->dateFrom)->format('M j, Y') }} - {{ \Carbon\CarbonImmutable::parse($this->dateTo)->format('M j, Y') }}
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-primary-600">{{ number_format($summary['goals']) }}</div>
                    <div class="text-sm text-gray-500">Active Goals</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-info-600">{{ number_format($summary['goal_hits']) }}</div>
                    <div class="text-sm text-gray-500">Goal Hits</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-2xl font-bold text-success-600">{{ number_format($summary['visitors']) }}</div>
                    <div class="text-sm text-gray-500">Visitors</div>
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
                    <div class="text-2xl font-bold text-warning-600">{{ number_format($summary['avg_goal_rate'], 2) }}%</div>
                    <div class="text-sm text-gray-500">Avg Goal Rate</div>
                </div>
            </x-filament::section>
        </div>

        <x-filament::section>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3 font-medium">Goal</th>
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th class="px-4 py-3 font-medium">Property</th>
                            <th class="px-4 py-3 font-medium">Event</th>
                            <th class="px-4 py-3 font-medium">Hits</th>
                            <th class="px-4 py-3 font-medium">Visitors</th>
                            <th class="px-4 py-3 font-medium">Goal Rate</th>
                            <th class="px-4 py-3 font-medium">{{ $this->monetaryValueLabel() }}</th>
                            <th class="px-4 py-3 font-medium">Last Hit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $row['name'] }}</td>
                                <td class="px-4 py-3">{{ str_replace('_', ' ', ucfirst($row['goal_type'])) }}</td>
                                <td class="px-4 py-3">{{ $row['tracked_property_name'] ?? 'All Properties' }}</td>
                                <td class="px-4 py-3">
                                    <div>{{ $row['event_name'] }}</div>
                                    @if ($row['event_category'])
                                        <div class="text-xs text-gray-400">{{ $row['event_category'] }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ number_format($row['goal_hits']) }}</td>
                                <td class="px-4 py-3">{{ number_format($row['visitors']) }}</td>
                                <td class="px-4 py-3">{{ number_format($row['goal_rate'], 2) }}%</td>
                                <td class="px-4 py-3">{{ $this->formatMoney($row['revenue_minor']) }}</td>
                                <td class="px-4 py-3">{{ $row['last_hit_at'] ? $this->formatAggregateTimestamp($row['last_hit_at']) : 'Never' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    Goal performance will appear here once active goals and matching events have been recorded.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>