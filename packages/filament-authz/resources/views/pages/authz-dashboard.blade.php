<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Stats Overview --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            @php $stats = $this->getStats(); @endphp

            <x-filament::section>
                <div class="text-center">
                    <div class="text-3xl font-bold text-primary-500">{{ $stats['roles'] }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Roles</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-3xl font-bold text-success-500">{{ $stats['permissions'] }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Permissions</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-3xl font-bold text-warning-500">{{ $stats['recent_activity'] }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Recent Activity</div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-center">
                    <div class="text-3xl font-bold text-danger-500">{{ $stats['denials'] }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Denials</div>
                </div>
            </x-filament::section>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Recent Activity --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-clock class="w-5 h-5" />
                        Recent Activity
                    </div>
                </x-slot>

                <div class="space-y-2 max-h-96 overflow-y-auto">
                    @forelse($this->getRecentActivity() as $activity)
                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div class="flex items-center gap-3">
                                @if(str_contains($activity['event_type'] ?? '', 'denied'))
                                    <x-heroicon-o-x-circle class="w-5 h-5 text-danger-500" />
                                @else
                                    <x-heroicon-o-check-circle class="w-5 h-5 text-success-500" />
                                @endif
                                <div>
                                    <div class="text-sm font-medium">{{ $activity['event_type'] ?? 'Unknown' }}</div>
                                    <div class="text-xs text-gray-500">User: {{ $activity['actor_id'] ?? 'N/A' }}</div>
                                </div>
                            </div>
                            <div class="text-xs text-gray-400">
                                {{ \Carbon\Carbon::parse($activity['created_at'])->diffForHumans() }}
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            <x-heroicon-o-inbox class="w-12 h-12 mx-auto mb-2 opacity-50" />
                            <p>No recent activity</p>
                        </div>
                    @endforelse
                </div>
            </x-filament::section>

            {{-- Permission Usage Heatmap --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-fire class="w-5 h-5" />
                        Permission Usage
                    </div>
                </x-slot>

                <div class="space-y-2 max-h-96 overflow-y-auto">
                    @php $heatmap = $this->getPermissionUsageHeatmap(); @endphp
                    @php $maxCount = max($heatmap ?: [1]); @endphp

                    @forelse($heatmap as $permission => $count)
                        <div class="flex items-center gap-3">
                            <div class="flex-1 text-sm truncate" title="{{ $permission }}">
                                {{ $permission ?: 'Unknown' }}
                            </div>
                            <div class="w-32">
                                <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                    <div class="h-full bg-primary-500 rounded-full"
                                        style="width: {{ ($count / $maxCount) * 100 }}%"></div>
                                </div>
                            </div>
                            <div class="w-12 text-right text-sm text-gray-500">{{ $count }}</div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            <x-heroicon-o-chart-bar class="w-12 h-12 mx-auto mb-2 opacity-50" />
                            <p>No usage data available</p>
                        </div>
                    @endforelse
                </div>
            </x-filament::section>
        </div>

        {{-- Anomaly Detection --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-warning-500" />
                    Anomaly Detection
                </div>
            </x-slot>

            <div class="space-y-3">
                @forelse($this->getAnomalies() as $anomaly)
                    <div
                        class="flex items-center justify-between p-4 bg-{{ $anomaly['severity'] === 'high' ? 'danger' : 'warning' }}-50 dark:bg-{{ $anomaly['severity'] === 'high' ? 'danger' : 'warning' }}-900/20 border border-{{ $anomaly['severity'] === 'high' ? 'danger' : 'warning' }}-200 dark:border-{{ $anomaly['severity'] === 'high' ? 'danger' : 'warning' }}-800 rounded-lg">
                        <div class="flex items-center gap-3">
                            @if($anomaly['severity'] === 'high')
                                <x-heroicon-o-shield-exclamation class="w-6 h-6 text-danger-500" />
                            @else
                                <x-heroicon-o-exclamation-circle class="w-6 h-6 text-warning-500" />
                            @endif
                            <div>
                                <div class="font-medium">User ID: {{ $anomaly['user_id'] }}</div>
                                <div class="text-sm text-gray-500">
                                    {{ $anomaly['denials'] }} denials out of {{ $anomaly['total_requests'] }} requests
                                    ({{ $anomaly['denial_rate'] }}% denial rate)
                                </div>
                            </div>
                        </div>
                        <span
                            class="px-2 py-1 text-xs rounded-full bg-{{ $anomaly['severity'] === 'high' ? 'danger' : 'warning' }}-100 text-{{ $anomaly['severity'] === 'high' ? 'danger' : 'warning' }}-800">
                            {{ ucfirst($anomaly['severity']) }} Risk
                        </span>
                    </div>
                @empty
                    <div class="text-center py-8 text-gray-500">
                        <x-heroicon-o-shield-check class="w-12 h-12 mx-auto mb-2 opacity-50 text-success-500" />
                        <p class="text-success-600">No anomalies detected</p>
                        <p class="text-sm">All authorization patterns appear normal</p>
                    </div>
                @endforelse
            </div>
        </x-filament::section>

        {{-- Hourly Chart --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-chart-bar class="w-5 h-5" />
                    Hourly Activity (Last 24 Hours)
                </div>
            </x-slot>

            <div class="flex items-end gap-1 h-32">
                @php $hourly = $this->getHourlyBreakdown(); @endphp
                @php $maxHourly = max($hourly ?: [1]); @endphp

                @foreach($hourly as $hour => $count)
                    <div class="flex-1 group relative">
                        <div class="bg-primary-500 hover:bg-primary-600 rounded-t transition-all cursor-pointer"
                            style="height: {{ $maxHourly > 0 ? ($count / $maxHourly) * 100 : 0 }}%"
                            title="{{ $hour }}: {{ $count }} events"></div>
                        <div
                            class="invisible group-hover:visible absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap">
                            {{ $hour }}: {{ $count }}
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    </div>

    @push('scripts')
        <script>
            // Auto-refresh every 30 seconds
            setInterval(() => {
                @this.call('refreshData');
            }, {{ $this->refreshInterval * 1000 }});
        </script>
    @endpush
</x-filament-panels::page>