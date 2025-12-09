<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Pages;

use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AuthzDashboardPage extends Page
{
    public string $filterMode = 'all';

    public ?string $filterUser = null;

    public ?string $filterPermission = null;

    public string $timeRange = '24h';

    public int $refreshInterval = 30; // seconds

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Authz Dashboard';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament-authz::pages.authz-dashboard';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-authz.navigation.group', 'Authorization');
    }

    public static function canAccess(): bool
    {
        return config('filament-authz.features.audit_logging', true);
    }

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $cacheKey = 'authz_dashboard_stats_'.$this->timeRange;

        return Cache::remember($cacheKey, 60, function () {
            $since = $this->getTimeRangeStart();

            $roleCount = Role::count();
            $permissionCount = Permission::count();

            $auditTable = config('filament-authz.database.tables.audit_logs', 'authz_audit_logs');

            $recentActivity = 0;
            $denialCount = 0;

            if (DB::getSchemaBuilder()->hasTable($auditTable)) {
                $recentActivity = DB::table($auditTable)
                    ->where('created_at', '>=', $since)
                    ->count();

                $denialCount = DB::table($auditTable)
                    ->where('created_at', '>=', $since)
                    ->where('event_type', 'like', '%denied%')
                    ->count();
            }

            return [
                'roles' => $roleCount,
                'permissions' => $permissionCount,
                'recent_activity' => $recentActivity,
                'denials' => $denialCount,
            ];
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentActivity(): array
    {
        $auditTable = config('filament-authz.database.tables.audit_logs', 'authz_audit_logs');

        if (! DB::getSchemaBuilder()->hasTable($auditTable)) {
            return [];
        }

        $query = DB::table($auditTable)
            ->orderBy('created_at', 'desc')
            ->limit(20);

        if ($this->filterMode === 'denials') {
            $query->where('event_type', 'like', '%denied%');
        }

        if ($this->filterUser) {
            $query->where('actor_id', $this->filterUser);
        }

        if ($this->filterPermission) {
            $query->where('metadata', 'like', "%{$this->filterPermission}%");
        }

        $query->where('created_at', '>=', $this->getTimeRangeStart());

        return $query->get()->map(function ($row) {
            return [
                'id' => $row->id,
                'event_type' => $row->event_type,
                'actor_id' => $row->actor_id,
                'subject_type' => $row->subject_type ?? null,
                'subject_id' => $row->subject_id ?? null,
                'metadata' => json_decode($row->metadata ?? '{}', true),
                'created_at' => $row->created_at,
            ];
        })->toArray();
    }

    /**
     * @return array<string, int>
     */
    public function getHourlyBreakdown(): array
    {
        $auditTable = config('filament-authz.database.tables.audit_logs', 'authz_audit_logs');

        if (! DB::getSchemaBuilder()->hasTable($auditTable)) {
            return $this->getEmptyHourlyBreakdown();
        }

        try {
            $since = now()->subHours(24);

            // Try MySQL-compatible query
            $results = DB::table($auditTable)
                ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
                ->where('created_at', '>=', $since)
                ->groupBy('hour')
                ->pluck('count', 'hour')
                ->toArray();

            $breakdown = [];
            for ($i = 0; $i < 24; $i++) {
                $hour = now()->subHours(24 - $i)->format('H');
                $breakdown[$hour.':00'] = $results[(int) $hour] ?? 0;
            }

            return $breakdown;
        } catch (Exception $e) {
            // Fallback for SQLite or other databases without HOUR function
            return $this->getEmptyHourlyBreakdown();
        }
    }

    /**
     * @return array<string, int>
     */
    public function getPermissionUsageHeatmap(): array
    {
        $auditTable = config('filament-authz.database.tables.audit_logs', 'authz_audit_logs');

        if (! DB::getSchemaBuilder()->hasTable($auditTable)) {
            return [];
        }

        try {
            $since = $this->getTimeRangeStart();

            return DB::table($auditTable)
                ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.permission')) as permission, COUNT(*) as count")
                ->where('created_at', '>=', $since)
                ->whereNotNull('metadata')
                ->groupBy('permission')
                ->orderBy('count', 'desc')
                ->limit(20)
                ->pluck('count', 'permission')
                ->toArray();
        } catch (Exception $e) {
            // Fallback for databases without JSON functions
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAnomalies(): array
    {
        $auditTable = config('filament-authz.database.tables.audit_logs', 'authz_audit_logs');

        if (! DB::getSchemaBuilder()->hasTable($auditTable)) {
            return [];
        }

        $since = $this->getTimeRangeStart();

        // Detect unusual patterns: high denial rates per user
        $anomalies = DB::table($auditTable)
            ->selectRaw('actor_id, COUNT(*) as total, SUM(CASE WHEN event_type LIKE "%denied%" THEN 1 ELSE 0 END) as denials')
            ->where('created_at', '>=', $since)
            ->groupBy('actor_id')
            ->havingRaw('denials / total > 0.5 AND total > 5')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                return [
                    'user_id' => $row->actor_id,
                    'total_requests' => $row->total,
                    'denials' => $row->denials,
                    'denial_rate' => round(($row->denials / $row->total) * 100, 1),
                    'severity' => $row->denials > 10 ? 'high' : 'medium',
                ];
            })
            ->toArray();

        return $anomalies;
    }

    public function setFilterMode(string $mode): void
    {
        $this->filterMode = $mode;
    }

    public function setTimeRange(string $range): void
    {
        $this->timeRange = $range;
        Cache::forget('authz_dashboard_stats_'.$range);
    }

    public function refreshData(): void
    {
        Cache::forget('authz_dashboard_stats_'.$this->timeRange);
    }

    /**
     * @return array<string, int>
     */
    protected function getEmptyHourlyBreakdown(): array
    {
        $breakdown = [];
        for ($i = 0; $i < 24; $i++) {
            $hour = now()->subHours(24 - $i)->format('H');
            $breakdown[$hour.':00'] = 0;
        }

        return $breakdown;
    }

    protected function getTimeRangeStart(): \Carbon\Carbon
    {
        return match ($this->timeRange) {
            '1h' => now()->subHour(),
            '24h' => now()->subHours(24),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subHours(24),
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action('refreshData'),

            Action::make('timeRange')
                ->label('Time Range')
                ->icon('heroicon-o-clock')
                ->form([
                    \Filament\Forms\Components\Select::make('range')
                        ->label('Select Time Range')
                        ->options([
                            '1h' => 'Last Hour',
                            '24h' => 'Last 24 Hours',
                            '7d' => 'Last 7 Days',
                            '30d' => 'Last 30 Days',
                        ])
                        ->default($this->timeRange),
                ])
                ->action(fn (array $data) => $this->setTimeRange($data['range'])),

            Action::make('filterMode')
                ->label($this->filterMode === 'denials' ? 'Showing Denials' : 'Showing All')
                ->icon($this->filterMode === 'denials' ? 'heroicon-o-x-circle' : 'heroicon-o-funnel')
                ->color($this->filterMode === 'denials' ? 'danger' : 'gray')
                ->action(fn () => $this->setFilterMode($this->filterMode === 'denials' ? 'all' : 'denials')),
        ];
    }
}
