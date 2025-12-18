<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Services\ComplianceReportService;
use AIArmada\FilamentAuthz\Services\ContextualAuthorizationService;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use AIArmada\FilamentAuthz\Services\TeamPermissionService;
use AIArmada\FilamentAuthz\Services\TemporalPermissionService;

describe('ComplianceReportService Deep Coverage', function (): void {
    beforeEach(function (): void {
        $this->service = new ComplianceReportService;
    });

    it('exports report to array with nested data', function (): void {
        $report = [
            'summary' => [
                'total_events' => 100,
                'unique_actors' => 10,
                'high_severity_events' => 5,
                'start_date' => now()->subDays(30)->toISOString(),
                'end_date' => now()->toISOString(),
            ],
            'events_by_type' => [
                'login' => 50,
                'access' => 30,
                'permission_change' => 20,
            ],
            'events_by_severity' => [
                'low' => 80,
                'medium' => 15,
                'high' => 5,
            ],
            'security_events' => [
                ['type' => 'failed_login', 'count' => 3],
            ],
        ];

        $exported = $this->service->exportToArray($report);

        // exportToArray returns a flattened array of rows
        expect($exported)->toBeArray();
    });

    it('can be instantiated', function (): void {
        expect($this->service)->toBeInstanceOf(ComplianceReportService::class);
    });

    it('has all expected methods', function (): void {
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
});

describe('ContextualAuthorizationService Deep Coverage', function (): void {
    beforeEach(function (): void {
        $this->aggregator = Mockery::mock(PermissionAggregator::class);
        $this->service = new ContextualAuthorizationService($this->aggregator);
    });

    it('checks permission with empty context', function (): void {
        $user = new class
        {
            public function getKey(): int
            {
                return 1;
            }
        };

        $this->aggregator->shouldReceive('userHasPermission')
            ->with($user, 'test.permission')
            ->andReturn(true);

        $result = $this->service->canWithContext($user, 'test.permission', []);
        expect($result)->toBeTrue();
    });

    it('checks permission when user lacks it', function (): void {
        $user = new class
        {
            public function getKey(): int
            {
                return 2;
            }
        };

        $this->aggregator->shouldReceive('userHasPermission')
            ->with($user, 'restricted.permission')
            ->andReturn(false);

        $result = $this->service->canWithContext($user, 'restricted.permission', []);
        expect($result)->toBeFalse();
    });

    it('has clearCache method that takes user argument', function (): void {
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('clearCache');
        $parameters = $method->getParameters();

        expect(count($parameters))->toBe(1);
    });
});

describe('TeamPermissionService Deep Coverage', function (): void {
    beforeEach(function (): void {
        $aggregator = Mockery::mock(PermissionAggregator::class);
        $aggregator->shouldReceive('userHasPermission')->andReturn(false);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $this->service = new TeamPermissionService($contextualAuth);
    });

    it('can be instantiated with dependencies', function (): void {
        expect($this->service)->toBeInstanceOf(TeamPermissionService::class);
    });

    it('has expected method signatures', function (): void {
        $reflection = new ReflectionClass($this->service);

        // Check method parameter types
        $hasTeamPermission = $reflection->getMethod('hasTeamPermission');
        $parameters = $hasTeamPermission->getParameters();

        expect(count($parameters))->toBeGreaterThanOrEqual(3);
    });

    it('has grantTeamPermission method', function (): void {
        $reflection = new ReflectionClass($this->service);
        expect($reflection->hasMethod('grantTeamPermission'))->toBeTrue();
    });

    it('has revokeTeamPermission method', function (): void {
        $reflection = new ReflectionClass($this->service);
        expect($reflection->hasMethod('revokeTeamPermission'))->toBeTrue();
    });

    it('has getTeamPermissions method', function (): void {
        $reflection = new ReflectionClass($this->service);
        expect($reflection->hasMethod('getTeamPermissions'))->toBeTrue();
    });

    it('has getTeamsWithPermission method', function (): void {
        $reflection = new ReflectionClass($this->service);
        expect($reflection->hasMethod('getTeamsWithPermission'))->toBeTrue();
    });

    it('has revokeAllTeamPermissions method', function (): void {
        $reflection = new ReflectionClass($this->service);
        expect($reflection->hasMethod('revokeAllTeamPermissions'))->toBeTrue();
    });

    it('has copyTeamPermissions method', function (): void {
        $reflection = new ReflectionClass($this->service);
        expect($reflection->hasMethod('copyTeamPermissions'))->toBeTrue();
    });
});

describe('TemporalPermissionService Deep Coverage', function (): void {
    beforeEach(function (): void {
        $aggregator = Mockery::mock(PermissionAggregator::class);
        $aggregator->shouldReceive('userHasPermission')->andReturn(false);
        $contextualAuth = new ContextualAuthorizationService($aggregator);
        $this->service = new TemporalPermissionService($contextualAuth);
    });

    it('can be instantiated with dependencies', function (): void {
        expect($this->service)->toBeInstanceOf(TemporalPermissionService::class);
    });

    it('revokeExpired returns integer', function (): void {
        $result = $this->service->revokeExpired();
        expect($result)->toBeInt();
    });

    it('revokeExpired returns non-negative', function (): void {
        $result = $this->service->revokeExpired();
        expect($result)->toBeGreaterThanOrEqual(0);
    });

    it('has grantTemporaryPermission method', function (): void {
        $reflection = new ReflectionClass($this->service);
        expect($reflection->hasMethod('grantTemporaryPermission'))->toBeTrue();
    });

    it('has grantForDuration method', function (): void {
        $reflection = new ReflectionClass($this->service);
        expect($reflection->hasMethod('grantForDuration'))->toBeTrue();
    });

    it('has grantDuringHours method', function (): void {
        $reflection = new ReflectionClass($this->service);
        expect($reflection->hasMethod('grantDuringHours'))->toBeTrue();
    });

    it('has grantOnDays method', function (): void {
        $reflection = new ReflectionClass($this->service);
        expect($reflection->hasMethod('grantOnDays'))->toBeTrue();
    });

    it('has hasActiveTemporaryPermission method', function (): void {
        $reflection = new ReflectionClass($this->service);
        expect($reflection->hasMethod('hasActiveTemporaryPermission'))->toBeTrue();
    });

    it('has getExpiringPermissions method', function (): void {
        $reflection = new ReflectionClass($this->service);
        expect($reflection->hasMethod('getExpiringPermissions'))->toBeTrue();
    });

    it('has extendPermission method', function (): void {
        $reflection = new ReflectionClass($this->service);
        expect($reflection->hasMethod('extendPermission'))->toBeTrue();
    });
});
