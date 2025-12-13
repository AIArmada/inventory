<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-funnel class="h-5 w-5 text-primary-500" />
                <span>Conversion Funnel</span>
            </div>
        </x-slot>

        @php
            $data = $this->getData();
        @endphp

        <div class="space-y-4">
            {{-- Funnel Visualization --}}
            <div class="space-y-3">
                @foreach($data['stages'] as $index => $stage)
                    <div class="relative">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $stage['name'] }}
                            </span>
                            <div class="flex items-center gap-3">
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ number_format($stage['value']) }}
                                </span>
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full 
                                    {{ $stage['percent'] >= 50 ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : '' }}
                                    {{ $stage['percent'] >= 20 && $stage['percent'] < 50 ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300' : '' }}
                                    {{ $stage['percent'] < 20 ? 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300' : '' }}
                                ">
                                    {{ $stage['percent'] }}%
                                </span>
                            </div>
                        </div>
                        
                        {{-- Progress bar --}}
                        <div class="w-full h-8 bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden relative">
                            <div 
                                class="{{ $stage['color'] }} h-full transition-all duration-500 rounded-lg flex items-center justify-center"
                                style="width: {{ max($stage['width'], 1) }}%"
                            >
                                @if($stage['width'] > 15)
                                    <span class="text-xs font-medium text-white drop-shadow">
                                        {{ number_format($stage['value']) }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Drop-off indicator --}}
                        @if(isset($stage['dropoff']) && $stage['dropoff'] > 0)
                            <div class="absolute -right-2 top-1/2 transform translate-x-full -translate-y-1/2">
                                <div class="flex items-center gap-1 text-xs text-red-500 dark:text-red-400">
                                    <x-heroicon-s-arrow-down class="h-3 w-3" />
                                    <span>-{{ number_format($stage['dropoff']) }}</span>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Arrow between stages --}}
                    @if($index < count($data['stages']) - 1)
                        <div class="flex justify-center">
                            <x-heroicon-o-chevron-down class="h-4 w-4 text-gray-400" />
                        </div>
                    @endif
                @endforeach
            </div>

            {{-- Summary --}}
            <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        Overall Drop-off Rate
                    </span>
                    <span class="text-lg font-bold {{ $data['overallDropOff'] > 80 ? 'text-red-500' : ($data['overallDropOff'] > 50 ? 'text-yellow-500' : 'text-green-500') }}">
                        {{ $data['overallDropOff'] }}%
                    </span>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
