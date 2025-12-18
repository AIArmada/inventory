<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Enums\AuditEventType;
use AIArmada\FilamentAuthz\Enums\AuditSeverity;
use AIArmada\FilamentAuthz\Models\PermissionAuditLog;
use AIArmada\FilamentAuthz\Pages\AuditLogPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clean up any existing logs
    PermissionAuditLog::query()->delete();
});

describe('AuditLogPage', function () {
    it('has navigation icon', function () {
        expect(AuditLogPage::getNavigationIcon())->toBe('heroicon-o-clipboard-document-list');
    });

    it('has navigation label', function () {
        expect(AuditLogPage::getNavigationLabel())->toBe('Audit Log');
    });

    it('has navigation sort', function () {
        expect(AuditLogPage::getNavigationSort())->toBe(12);
    });

    it('gets navigation group from config', function () {
        config(['filament-authz.navigation.group' => 'Test Group']);
        expect(AuditLogPage::getNavigationGroup())->toBe('Test Group');
    });
});

describe('AuditLogPage::mount', function () {
    it('sets default date range to last 7 days', function () {
        $page = new AuditLogPage();
        $page->mount();

        expect($page->startDate)->toBe(now()->subDays(7)->toDateString());
        expect($page->endDate)->toBe(now()->toDateString());
    });

    it('initializes logs collection', function () {
        $page = new AuditLogPage();
        $page->mount();

        expect($page->logs)->toBeInstanceOf(Collection::class);
    });
});

describe('AuditLogPage::loadLogs', function () {
    it('loads logs within date range', function () {
        // Create log within range
        PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionGranted->value,
            'severity' => AuditSeverity::Low->value,
            'actor_type' => 'App\\Models\\User',
            'actor_id' => '1',
            'action' => 'grant',
            'subject_type' => 'permission',
            'subject_id' => 'test.permission',
            'occurred_at' => now(),
        ]);

        $page = new AuditLogPage();
        $page->startDate = now()->subDay()->toDateString();
        $page->endDate = now()->toDateString();
        $page->loadLogs();

        expect($page->logs)->toHaveCount(1);
    });

    it('filters by event type', function () {
        PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionGranted->value,
            'severity' => AuditSeverity::Low->value,
            'actor_type' => 'App\\Models\\User',
            'actor_id' => '1',
            'action' => 'grant',
            'subject_type' => 'permission',
            'subject_id' => 'test.permission',
            'occurred_at' => now(),
        ]);

        PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionRevoked->value,
            'severity' => AuditSeverity::Medium->value,
            'actor_type' => 'App\\Models\\User',
            'actor_id' => '1',
            'action' => 'revoke',
            'subject_type' => 'permission',
            'subject_id' => 'test.permission',
            'occurred_at' => now(),
        ]);

        $page = new AuditLogPage();
        $page->startDate = now()->subDay()->toDateString();
        $page->endDate = now()->toDateString();
        $page->eventTypeFilter = AuditEventType::PermissionGranted->value;
        $page->loadLogs();

        expect($page->logs)->toHaveCount(1);
        expect($page->logs->first()->event_type)->toBe(AuditEventType::PermissionGranted->value);
    });

    it('filters by severity', function () {
        PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionGranted->value,
            'severity' => AuditSeverity::Low->value,
            'actor_type' => 'App\\Models\\User',
            'actor_id' => '1',
            'action' => 'grant',
            'subject_type' => 'permission',
            'subject_id' => 'test.permission',
            'occurred_at' => now(),
        ]);

        PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionRevoked->value,
            'severity' => AuditSeverity::High->value,
            'actor_type' => 'App\\Models\\User',
            'actor_id' => '1',
            'action' => 'revoke',
            'subject_type' => 'permission',
            'subject_id' => 'test.permission',
            'occurred_at' => now(),
        ]);

        $page = new AuditLogPage();
        $page->startDate = now()->subDay()->toDateString();
        $page->endDate = now()->toDateString();
        $page->severityFilter = AuditSeverity::High->value;
        $page->loadLogs();

        expect($page->logs)->toHaveCount(1);
        expect($page->logs->first()->severity)->toBe(AuditSeverity::High->value);
    });
});

describe('AuditLogPage::filterByEventType', function () {
    it('sets event type filter and reloads', function () {
        $page = new AuditLogPage();
        $page->startDate = now()->subDay()->toDateString();
        $page->endDate = now()->toDateString();

        $page->filterByEventType(AuditEventType::PermissionGranted->value);

        expect($page->eventTypeFilter)->toBe(AuditEventType::PermissionGranted->value);
    });
});

describe('AuditLogPage::filterBySeverity', function () {
    it('sets severity filter and reloads', function () {
        $page = new AuditLogPage();
        $page->startDate = now()->subDay()->toDateString();
        $page->endDate = now()->toDateString();

        $page->filterBySeverity(AuditSeverity::High->value);

        expect($page->severityFilter)->toBe(AuditSeverity::High->value);
    });
});

describe('AuditLogPage::clearFilters', function () {
    it('clears all filters', function () {
        $page = new AuditLogPage();
        $page->startDate = now()->subDay()->toDateString();
        $page->endDate = now()->toDateString();
        $page->eventTypeFilter = AuditEventType::PermissionGranted->value;
        $page->severityFilter = AuditSeverity::High->value;

        $page->clearFilters();

        expect($page->eventTypeFilter)->toBeNull();
        expect($page->severityFilter)->toBeNull();
    });
});

describe('AuditLogPage::getEventTypeOptions', function () {
    it('returns event type options array', function () {
        $page = new AuditLogPage();
        $options = $page->getEventTypeOptions();

        expect($options)->toBeArray();
        expect($options)->not->toBeEmpty();
        expect(array_keys($options))->toContain(AuditEventType::PermissionGranted->value);
    });
});

describe('AuditLogPage::getStatistics', function () {
    it('returns statistics array with correct structure', function () {
        $page = new AuditLogPage();
        $page->logs = collect();

        $stats = $page->getStatistics();

        expect($stats)->toHaveKeys(['total', 'by_severity', 'by_category']);
        expect($stats['total'])->toBe(0);
        expect($stats['by_severity'])->toBeArray();
        expect($stats['by_category'])->toBeArray();
    });

    it('calculates statistics from logs', function () {
        $log1 = PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionGranted->value,
            'severity' => AuditSeverity::Low->value,
            'actor_type' => 'App\\Models\\User',
            'actor_id' => '1',
            'action' => 'grant',
            'subject_type' => 'permission',
            'subject_id' => 'test.permission',
            'occurred_at' => now(),
        ]);

        $log2 = PermissionAuditLog::create([
            'event_type' => AuditEventType::PermissionRevoked->value,
            'severity' => AuditSeverity::High->value,
            'actor_type' => 'App\\Models\\User',
            'actor_id' => '1',
            'action' => 'revoke',
            'subject_type' => 'permission',
            'subject_id' => 'test.permission',
            'occurred_at' => now(),
        ]);

        $page = new AuditLogPage();
        $page->logs = collect([$log1, $log2]);

        $stats = $page->getStatistics();

        expect($stats['total'])->toBe(2);
        expect($stats['by_severity'])->toHaveKey(AuditSeverity::Low->value);
        expect($stats['by_severity'])->toHaveKey(AuditSeverity::High->value);
    });
});
