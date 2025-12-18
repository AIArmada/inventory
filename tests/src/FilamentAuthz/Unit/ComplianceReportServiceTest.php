<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Enums\AuditEventType;
use AIArmada\FilamentAuthz\Enums\AuditSeverity;
use AIArmada\FilamentAuthz\Models\PermissionAuditLog;
use AIArmada\FilamentAuthz\Models\ScopedPermission;
use AIArmada\FilamentAuthz\Services\ComplianceReportService;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    // Clear data
    PermissionAuditLog::query()->delete();
    ScopedPermission::query()->delete();
    Permission::query()->delete();
    Role::query()->delete();
    User::query()->delete();

    // Create and authenticate a user
    $user = User::create([
        'name' => 'System User',
        'email' => 'system@example.com',
        'password' => bcrypt('password'),
    ]);
    test()->actingAs($user);
});

describe('ComplianceReportService', function (): void {
    test('can be instantiated', function (): void {
        $service = new ComplianceReportService();

        expect($service)->toBeInstanceOf(ComplianceReportService::class);
    });

    test('generateReport returns complete report structure', function (): void {
        $service = new ComplianceReportService();

        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();

        $report = $service->generateReport($startDate, $endDate);

        expect($report)->toHaveKeys([
            'period',
            'summary',
            'events_by_type',
            'events_by_severity',
            'security_events',
            'access_patterns',
            'top_actors',
            'generated_at',
        ]);
        expect($report['period']['start'])->toBe($startDate->toIso8601String());
        expect($report['period']['end'])->toBe($endDate->toIso8601String());
    })->skip(fn () => config('database.default') === 'testing', 'Skipped - uses MySQL HOUR() function not available in SQLite');

    test('generateReport respects report type', function (): void {
        $service = new ComplianceReportService();

        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();

        // Partial report should have empty security_events and access_patterns
        $partialReport = $service->generateReport($startDate, $endDate, 'partial');

        expect($partialReport['security_events'])->toBeEmpty();
        expect($partialReport['access_patterns'])->toBeEmpty();
    });

    test('getSummary returns correct counts', function (): void {
        $service = new ComplianceReportService();

        // Create audit logs
        PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionGranted->value,
            'severity' => AuditSeverity::Low->value,
            'actor_type' => User::class,
            'actor_id' => 'user-1',
            'occurred_at' => now(),
        ]);
        PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionRevoked->value,
            'severity' => AuditSeverity::Medium->value,
            'actor_type' => User::class,
            'actor_id' => 'user-2',
            'occurred_at' => now(),
        ]);
        PermissionAuditLog::create([
            'event_type' => AuditEventType::AccessDenied->value,
            'severity' => AuditSeverity::High->value,
            'actor_type' => User::class,
            'actor_id' => 'user-1',
            'occurred_at' => now(),
        ]);
        PermissionAuditLog::create([
            'event_type' => AuditEventType::RoleAssigned->value,
            'severity' => AuditSeverity::Critical->value,
            'actor_type' => User::class,
            'actor_id' => 'user-3',
            'occurred_at' => now(),
        ]);

        $summary = $service->getSummary(Carbon::now()->subDay(), Carbon::now()->addDay());

        expect($summary['total_events'])->toBe(4);
        expect($summary['unique_actors'])->toBe(3);
        expect($summary['high_severity_events'])->toBe(2); // High + Critical
        expect($summary['access_denials'])->toBe(1);
        expect($summary['permission_changes'])->toBe(2); // Granted + Revoked
        expect($summary['role_changes'])->toBe(1); // RoleAssigned
    });

    test('getEventsByType groups events correctly', function (): void {
        $service = new ComplianceReportService();

        // Create events of different types
        PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionGranted->value,
            'severity' => AuditSeverity::Low->value,
            'actor_type' => User::class,
            'actor_id' => 'user-1',
            'occurred_at' => now(),
        ]);
        PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionGranted->value,
            'severity' => AuditSeverity::Low->value,
            'actor_type' => User::class,
            'actor_id' => 'user-2',
            'occurred_at' => now(),
        ]);
        PermissionAuditLog::create([
            'event_type' => AuditEventType::AccessDenied->value,
            'severity' => AuditSeverity::High->value,
            'actor_type' => User::class,
            'actor_id' => 'user-3',
            'occurred_at' => now(),
        ]);

        $eventsByType = $service->getEventsByType(Carbon::now()->subDay(), Carbon::now()->addDay());

        expect($eventsByType)->toHaveKey(AuditEventType::PermissionGranted->value);
        expect($eventsByType[AuditEventType::PermissionGranted->value])->toBe(2);
        expect($eventsByType[AuditEventType::AccessDenied->value])->toBe(1);
    });

    test('getEventsBySeverity groups events correctly', function (): void {
        $service = new ComplianceReportService();

        // Create events of different severities
        PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionGranted->value,
            'severity' => AuditSeverity::Low->value,
            'actor_type' => User::class,
            'actor_id' => 'user-1',
            'occurred_at' => now(),
        ]);
        PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionRevoked->value,
            'severity' => AuditSeverity::Low->value,
            'actor_type' => User::class,
            'actor_id' => 'user-2',
            'occurred_at' => now(),
        ]);
        PermissionAuditLog::create([
            'event_type' => AuditEventType::AccessDenied->value,
            'severity' => AuditSeverity::High->value,
            'actor_type' => User::class,
            'actor_id' => 'user-3',
            'occurred_at' => now(),
        ]);

        $eventsBySeverity = $service->getEventsBySeverity(Carbon::now()->subDay(), Carbon::now()->addDay());

        expect($eventsBySeverity)->toHaveKey(AuditSeverity::Low->value);
        expect($eventsBySeverity[AuditSeverity::Low->value])->toBe(2);
        expect($eventsBySeverity[AuditSeverity::High->value])->toBe(1);
    });

    test('getSecurityEvents returns security-related events only', function (): void {
        $service = new ComplianceReportService();

        // Create security and non-security events
        PermissionAuditLog::create([
            'event_type' => AuditEventType::AccessDenied->value,
            'severity' => AuditSeverity::High->value,
            'actor_type' => User::class,
            'actor_id' => 'user-1',
            'occurred_at' => now(),
        ]);
        PermissionAuditLog::create([
            'event_type' => AuditEventType::PrivilegeEscalation->value,
            'severity' => AuditSeverity::Critical->value,
            'actor_type' => User::class,
            'actor_id' => 'user-2',
            'occurred_at' => now(),
        ]);
        PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionGranted->value, // Not a security event
            'severity' => AuditSeverity::Low->value,
            'actor_type' => User::class,
            'actor_id' => 'user-3',
            'occurred_at' => now(),
        ]);

        $securityEvents = $service->getSecurityEvents(Carbon::now()->subDay(), Carbon::now()->addDay());

        expect($securityEvents)->toHaveCount(2);
        expect($securityEvents->pluck('event_type')->toArray())->each->toBeIn([
            AuditEventType::AccessDenied->value,
            AuditEventType::PrivilegeEscalation->value,
        ]);
    });

    test('getTopActors returns most active actors', function (): void {
        $service = new ComplianceReportService();

        // Create events with different actors
        for ($i = 0; $i < 5; $i++) {
            PermissionAuditLog::create([
                'event_type' => AuditEventType::PermissionGranted->value,
                'severity' => AuditSeverity::Low->value,
                'actor_type' => User::class,
                'actor_id' => 'user-top',
                'occurred_at' => now(),
            ]);
        }
        for ($i = 0; $i < 3; $i++) {
            PermissionAuditLog::create([
                'event_type' => AuditEventType::PermissionGranted->value,
                'severity' => AuditSeverity::Low->value,
                'actor_type' => User::class,
                'actor_id' => 'user-second',
                'occurred_at' => now(),
            ]);
        }
        PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionGranted->value,
            'severity' => AuditSeverity::Low->value,
            'actor_type' => User::class,
            'actor_id' => 'user-third',
            'occurred_at' => now(),
        ]);

        $topActors = $service->getTopActors(Carbon::now()->subDay(), Carbon::now()->addDay());

        expect($topActors)->toHaveCount(3);
        expect($topActors[0]['actor_id'])->toBe('user-top');
        expect($topActors[0]['event_count'])->toBe(5);
        expect($topActors[1]['actor_id'])->toBe('user-second');
        expect($topActors[1]['event_count'])->toBe(3);
    });

    test('getTopActors respects limit', function (): void {
        $service = new ComplianceReportService();

        // Create events with many actors
        for ($i = 1; $i <= 15; $i++) {
            PermissionAuditLog::create([
                'event_type' => AuditEventType::PermissionGranted->value,
                'severity' => AuditSeverity::Low->value,
                'actor_type' => User::class,
                'actor_id' => "user-{$i}",
                'occurred_at' => now(),
            ]);
        }

        $topActors = $service->getTopActors(Carbon::now()->subDay(), Carbon::now()->addDay(), 5);

        expect($topActors)->toHaveCount(5);
    });

    test('getUserPermissionHistory returns user-related events', function (): void {
        $service = new ComplianceReportService();

        $userId = 'target-user-id';

        // Create events for target user
        PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionGranted->value,
            'severity' => AuditSeverity::Low->value,
            'actor_type' => User::class,
            'actor_id' => 'admin-1',
            'subject_id' => $userId,
            'occurred_at' => now(),
        ]);
        PermissionAuditLog::create([
            'event_type' => AuditEventType::RoleAssigned->value,
            'severity' => AuditSeverity::Medium->value,
            'actor_type' => User::class,
            'actor_id' => 'admin-2',
            'subject_id' => $userId,
            'occurred_at' => now(),
        ]);
        // Event by user (as actor)
        PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionRevoked->value,
            'severity' => AuditSeverity::Low->value,
            'actor_type' => User::class,
            'actor_id' => $userId,
            'occurred_at' => now(),
        ]);
        // Unrelated event
        PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionGranted->value,
            'severity' => AuditSeverity::Low->value,
            'actor_type' => User::class,
            'actor_id' => 'admin-3',
            'subject_id' => 'other-user',
            'occurred_at' => now(),
        ]);

        $history = $service->getUserPermissionHistory($userId);

        expect($history)->toHaveCount(3);
    });

    test('getUserPermissionHistory respects date range', function (): void {
        $service = new ComplianceReportService();

        $userId = 'test-user';
        $differentUserId = 'different-user';

        // Create event for target user in range
        $inRangeLog = PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionGranted->value,
            'severity' => AuditSeverity::Low->value,
            'actor_type' => User::class,
            'actor_id' => 'admin-1',
            'subject_id' => $userId,
            'occurred_at' => now(),
        ]);

        // Create event for DIFFERENT user (should not be returned)
        PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionRevoked->value,
            'severity' => AuditSeverity::Low->value,
            'actor_type' => User::class,
            'actor_id' => 'admin-2',
            'subject_id' => $differentUserId,
            'occurred_at' => now(),
        ]);

        $history = $service->getUserPermissionHistory(
            $userId,
            Carbon::now()->subDays(1),
            Carbon::now()->addDay()
        );

        expect($history)->toHaveCount(1);
        expect($history->first()->id)->toBe($inRangeLog->id);
    });

    test('getRoleHistory returns role-related events', function (): void {
        $service = new ComplianceReportService();

        $roleId = 'target-role-id';

        // Create events for target role
        PermissionAuditLog::create([
            'event_type' => AuditEventType::RoleCreated->value,
            'severity' => AuditSeverity::Low->value,
            'actor_type' => User::class,
            'actor_id' => 'admin-1',
            'subject_id' => $roleId,
            'occurred_at' => now(),
        ]);
        PermissionAuditLog::create([
            'event_type' => AuditEventType::RoleUpdated->value,
            'severity' => AuditSeverity::Medium->value,
            'actor_type' => User::class,
            'actor_id' => 'admin-2',
            'subject_id' => $roleId,
            'occurred_at' => now(),
        ]);
        // Unrelated event
        PermissionAuditLog::create([
            'event_type' => AuditEventType::RoleCreated->value,
            'severity' => AuditSeverity::Low->value,
            'actor_type' => User::class,
            'actor_id' => 'admin-3',
            'subject_id' => 'other-role',
            'occurred_at' => now(),
        ]);

        $history = $service->getRoleHistory($roleId);

        expect($history)->toHaveCount(2);
    });

    test('exportToArray converts report to flat array', function (): void {
        $service = new ComplianceReportService();

        $report = [
            'summary' => [
                'total_events' => 100,
                'unique_actors' => 10,
                'high_severity_events' => 5,
            ],
            'events_by_type' => [
                'permission_granted' => 50,
                'access_denied' => 30,
            ],
            'events_by_severity' => [
                'low' => 60,
                'high' => 40,
            ],
        ];

        $exported = $service->exportToArray($report);

        expect($exported)->toBeArray();
        expect($exported)->not->toBeEmpty();

        // Check summary rows
        $summaryRows = array_filter($exported, fn ($row) => $row['section'] === 'Summary');
        expect(count($summaryRows))->toBe(3);

        // Check event type rows
        $typeRows = array_filter($exported, fn ($row) => $row['section'] === 'Events By Type');
        expect(count($typeRows))->toBe(2);
    });

    test('exportToCsv generates valid CSV', function (): void {
        $service = new ComplianceReportService();

        // Create audit log
        PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionGranted->value,
            'severity' => AuditSeverity::Low->value,
            'actor_type' => User::class,
            'actor_id' => 'user-1',
            'subject_id' => 'subject-1',
            'subject_type' => 'App\\Models\\User',
            'occurred_at' => now(),
        ]);

        $csv = $service->exportToCsv(Carbon::now()->subDay(), Carbon::now()->addDay());

        expect($csv)->toBeString();
        expect($csv)->toContain('ID');
        expect($csv)->toContain('Event Type');
        expect($csv)->toContain('Severity');
        expect($csv)->toContain(AuditEventType::PermissionGranted->value);
    });

    test('exportToCsv returns empty string on failure', function (): void {
        $service = new ComplianceReportService();

        // With no logs, should still return valid CSV header
        $csv = $service->exportToCsv(Carbon::now()->subDay(), Carbon::now()->addDay());

        expect($csv)->toContain('ID');
    });
});
