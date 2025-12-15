<?php

declare(strict_types=1);

namespace Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Services\ComplianceReportService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

describe('ComplianceReportService', function (): void {
    beforeEach(function (): void {
        $this->service = new ComplianceReportService;
    });

    describe('generateReport', function (): void {
        it('returns report with required keys', function (): void {
            $start = Carbon::now()->subDays(7);
            $end = Carbon::now();

            // Use 'summary' type to avoid MySQL-specific HOUR() function
            $report = $this->service->generateReport($start, $end, 'summary');

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
        });

        it('returns period with start and end dates', function (): void {
            $start = Carbon::create(2024, 1, 1);
            $end = Carbon::create(2024, 1, 31);

            // Use 'summary' type to avoid MySQL-specific HOUR() function
            $report = $this->service->generateReport($start, $end, 'summary');

            expect($report['period']['start'])->toContain('2024-01-01');
            expect($report['period']['end'])->toContain('2024-01-31');
        });

        it('returns empty security events for summary report type', function (): void {
            $start = Carbon::now()->subDays(7);
            $end = Carbon::now();

            $report = $this->service->generateReport($start, $end, 'summary');

            expect($report['security_events'])->toBe([]);
            expect($report['access_patterns'])->toBe([]);
        });
    });

    describe('getSummary', function (): void {
        it('returns summary with required keys', function (): void {
            $start = Carbon::now()->subDays(7);
            $end = Carbon::now();

            $summary = $this->service->getSummary($start, $end);

            expect($summary)->toHaveKeys([
                'total_events',
                'unique_actors',
                'high_severity_events',
                'access_denials',
                'permission_changes',
                'role_changes',
            ]);
        });

        it('returns zero counts when no events exist', function (): void {
            $start = Carbon::now()->subDays(7);
            $end = Carbon::now();

            $summary = $this->service->getSummary($start, $end);

            expect($summary['total_events'])->toBe(0);
            expect($summary['unique_actors'])->toBe(0);
            expect($summary['high_severity_events'])->toBe(0);
        });
    });

    describe('getEventsByType', function (): void {
        it('returns empty array when no events exist', function (): void {
            $start = Carbon::now()->subDays(7);
            $end = Carbon::now();

            $events = $this->service->getEventsByType($start, $end);

            expect($events)->toBe([]);
        });
    });

    describe('getEventsBySeverity', function (): void {
        it('returns empty array when no events exist', function (): void {
            $start = Carbon::now()->subDays(7);
            $end = Carbon::now();

            $events = $this->service->getEventsBySeverity($start, $end);

            expect($events)->toBe([]);
        });
    });

    describe('getSecurityEvents', function (): void {
        it('returns empty collection when no security events exist', function (): void {
            $start = Carbon::now()->subDays(7);
            $end = Carbon::now();

            $events = $this->service->getSecurityEvents($start, $end);

            expect($events)->toBeInstanceOf(Collection::class);
            expect($events)->toBeEmpty();
        });
    });

    describe('getTopActors', function (): void {
        it('returns empty array when no events exist', function (): void {
            $start = Carbon::now()->subDays(7);
            $end = Carbon::now();

            $actors = $this->service->getTopActors($start, $end);

            expect($actors)->toBe([]);
        });

        it('accepts custom limit parameter', function (): void {
            $start = Carbon::now()->subDays(7);
            $end = Carbon::now();

            $actors = $this->service->getTopActors($start, $end, 5);

            expect($actors)->toBe([]);
        });
    });

    describe('getUserPermissionHistory', function (): void {
        it('returns empty collection when no history exists', function (): void {
            $history = $this->service->getUserPermissionHistory('user-123');

            expect($history)->toBeInstanceOf(Collection::class);
            expect($history)->toBeEmpty();
        });

        it('accepts optional date range', function (): void {
            $start = Carbon::now()->subDays(30);
            $end = Carbon::now();

            $history = $this->service->getUserPermissionHistory('user-123', $start, $end);

            expect($history)->toBeEmpty();
        });
    });

    describe('getRoleHistory', function (): void {
        it('returns empty collection when no history exists', function (): void {
            $history = $this->service->getRoleHistory('role-123');

            expect($history)->toBeInstanceOf(Collection::class);
            expect($history)->toBeEmpty();
        });

        it('accepts optional date range', function (): void {
            $start = Carbon::now()->subDays(30);
            $end = Carbon::now();

            $history = $this->service->getRoleHistory('role-123', $start, $end);

            expect($history)->toBeEmpty();
        });
    });

    describe('exportToArray', function (): void {
        it('converts report to array format', function (): void {
            $report = [
                'summary' => [
                    'total_events' => 100,
                    'unique_actors' => 25,
                    'high_severity_events' => 5,
                ],
                'events_by_type' => [
                    'permission.granted' => 50,
                    'role.assigned' => 30,
                ],
                'events_by_severity' => [
                    'low' => 80,
                    'medium' => 15,
                ],
            ];

            $rows = $this->service->exportToArray($report);

            expect($rows)->toBeArray();
            expect($rows[0])->toMatchArray([
                'section' => 'Summary',
                'metric' => 'Total Events',
                'value' => 100,
            ]);
        });

        it('includes events by type in export', function (): void {
            $report = [
                'summary' => [
                    'total_events' => 10,
                    'unique_actors' => 5,
                    'high_severity_events' => 1,
                ],
                'events_by_type' => [
                    'test_type' => 5,
                ],
                'events_by_severity' => [],
            ];

            $rows = $this->service->exportToArray($report);

            $typeRows = array_filter($rows, fn ($r) => $r['section'] === 'Events By Type');
            expect(count($typeRows))->toBe(1);
        });

        it('includes events by severity in export', function (): void {
            $report = [
                'summary' => [
                    'total_events' => 10,
                    'unique_actors' => 5,
                    'high_severity_events' => 1,
                ],
                'events_by_type' => [],
                'events_by_severity' => [
                    'high' => 3,
                    'low' => 7,
                ],
            ];

            $rows = $this->service->exportToArray($report);

            $severityRows = array_filter($rows, fn ($r) => $r['section'] === 'Events By Severity');
            expect(count($severityRows))->toBe(2);
        });
    });

    describe('exportToCsv', function (): void {
        it('returns csv string with header', function (): void {
            $start = Carbon::now()->subDays(7);
            $end = Carbon::now();

            $csv = $this->service->exportToCsv($start, $end);

            expect($csv)->toContain('ID');
            expect($csv)->toContain('Event Type');
            expect($csv)->toContain('Severity');
            expect($csv)->toContain('Actor ID');
        });

        it('returns empty-ish csv when no logs exist', function (): void {
            $start = Carbon::now()->subDays(7);
            $end = Carbon::now();

            $csv = $this->service->exportToCsv($start, $end);

            // Should only have the header line
            $lines = explode("\n", trim($csv));
            expect(count($lines))->toBe(1);
        });
    });
});
