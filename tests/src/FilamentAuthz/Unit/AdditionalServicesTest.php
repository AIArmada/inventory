<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Services\ComplianceReportService;
use AIArmada\FilamentAuthz\Services\ContextualAuthorizationService;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use AIArmada\FilamentAuthz\Services\TeamPermissionService;
use AIArmada\FilamentAuthz\Services\TemporalPermissionService;

describe('ComplianceReportService', function (): void {
    beforeEach(function (): void {
        $this->service = new ComplianceReportService;
    });

    it('can be instantiated', function (): void {
        expect($this->service)->toBeInstanceOf(ComplianceReportService::class);
    });

    it('has all required methods', function (): void {
        expect(method_exists($this->service, 'generateReport'))->toBeTrue()
            ->and(method_exists($this->service, 'getSummary'))->toBeTrue()
            ->and(method_exists($this->service, 'getEventsByType'))->toBeTrue()
            ->and(method_exists($this->service, 'getEventsBySeverity'))->toBeTrue()
            ->and(method_exists($this->service, 'getSecurityEvents'))->toBeTrue()
            ->and(method_exists($this->service, 'getAccessPatterns'))->toBeTrue()
            ->and(method_exists($this->service, 'getTopActors'))->toBeTrue()
            ->and(method_exists($this->service, 'getUserPermissionHistory'))->toBeTrue()
            ->and(method_exists($this->service, 'getRoleHistory'))->toBeTrue()
            ->and(method_exists($this->service, 'exportToArray'))->toBeTrue()
            ->and(method_exists($this->service, 'exportToCsv'))->toBeTrue();
    });

    it('exports report to array format', function (): void {
        $report = [
            'summary' => [
                'total_events' => 100,
                'unique_actors' => 10,
                'high_severity_events' => 5,
            ],
            'events_by_type' => ['login' => 50, 'access' => 30],
            'events_by_severity' => ['low' => 80, 'high' => 20],
        ];

        $exported = $this->service->exportToArray($report);

        expect($exported)->toBeArray()
            ->and($exported)->not->toBeEmpty();
    });
});

describe('ContextualAuthorizationService', function (): void {
    beforeEach(function (): void {
        $this->aggregator = Mockery::mock(PermissionAggregator::class);
        $this->service = new ContextualAuthorizationService($this->aggregator);
    });

    it('can be instantiated', function (): void {
        expect($this->service)->toBeInstanceOf(ContextualAuthorizationService::class);
    });

    it('has all required methods', function (): void {
        expect(method_exists($this->service, 'canWithContext'))->toBeTrue()
            ->and(method_exists($this->service, 'canForResource'))->toBeTrue()
            ->and(method_exists($this->service, 'canInTeam'))->toBeTrue()
            ->and(method_exists($this->service, 'canInTenant'))->toBeTrue()
            ->and(method_exists($this->service, 'grantScopedPermission'))->toBeTrue()
            ->and(method_exists($this->service, 'revokeScopedPermission'))->toBeTrue()
            ->and(method_exists($this->service, 'getScopedPermissions'))->toBeTrue()
            ->and(method_exists($this->service, 'getPermissionScopes'))->toBeTrue()
            ->and(method_exists($this->service, 'clearCache'))->toBeTrue();
    });

    it('returns true when user has global permission', function (): void {
        $user = new class
        {
            public function getKey(): int
            {
                return 1;
            }
        };

        $this->aggregator->shouldReceive('userHasPermission')
            ->with($user, 'test.view')
            ->andReturn(true);

        $result = $this->service->canWithContext($user, 'test.view', []);

        expect($result)->toBeTrue();
    });

    it('returns false when user has no permission', function (): void {
        $user = new class
        {
            public function getKey(): int
            {
                return 1;
            }
        };

        $this->aggregator->shouldReceive('userHasPermission')
            ->with($user, 'test.view')
            ->andReturn(false);

        $result = $this->service->canWithContext($user, 'test.view', []);

        expect($result)->toBeFalse();
    });
});

describe('TeamPermissionService', function (): void {
    beforeEach(function (): void {
        $aggregator = Mockery::mock(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $this->service = new TeamPermissionService($contextualAuth);
    });

    it('can be instantiated', function (): void {
        expect($this->service)->toBeInstanceOf(TeamPermissionService::class);
    });

    it('has all required methods', function (): void {
        expect(method_exists($this->service, 'hasTeamPermission'))->toBeTrue()
            ->and(method_exists($this->service, 'grantTeamPermission'))->toBeTrue()
            ->and(method_exists($this->service, 'revokeTeamPermission'))->toBeTrue()
            ->and(method_exists($this->service, 'getTeamPermissions'))->toBeTrue()
            ->and(method_exists($this->service, 'getTeamsWithPermission'))->toBeTrue()
            ->and(method_exists($this->service, 'revokeAllTeamPermissions'))->toBeTrue()
            ->and(method_exists($this->service, 'copyTeamPermissions'))->toBeTrue();
    });
});

describe('TemporalPermissionService', function (): void {
    beforeEach(function (): void {
        $aggregator = Mockery::mock(PermissionAggregator::class);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $this->service = new TemporalPermissionService($contextualAuth);
    });

    it('can be instantiated', function (): void {
        expect($this->service)->toBeInstanceOf(TemporalPermissionService::class);
    });

    it('has all required methods', function (): void {
        expect(method_exists($this->service, 'grantTemporaryPermission'))->toBeTrue()
            ->and(method_exists($this->service, 'grantForDuration'))->toBeTrue()
            ->and(method_exists($this->service, 'grantDuringHours'))->toBeTrue()
            ->and(method_exists($this->service, 'grantOnDays'))->toBeTrue()
            ->and(method_exists($this->service, 'hasActiveTemporaryPermission'))->toBeTrue()
            ->and(method_exists($this->service, 'getExpiringPermissions'))->toBeTrue()
            ->and(method_exists($this->service, 'extendPermission'))->toBeTrue()
            ->and(method_exists($this->service, 'revokeExpired'))->toBeTrue();
    });

    it('revokes expired permissions', function (): void {
        // This tests the revokeExpired method which returns an int
        $result = $this->service->revokeExpired();

        expect($result)->toBeInt()
            ->and($result)->toBeGreaterThanOrEqual(0);
    });
});
