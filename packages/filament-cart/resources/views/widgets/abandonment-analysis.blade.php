<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-chart-bar class="h-5 w-5 text-primary-500" />
                <span>Abandonment Analysis</span>
            </div>
        </x-slot>

        @php
            $data = $this->getData();
        @endphp

        <div class="space-y-6">
            {{-- Summary Cards --}}
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Total Abandonments
                    </div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        {{ number_format($data['total']) }}
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Peak Hour
                    </div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        {{ $data['peakHour'] }}
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Peak Day
                    </div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        {{ $data['peakDay'] }}
                    </div>
                </div>
            </div>

            {{-- Tabs --}}
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="flex gap-4" aria-label="Tabs">
                    <button
                        wire:click="setActiveTab('hour')"
                        class="py-2 px-1 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'hour' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' }}"
                    >
                        By Hour
                    </button>
                    <button
                        wire:click="setActiveTab('day')"
                        class="py-2 px-1 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'day' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' }}"
                    >
                        By Day
                    </button>
                    <button
                        wire:click="setActiveTab('value')"
                        class="py-2 px-1 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'value' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' }}"
                    >
                        By Value
                    </button>
                    <button
                        wire:click="setActiveTab('items')"
                        class="py-2 px-1 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'items' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' }}"
                    >
                        By Items
                    </button>
                    <button
                        wire:click="setActiveTab('exit')"
                        class="py-2 px-1 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'exit' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' }}"
                    >
                        Exit Points
                    </button>
                </nav>
            </div>

            {{-- Tab Content --}}
            <div class="min-h-[200px]">
                @if($activeTab === 'hour')
                    {{-- By Hour Chart --}}
                    <div class="space-y-2">
                        @php
                            $maxHour = max($data['byHour']) ?: 1;
                        @endphp
                        @foreach($data['byHour'] as $hour => $count)
                            <div class="flex items-center gap-3">
                                <span class="text-xs text-gray-500 dark:text-gray-400 w-12">
                                    {{ sprintf('%02d:00', $hour) }}
                                </span>
                                <div class="flex-1 h-4 bg-gray-100 dark:bg-gray-700 rounded overflow-hidden">
                                    <div 
                                        class="h-full bg-red-400 rounded transition-all"
                                        style="width: {{ ($count / $maxHour) * 100 }}%"
                                    ></div>
                                </div>
                                <span class="text-xs font-medium text-gray-700 dark:text-gray-300 w-12 text-right">
                                    {{ number_format($count) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @elseif($activeTab === 'day')
                    {{-- By Day Chart --}}
                    <div class="space-y-3">
                        @php
                            $maxDay = max($data['byDayOfWeek']) ?: 1;
                        @endphp
                        @foreach($data['byDayOfWeek'] as $day => $count)
                            <div class="flex items-center gap-3">
                                <span class="text-sm text-gray-500 dark:text-gray-400 w-12">
                                    {{ $day }}
                                </span>
                                <div class="flex-1 h-6 bg-gray-100 dark:bg-gray-700 rounded overflow-hidden">
                                    <div 
                                        class="h-full bg-orange-400 rounded transition-all flex items-center justify-end pr-2"
                                        style="width: {{ ($count / $maxDay) * 100 }}%"
                                    >
                                        @if(($count / $maxDay) > 0.2)
                                            <span class="text-xs text-white font-medium">{{ number_format($count) }}</span>
                                        @endif
                                    </div>
                                </div>
                                @if(($count / $maxDay) <= 0.2)
                                    <span class="text-xs font-medium text-gray-700 dark:text-gray-300 w-12 text-right">
                                        {{ number_format($count) }}
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @elseif($activeTab === 'value')
                    {{-- By Cart Value --}}
                    <div class="space-y-3">
                        @php
                            $maxValue = max($data['byCartValueRange']) ?: 1;
                        @endphp
                        @foreach($data['byCartValueRange'] as $range => $count)
                            <div class="flex items-center gap-3">
                                <span class="text-sm text-gray-500 dark:text-gray-400 w-24">
                                    {{ $range }}
                                </span>
                                <div class="flex-1 h-6 bg-gray-100 dark:bg-gray-700 rounded overflow-hidden">
                                    <div 
                                        class="h-full bg-yellow-400 rounded transition-all"
                                        style="width: {{ ($count / $maxValue) * 100 }}%"
                                    ></div>
                                </div>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300 w-16 text-right">
                                    {{ number_format($count) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @elseif($activeTab === 'items')
                    {{-- By Items Count --}}
                    <div class="space-y-3">
                        @php
                            $maxItems = max($data['byItemsCount']) ?: 1;
                        @endphp
                        @foreach($data['byItemsCount'] as $range => $count)
                            <div class="flex items-center gap-3">
                                <span class="text-sm text-gray-500 dark:text-gray-400 w-24">
                                    {{ $range }}
                                </span>
                                <div class="flex-1 h-6 bg-gray-100 dark:bg-gray-700 rounded overflow-hidden">
                                    <div 
                                        class="h-full bg-purple-400 rounded transition-all"
                                        style="width: {{ ($count / $maxItems) * 100 }}%"
                                    ></div>
                                </div>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300 w-16 text-right">
                                    {{ number_format($count) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @elseif($activeTab === 'exit')
                    {{-- Common Exit Points --}}
                    <div class="space-y-3">
                        @php
                            $maxExit = max($data['commonExitPoints']) ?: 1;
                        @endphp
                        @forelse($data['commonExitPoints'] as $point => $count)
                            <div class="flex items-center gap-3">
                                <span class="text-sm text-gray-500 dark:text-gray-400 flex-1 truncate">
                                    {{ ucfirst(str_replace('_', ' ', $point)) }}
                                </span>
                                <div class="w-48 h-6 bg-gray-100 dark:bg-gray-700 rounded overflow-hidden">
                                    <div 
                                        class="h-full bg-cyan-400 rounded transition-all"
                                        style="width: {{ ($count / $maxExit) * 100 }}%"
                                    ></div>
                                </div>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300 w-16 text-right">
                                    {{ number_format($count) }}
                                </span>
                            </div>
                        @empty
                            <div class="text-center text-gray-500 dark:text-gray-400 py-8">
                                No exit point data available
                            </div>
                        @endforelse
                    </div>
                @endif
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
