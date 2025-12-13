<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Key Metrics --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-filament::section>
                <div class="text-center">
                    <div class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                        {{ $metrics?->revenue?->currency ?? 'MYR' }} {{ number_format(($metrics?->revenue?->total_revenue ?? 0) / 100, 2) }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Total Revenue') }}
                    </div>
                    @if(isset($metrics?->revenue?->growth_percentage))
                        <div class="text-xs {{ $metrics->revenue->growth_percentage >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                            {{ $metrics->revenue->growth_percentage >= 0 ? '+' : '' }}{{ number_format($metrics->revenue->growth_percentage, 1) }}%
                        </div>
                    @endif
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                        {{ $metrics?->transactions?->total_count ?? 0 }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Transactions') }}
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-3xl font-bold text-success-600 dark:text-success-400">
                        {{ number_format(($metrics?->transactions?->success_rate ?? 0) * 100, 1) }}%
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Success Rate') }}
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                        {{ $metrics?->revenue?->currency ?? 'MYR' }} {{ number_format(($metrics?->transactions?->average_value ?? 0) / 100, 2) }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Avg. Transaction') }}
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- Revenue Trend Chart --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('Revenue Trend (:period Days)', ['period' => $period]) }}
            </x-slot>

            @if(empty($revenueTrend))
                <div class="text-center py-6">
                    <x-heroicon-o-chart-bar class="mx-auto h-12 w-12 text-gray-400" />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('No data available') }}</p>
                </div>
            @else
                <div class="h-64 flex items-end gap-1">
                    @php
                        $maxRevenue = max(array_column($revenueTrend, 'revenue') ?: [1]);
                    @endphp
                    @foreach($revenueTrend as $data)
                        <div class="flex-1 flex flex-col items-center">
                            <div 
                                class="w-full bg-primary-500 rounded-t transition-all hover:bg-primary-600" 
                                style="height: {{ $maxRevenue > 0 ? ($data['revenue'] / $maxRevenue) * 100 : 0 }}%"
                                title="{{ $data['period'] }}: {{ number_format($data['revenue'] / 100, 2) }}"
                            ></div>
                            @if(count($revenueTrend) <= 14)
                                <span class="text-xs text-gray-500 mt-1 truncate w-full text-center">
                                    {{ \Carbon\Carbon::parse($data['period'])->format('d') }}
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        {{-- Payment Methods & Status Breakdown --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Payment Methods') }}
                </x-slot>

                @if(empty($metrics?->payment_method_breakdown))
                    <div class="text-center py-6">
                        <x-heroicon-o-credit-card class="mx-auto h-12 w-12 text-gray-400" />
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('No payment data') }}</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @php
                            $totalPayments = array_sum(array_column($metrics->payment_method_breakdown, 'count'));
                        @endphp
                        @foreach($metrics->payment_method_breakdown as $method)
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-700 dark:text-gray-300">{{ ucfirst($method['payment_method'] ?? 'Unknown') }}</span>
                                    <span class="text-gray-500">
                                        {{ $method['count'] }} ({{ number_format(($method['revenue'] ?? 0) / 100, 2) }})
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                    <div 
                                        class="bg-primary-600 h-2 rounded-full" 
                                        style="width: {{ $totalPayments > 0 ? ($method['count'] / $totalPayments) * 100 : 0 }}%"
                                    ></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Transaction Status') }}
                </x-slot>

                @if(!isset($metrics?->transactions))
                    <div class="text-center py-6">
                        <x-heroicon-o-chart-pie class="mx-auto h-12 w-12 text-gray-400" />
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('No transaction data') }}</p>
                    </div>
                @else
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-3 bg-success-50 dark:bg-success-900/20 rounded-lg">
                            <div class="flex items-center gap-2">
                                <x-heroicon-o-check-circle class="h-5 w-5 text-success-600" />
                                <span class="text-sm font-medium text-success-800 dark:text-success-200">{{ __('Completed') }}</span>
                            </div>
                            <span class="text-lg font-bold text-success-600">{{ $metrics->transactions->completed_count ?? 0 }}</span>
                        </div>

                        <div class="flex items-center justify-between p-3 bg-warning-50 dark:bg-warning-900/20 rounded-lg">
                            <div class="flex items-center gap-2">
                                <x-heroicon-o-clock class="h-5 w-5 text-warning-600" />
                                <span class="text-sm font-medium text-warning-800 dark:text-warning-200">{{ __('Pending') }}</span>
                            </div>
                            <span class="text-lg font-bold text-warning-600">{{ $metrics->transactions->pending_count ?? 0 }}</span>
                        </div>

                        <div class="flex items-center justify-between p-3 bg-danger-50 dark:bg-danger-900/20 rounded-lg">
                            <div class="flex items-center gap-2">
                                <x-heroicon-o-x-circle class="h-5 w-5 text-danger-600" />
                                <span class="text-sm font-medium text-danger-800 dark:text-danger-200">{{ __('Failed') }}</span>
                            </div>
                            <span class="text-lg font-bold text-danger-600">{{ $metrics->transactions->failed_count ?? 0 }}</span>
                        </div>

                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div class="flex items-center gap-2">
                                <x-heroicon-o-arrow-uturn-left class="h-5 w-5 text-gray-600" />
                                <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ __('Refunded') }}</span>
                            </div>
                            <span class="text-lg font-bold text-gray-600">{{ $metrics->transactions->refunded_count ?? 0 }}</span>
                        </div>
                    </div>
                @endif
            </x-filament::section>
        </div>

        {{-- Failure Analysis --}}
        @if(!empty($metrics?->failure_analysis))
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Failure Analysis') }}
                </x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left border-b dark:border-gray-700">
                                <th class="pb-2 font-medium text-gray-700 dark:text-gray-300">{{ __('Reason') }}</th>
                                <th class="pb-2 font-medium text-gray-700 dark:text-gray-300 text-right">{{ __('Count') }}</th>
                                <th class="pb-2 font-medium text-gray-700 dark:text-gray-300 text-right">{{ __('Lost Revenue') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y dark:divide-gray-700">
                            @foreach($metrics->failure_analysis as $failure)
                                <tr>
                                    <td class="py-2 text-gray-900 dark:text-gray-100">{{ $failure['reason'] ?? 'Unknown' }}</td>
                                    <td class="py-2 text-right text-gray-600 dark:text-gray-400">{{ $failure['count'] ?? 0 }}</td>
                                    <td class="py-2 text-right text-danger-600">
                                        {{ number_format(($failure['lost_revenue'] ?? 0) / 100, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
