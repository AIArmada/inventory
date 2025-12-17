<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

use AIArmada\FilamentAuthz\Enums\AuditEventType;
use AIArmada\FilamentAuthz\Enums\AuditSeverity;
use AIArmada\FilamentAuthz\Models\PermissionAuditLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ComplianceReportService
{
    private function hourExpressionSql(): string
    {
        return match (DB::getDriverName()) {
            'pgsql' => 'EXTRACT(HOUR FROM created_at)',
            'sqlite' => "CAST(strftime('%H', created_at) AS INTEGER)",
            default => 'HOUR(created_at)',
        };
    }

    private function dateExpressionSql(): string
    {
        return 'DATE(created_at)';
    }
    /**
     * Generate a compliance report for a date range.
     *
     * @return array<string, mixed>
     */
    public function generateReport(
        Carbon $startDate,
        Carbon $endDate,
        ?string $reportType = 'full'
    ): array {
        return [
            'period' => [
                'start' => $startDate->toIso8601String(),
                'end' => $endDate->toIso8601String(),
            ],
            'summary' => $this->getSummary($startDate, $endDate),
            'events_by_type' => $this->getEventsByType($startDate, $endDate),
            'events_by_severity' => $this->getEventsBySeverity($startDate, $endDate),
            'security_events' => $reportType === 'full' ? $this->getSecurityEvents($startDate, $endDate) : [],
            'access_patterns' => $reportType === 'full' ? $this->getAccessPatterns($startDate, $endDate) : [],
            'top_actors' => $this->getTopActors($startDate, $endDate),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get a summary of audit events.
     *
     * @return array<string, int>
     */
    public function getSummary(Carbon $startDate, Carbon $endDate): array
    {
        $query = PermissionAuditLog::query()
            ->whereBetween('created_at', [$startDate, $endDate]);

        return [
            'total_events' => (clone $query)->count(),
            'unique_actors' => (clone $query)->distinct('actor_id')->count('actor_id'),
            'high_severity_events' => (clone $query)
                ->whereIn('severity', [AuditSeverity::High, AuditSeverity::Critical])
                ->count(),
            'access_denials' => (clone $query)
                ->where('event_type', AuditEventType::AccessDenied)
                ->count(),
            'permission_changes' => (clone $query)
                ->whereIn('event_type', [
                    AuditEventType::PermissionGranted,
                    AuditEventType::PermissionRevoked,
                ])
                ->count(),
            'role_changes' => (clone $query)
                ->whereIn('event_type', [
                    AuditEventType::RoleAssigned,
                    AuditEventType::RoleRemoved,
                    AuditEventType::RoleCreated,
                    AuditEventType::RoleDeleted,
                ])
                ->count(),
        ];
    }

    /**
     * Get events grouped by type.
     *
     * @return array<string, int>
     */
    public function getEventsByType(Carbon $startDate, Carbon $endDate): array
    {
        return PermissionAuditLog::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('event_type', DB::raw('count(*) as count'))
            ->groupBy('event_type')
            ->pluck('count', 'event_type')
            ->toArray();
    }

    /**
     * Get events grouped by severity.
     *
     * @return array<string, int>
     */
    public function getEventsBySeverity(Carbon $startDate, Carbon $endDate): array
    {
        return PermissionAuditLog::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('severity', DB::raw('count(*) as count'))
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();
    }

    /**
     * Get security-relevant events.
     *
     * @return Collection<int, PermissionAuditLog>
     */
    public function getSecurityEvents(Carbon $startDate, Carbon $endDate): Collection
    {
        $securityEventTypes = [
            AuditEventType::AccessDenied,
            AuditEventType::PrivilegeEscalation,
            AuditEventType::SuspiciousActivity,
            AuditEventType::LoginFailed,
            AuditEventType::MfaFailed,
            AuditEventType::UnauthorizedAccess,
        ];

        return PermissionAuditLog::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('event_type', $securityEventTypes)
            ->orderBy('severity', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();
    }

    /**
     * Get access patterns analysis.
     *
     * @return array<string, mixed>
     */
    public function getAccessPatterns(Carbon $startDate, Carbon $endDate): array
    {
        // Peak hours analysis
        $hourExpression = $this->hourExpressionSql();

        $hourlyDistribution = PermissionAuditLog::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw($hourExpression.' as hour, count(*) as count')
            ->groupByRaw($hourExpression)
            ->pluck('count', 'hour')
            ->toArray();

        // Daily distribution
        $dateExpression = $this->dateExpressionSql();

        $dailyDistribution = PermissionAuditLog::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw($dateExpression.' as date, count(*) as count')
            ->groupByRaw($dateExpression)
            ->pluck('count', 'date')
            ->toArray();

        return [
            'hourly_distribution' => $hourlyDistribution,
            'daily_distribution' => $dailyDistribution,
            'peak_hour' => $hourlyDistribution !== [] ? array_search(max($hourlyDistribution), $hourlyDistribution, true) : null,
            'average_daily_events' => $dailyDistribution !== [] ? round(array_sum($dailyDistribution) / count($dailyDistribution), 2) : 0,
        ];
    }

    /**
     * Get top actors by activity.
     *
     * @return array<int, array{actor_id: string, actor_type: string, event_count: int}>
     */
    public function getTopActors(Carbon $startDate, Carbon $endDate, int $limit = 10): array
    {
        return PermissionAuditLog::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('actor_id')
            ->select('actor_id', 'actor_type', DB::raw('count(*) as event_count'))
            ->groupBy('actor_id', 'actor_type')
            ->orderBy('event_count', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get permission changes for a specific user.
     *
     * @return Collection<int, PermissionAuditLog>
     */
    public function getUserPermissionHistory(
        string $userId,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): Collection {
        $query = PermissionAuditLog::query()
            ->where(function ($q) use ($userId): void {
                $q->where('actor_id', $userId)
                    ->orWhere('subject_id', $userId);
            })
            ->whereIn('event_type', [
                AuditEventType::PermissionGranted,
                AuditEventType::PermissionRevoked,
                AuditEventType::RoleAssigned,
                AuditEventType::RoleRemoved,
            ])
            ->orderBy('created_at', 'desc');

        if ($startDate !== null) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate !== null) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query->get();
    }

    /**
     * Get role changes history.
     *
     * @return Collection<int, PermissionAuditLog>
     */
    public function getRoleHistory(
        string $roleId,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): Collection {
        $query = PermissionAuditLog::query()
            ->where('subject_id', $roleId)
            ->whereIn('event_type', [
                AuditEventType::RoleCreated,
                AuditEventType::RoleUpdated,
                AuditEventType::RoleDeleted,
                AuditEventType::RolePermissionsUpdated,
            ])
            ->orderBy('created_at', 'desc');

        if ($startDate !== null) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate !== null) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query->get();
    }

    /**
     * Export report to array format.
     *
     * @param  array<string, mixed>  $report
     * @return array<int, array<string, mixed>>
     */
    public function exportToArray(array $report): array
    {
        $rows = [];

        // Add summary
        $rows[] = ['section' => 'Summary', 'metric' => 'Total Events', 'value' => $report['summary']['total_events']];
        $rows[] = ['section' => 'Summary', 'metric' => 'Unique Actors', 'value' => $report['summary']['unique_actors']];
        $rows[] = ['section' => 'Summary', 'metric' => 'High Severity Events', 'value' => $report['summary']['high_severity_events']];

        // Add events by type
        foreach ($report['events_by_type'] as $type => $count) {
            $rows[] = ['section' => 'Events By Type', 'metric' => $type, 'value' => $count];
        }

        // Add events by severity
        foreach ($report['events_by_severity'] as $severity => $count) {
            $rows[] = ['section' => 'Events By Severity', 'metric' => $severity, 'value' => $count];
        }

        return $rows;
    }

    /**
     * Export audit logs to CSV format.
     */
    public function exportToCsv(Carbon $startDate, Carbon $endDate): string
    {
        $logs = PermissionAuditLog::query()
            ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->orderBy('created_at', 'desc')
            ->get();

        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            return '';
        }

        // Write CSV header
        fputcsv($output, [
            'ID',
            'Event Type',
            'Severity',
            'Actor ID',
            'Actor Type',
            'Subject ID',
            'Subject Type',
            'Permission',
            'Description',
            'IP Address',
            'User Agent',
            'Created At',
        ]);

        // Write data rows
        foreach ($logs as $log) {
            fputcsv($output, [
                $log->id,
                $log->event_type,
                $log->severity,
                $log->actor_id ?? '',
                $log->actor_type ?? '',
                $log->subject_id ?? '',
                $log->subject_type ?? '',
                $log->target_name ?? '',
                $log->getDescription(),
                $log->ip_address ?? '',
                $log->user_agent ?? '',
                $log->created_at?->toIso8601String() ?? '',
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv !== false ? $csv : '';
    }
}
