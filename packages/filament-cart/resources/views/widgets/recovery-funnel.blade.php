<div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
        Recovery Funnel
    </h3>

    @php
        $data = $this->getData();
    @endphp

    <div class="space-y-3">
        @foreach ($data['stages'] as $index => $stage)
            <div class="relative">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ $stage['name'] }}
                    </span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ number_format($stage['value']) }}
                        @if ($stage['percent'] < 100)
                            <span class="text-xs">({{ $stage['percent'] }}%)</span>
                        @endif
                    </span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-6 overflow-hidden">
                    <div
                        class="{{ $stage['color'] }} h-6 rounded-full transition-all duration-500 ease-out flex items-center justify-end pr-2"
                        style="width: {{ max($stage['width'], 5) }}%"
                    >
                        @if ($stage['width'] > 20)
                            <span class="text-xs text-white font-medium">
                                {{ number_format($stage['value']) }}
                            </span>
                        @endif
                    </div>
                </div>
                @if (isset($stage['dropoff']) && $stage['dropoff'] > 0)
                    <div class="text-xs text-red-500 dark:text-red-400 mt-0.5">
                        ↓ {{ number_format($stage['dropoff']) }} dropped off
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                Overall Conversion
            </span>
            <span class="text-lg font-bold {{ $data['overallConversion'] >= 5 ? 'text-green-600' : ($data['overallConversion'] >= 2 ? 'text-yellow-600' : 'text-red-600') }}">
                {{ $data['overallConversion'] }}%
            </span>
        </div>
    </div>
</div>
