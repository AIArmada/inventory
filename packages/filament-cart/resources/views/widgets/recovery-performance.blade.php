<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-arrow-path class="h-5 w-5 text-success-500" />
                <span>Recovery Performance</span>
            </div>
        </x-slot>

        @php
            $data = $this->getData();
            $summary = $data['summary'];
        @endphp

        <div class="space-y-6">
            {{-- Summary Stats --}}
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Total Abandoned
                    </div>
                    <div class="text-xl font-bold text-gray-900 dark:text-white mt-1">
                        {{ number_format($summary['total_abandoned']) }}
                    </div>
                </div>
                
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Recovery Attempts
                    </div>
                    <div class="text-xl font-bold text-gray-900 dark:text-white mt-1">
                        {{ number_format($summary['recovery_attempts']) }}
                    </div>
                </div>
                
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Recovered
                    </div>
                    <div class="text-xl font-bold text-success-500 mt-1">
                        {{ number_format($summary['successful_recoveries']) }}
                    </div>
                </div>
                
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Recovery Rate
                    </div>
                    <div class="text-xl font-bold {{ $summary['recovery_rate'] >= 10 ? 'text-success-500' : ($summary['recovery_rate'] >= 5 ? 'text-warning-500' : 'text-danger-500') }} mt-1">
                        {{ $summary['recovery_rate'] }}%
                    </div>
                </div>
                
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Recovered Revenue
                    </div>
                    <div class="text-xl font-bold text-success-500 mt-1">
                        ${{ number_format($summary['recovered_revenue'] / 100, 2) }}
                    </div>
                </div>
                
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Unreached Carts
                    </div>
                    <div class="text-xl font-bold text-warning-500 mt-1">
                        {{ number_format($summary['unreached_carts']) }}
                    </div>
                </div>
            </div>

            {{-- Potential Revenue Alert --}}
            @if($data['potentialRevenue'] > 0)
                <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                    <div class="flex items-center gap-3">
                        <x-heroicon-o-currency-dollar class="h-8 w-8 text-yellow-500" />
                        <div>
                            <div class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                Potential Revenue Opportunity
                            </div>
                            <div class="text-lg font-bold text-yellow-900 dark:text-yellow-100">
                                ${{ number_format($data['potentialRevenue'] / 100, 2) }}
                            </div>
                            <div class="text-xs text-yellow-600 dark:text-yellow-400 mt-1">
                                Based on average recovery value of unrecovered carts
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Strategy Performance --}}
            @if(count($data['strategies']) > 0)
                <div>
                    <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4">
                        Performance by Strategy
                    </h4>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="text-left py-2 px-3 font-medium text-gray-500 dark:text-gray-400">Strategy</th>
                                    <th class="text-right py-2 px-3 font-medium text-gray-500 dark:text-gray-400">Attempts</th>
                                    <th class="text-right py-2 px-3 font-medium text-gray-500 dark:text-gray-400">Conversions</th>
                                    <th class="text-right py-2 px-3 font-medium text-gray-500 dark:text-gray-400">Revenue</th>
                                    <th class="text-right py-2 px-3 font-medium text-gray-500 dark:text-gray-400">Rate</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($data['strategies'] as $strategy)
                                    <tr>
                                        <td class="py-3 px-3">
                                            <div class="flex items-center gap-2">
                                                <span class="w-2 h-2 rounded-full bg-{{ $strategy['color'] }}-400"></span>
                                                <span class="font-medium text-gray-900 dark:text-white">
                                                    {{ $strategy['name'] }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="py-3 px-3 text-right text-gray-700 dark:text-gray-300">
                                            {{ number_format($strategy['attempts']) }}
                                        </td>
                                        <td class="py-3 px-3 text-right text-gray-700 dark:text-gray-300">
                                            {{ number_format($strategy['conversions']) }}
                                        </td>
                                        <td class="py-3 px-3 text-right text-gray-700 dark:text-gray-300">
                                            ${{ number_format($strategy['revenue'] / 100, 2) }}
                                        </td>
                                        <td class="py-3 px-3 text-right">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                                {{ $strategy['rate'] >= 15 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '' }}
                                                {{ $strategy['rate'] >= 5 && $strategy['rate'] < 15 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : '' }}
                                                {{ $strategy['rate'] < 5 ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : '' }}
                                            ">
                                                {{ $strategy['rate'] }}%
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="text-center text-gray-500 dark:text-gray-400 py-8">
                    <x-heroicon-o-arrow-path class="h-12 w-12 mx-auto mb-3 opacity-50" />
                    <p>No recovery strategy data available for this period.</p>
                    <p class="text-sm mt-1">Recovery attempts will appear here once campaigns are active.</p>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
