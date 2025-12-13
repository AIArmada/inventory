<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Health Stats --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-filament::section>
                <div class="text-center">
                    <div class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                        {{ $health?->total_webhooks ?? 0 }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Total Webhooks') }}
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-3xl font-bold text-success-600 dark:text-success-400">
                        {{ number_format(($health?->success_rate ?? 0) * 100, 1) }}%
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Success Rate') }}
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-3xl font-bold text-warning-600 dark:text-warning-400">
                        {{ $health?->pending_count ?? 0 }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Pending') }}
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-3xl font-bold text-danger-600 dark:text-danger-400">
                        {{ $health?->failed_count ?? 0 }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('Failed') }}
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- Event Distribution & Failure Breakdown --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Event Distribution') }}
                </x-slot>

                @if(empty($eventDistribution))
                    <div class="text-center py-6">
                        <x-heroicon-o-signal class="mx-auto h-12 w-12 text-gray-400" />
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('No events recorded') }}</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @php
                            $maxCount = max($eventDistribution ?: [1]);
                        @endphp
                        @foreach($eventDistribution as $event => $count)
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-700 dark:text-gray-300">{{ $event }}</span>
                                    <span class="text-gray-500">{{ $count }}</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                    <div 
                                        class="bg-primary-600 h-2 rounded-full" 
                                        style="width: {{ ($count / $maxCount) * 100 }}%"
                                    ></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    {{ __('Failure Breakdown') }}
                </x-slot>

                @if(empty($failureBreakdown))
                    <div class="text-center py-6">
                        <x-heroicon-o-check-circle class="mx-auto h-12 w-12 text-success-400" />
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('No failures recorded') }}</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @php
                            $maxFailure = max($failureBreakdown ?: [1]);
                        @endphp
                        @foreach($failureBreakdown as $reason => $count)
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-700 dark:text-gray-300 truncate">{{ $reason }}</span>
                                    <span class="text-gray-500">{{ $count }}</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                    <div 
                                        class="bg-danger-600 h-2 rounded-full" 
                                        style="width: {{ ($count / $maxFailure) * 100 }}%"
                                    ></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        </div>

        {{-- Webhook Table --}}
        <x-filament::section>
            <x-slot name="heading">
                {{ __('Recent Webhooks') }}
            </x-slot>

            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
