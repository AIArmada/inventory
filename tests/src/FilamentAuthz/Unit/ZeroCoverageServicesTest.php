<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Enums\PolicyCombiningAlgorithm;
use AIArmada\FilamentAuthz\Enums\PolicyDecision;
use AIArmada\FilamentAuthz\Services\ContextualAuthorizationService;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use AIArmada\FilamentAuthz\Services\PermissionGroupService;
use AIArmada\FilamentAuthz\Services\PermissionImpactAnalyzer;
use AIArmada\FilamentAuthz\Services\PermissionRegistry;
use AIArmada\FilamentAuthz\Services\PermissionTester;
use AIArmada\FilamentAuthz\Services\PolicyEngine;
use AIArmada\FilamentAuthz\Services\RoleInheritanceService;
use AIArmada\FilamentAuthz\Services\RoleTemplateService;
use Illuminate\Support\Facades\Cache;

describe('PermissionGroupService', function (): void {
    beforeEach(function (): void {
        $this->service = new PermissionGroupService;
    });

    it('can be instantiated', function (): void {
        expect($this->service)->toBeInstanceOf(PermissionGroupService::class);
    });

    it('clears cache on operations', function (): void {
        Cache::shouldReceive('forget')
            ->with('permissions:groups:hierarchy_tree')
            ->once();

        $this->service->clearCache();
    });
});

describe('PermissionImpactAnalyzer', function (): void {
    it('can be instantiated', function (): void {
        $roleInheritance = Mockery::mock(RoleInheritanceService::class);
        $analyzer = new PermissionImpactAnalyzer($roleInheritance);

        expect($analyzer)->toBeInstanceOf(PermissionImpactAnalyzer::class);
    });

    it('has all required analysis methods', function (): void {
        $roleInheritance = Mockery::mock(RoleInheritanceService::class);
        $analyzer = new PermissionImpactAnalyzer($roleInheritance);

        expect(method_exists($analyzer, 'analyzePermissionGrant'))->toBeTrue()
            ->and(method_exists($analyzer, 'analyzePermissionRevoke'))->toBeTrue()
            ->and(method_exists($analyzer, 'analyzeRoleDeletion'))->toBeTrue()
            ->and(method_exists($analyzer, 'analyzeHierarchyChange'))->toBeTrue()
            ->and(method_exists($analyzer, 'analyzeBulkChange'))->toBeTrue();
    });
});

describe('PermissionRegistry', function (): void {
    beforeEach(function (): void {
        $this->registry = new PermissionRegistry;
    });

    it('can be instantiated', function (): void {
        expect($this->registry)->toBeInstanceOf(PermissionRegistry::class);
    });

    it('registers a permission', function (): void {
        $result = $this->registry->register('user.view', 'View users', 'users', 'user');

        expect($result)->toBe($this->registry)
            ->and($this->registry->isRegistered('user.view'))->toBeTrue();
    });

    it('registers resource permissions', function (): void {
        $result = $this->registry->registerResource('user', ['viewAny', 'view', 'create'], 'users');

        expect($result)->toBe($this->registry)
            ->and($this->registry->isRegistered('user.viewAny'))->toBeTrue()
            ->and($this->registry->isRegistered('user.view'))->toBeTrue()
            ->and($this->registry->isRegistered('user.create'))->toBeTrue();
    });

    it('registers wildcard permission', function (): void {
        $result = $this->registry->registerWildcard('user', 'All user permissions', 'users');

        expect($result)->toBe($this->registry)
            ->and($this->registry->isRegistered('user.*'))->toBeTrue();
    });

    it('gets all definitions', function (): void {
        $this->registry->register('test.permission', 'Test', null, null);

        $definitions = $this->registry->getDefinitions();

        expect($definitions)->toBeArray()
            ->and($definitions)->toHaveKey('test.permission');
    });

    it('gets definition by name', function (): void {
        $this->registry->register('test.permission', 'Test description', 'test-group', 'test');

        $definition = $this->registry->getDefinition('test.permission');

        expect($definition)->toBeArray()
            ->and($definition['name'])->toBe('test.permission')
            ->and($definition['description'])->toBe('Test description')
            ->and($definition['group'])->toBe('test-group')
            ->and($definition['resource'])->toBe('test');
    });

    it('returns null for unknown definition', function (): void {
        $definition = $this->registry->getDefinition('unknown.permission');

        expect($definition)->toBeNull();
    });

    it('clears registry', function (): void {
        $this->registry->register('test.permission', 'Test', null, null);
        $this->registry->clear();

        expect($this->registry->getDefinitions())->toBe([]);
    });

    it('loads from config', function (): void {
        $config = [
            'user.view' => ['description' => 'View users', 'group' => 'users', 'resource' => 'user'],
            'user.create' => ['description' => 'Create users', 'group' => 'users', 'resource' => 'user'],
        ];

        $this->registry->loadFromConfig($config);

        expect($this->registry->isRegistered('user.view'))->toBeTrue()
            ->and($this->registry->isRegistered('user.create'))->toBeTrue();
    });

    it('exports definitions', function (): void {
        $this->registry->register('test.export', 'Export test', 'group', 'resource');

        $exported = $this->registry->export();

        expect($exported)->toBeArray()
            ->and($exported)->toHaveKey('test.export');
    });

    it('groups by resource', function (): void {
        $this->registry->register('user.view', 'View', null, 'user');
        $this->registry->register('user.create', 'Create', null, 'user');
        $this->registry->register('order.view', 'View', null, 'order');

        $grouped = $this->registry->groupByResource();

        expect($grouped)->toHaveKey('user')
            ->and($grouped)->toHaveKey('order')
            ->and($grouped['user'])->toHaveCount(2)
            ->and($grouped['order'])->toHaveCount(1);
    });

    it('groups by group', function (): void {
        $this->registry->register('user.view', 'View', 'admin', 'user');
        $this->registry->register('user.create', 'Create', 'admin', 'user');
        $this->registry->register('report.view', 'View', 'reports', 'report');

        $grouped = $this->registry->groupByGroup();

        expect($grouped)->toHaveKey('admin')
            ->and($grouped)->toHaveKey('reports')
            ->and($grouped['admin'])->toHaveCount(2)
            ->and($grouped['reports'])->toHaveCount(1);
    });

    it('gets all resources', function (): void {
        $this->registry->register('user.view', null, null, 'user');
        $this->registry->register('order.view', null, null, 'order');

        $resources = $this->registry->getResources();

        expect($resources)->toContain('user')
            ->and($resources)->toContain('order');
    });

    it('gets all groups', function (): void {
        $this->registry->register('user.view', null, 'admin', null);
        $this->registry->register('order.view', null, 'reports', null);

        $groups = $this->registry->getGroups();

        expect($groups)->toContain('admin')
            ->and($groups)->toContain('reports');
    });
});

describe('PermissionTester', function (): void {
    beforeEach(function (): void {
        $this->aggregator = Mockery::mock(PermissionAggregator::class);
        $this->policyEngine = Mockery::mock(PolicyEngine::class);
        $this->contextualAuth = Mockery::mock(ContextualAuthorizationService::class);

        $this->tester = new PermissionTester(
            $this->aggregator,
            $this->policyEngine,
            $this->contextualAuth
        );
    });

    it('can be instantiated', function (): void {
        expect($this->tester)->toBeInstanceOf(PermissionTester::class);
    });

    it('tests user permission - allowed via aggregator', function (): void {
        $user = new stdClass;

        $this->aggregator->shouldReceive('userHasPermission')
            ->with($user, 'user.view')
            ->andReturn(true);
        $this->aggregator->shouldReceive('getPermissionSource')
            ->andReturn(['type' => 'direct', 'source' => null, 'via' => null]);

        $result = $this->tester->test($user, 'user.view');

        expect($result['allowed'])->toBeTrue()
            ->and($result['reason'])->toBe('Granted directly to user');
    });

    it('tests user permission - denied', function (): void {
        $user = new stdClass;

        $this->aggregator->shouldReceive('userHasPermission')
            ->with($user, 'user.view')
            ->andReturn(false);
        $this->policyEngine->shouldReceive('evaluate')
            ->andReturn(PolicyDecision::NotApplicable);

        $result = $this->tester->test($user, 'user.view');

        expect($result['allowed'])->toBeFalse();
    });

    it('tests batch permissions', function (): void {
        $user = new stdClass;

        $this->aggregator->shouldReceive('userHasPermission')
            ->with($user, 'user.view')
            ->andReturn(true);
        $this->aggregator->shouldReceive('userHasPermission')
            ->with($user, 'user.create')
            ->andReturn(false);
        $this->aggregator->shouldReceive('getPermissionSource')
            ->andReturn(['type' => 'direct', 'source' => null, 'via' => null]);
        $this->policyEngine->shouldReceive('evaluate')
            ->andReturn(PolicyDecision::NotApplicable);

        $results = $this->tester->testBatch($user, ['user.view', 'user.create']);

        expect($results)->toHaveKey('user.view')
            ->and($results)->toHaveKey('user.create')
            ->and($results['user.view']['allowed'])->toBeTrue()
            ->and($results['user.create']['allowed'])->toBeFalse();
    });
});

describe('PolicyEngine', function (): void {
    beforeEach(function (): void {
        config([
            'filament-authz.policies.combining_algorithm' => 'deny_overrides',
            'filament-authz.cache_ttl' => 3600,
        ]);

        $this->engine = new PolicyEngine;
    });

    it('can be instantiated', function (): void {
        expect($this->engine)->toBeInstanceOf(PolicyEngine::class);
    });

    it('gets combining algorithm', function (): void {
        $algorithm = $this->engine->getCombiningAlgorithm();

        expect($algorithm)->toBeInstanceOf(PolicyCombiningAlgorithm::class)
            ->and($algorithm)->toBe(PolicyCombiningAlgorithm::DenyOverrides);
    });

    it('sets combining algorithm', function (): void {
        $result = $this->engine->setCombiningAlgorithm(PolicyCombiningAlgorithm::FirstApplicable);

        expect($result)->toBe($this->engine)
            ->and($this->engine->getCombiningAlgorithm())->toBe(PolicyCombiningAlgorithm::FirstApplicable);
    });

    it('returns not applicable when no policies', function (): void {
        $decision = $this->engine->evaluate('view', 'nonexistent_resource');

        expect($decision)->toBe(PolicyDecision::NotApplicable);
    });

    it('checks isPermitted', function (): void {
        $result = $this->engine->isPermitted('view', 'nonexistent');

        expect($result)->toBeFalse();
    });

    it('checks isDenied', function (): void {
        $result = $this->engine->isDenied('view', 'nonexistent');

        expect($result)->toBeFalse();
    });

    it('explains decision', function (): void {
        $explanation = $this->engine->explain('view', 'nonexistent');

        expect($explanation)->toBeArray()
            ->and($explanation)->toHaveKey('decision')
            ->and($explanation)->toHaveKey('matching_policies')
            ->and($explanation)->toHaveKey('algorithm')
            ->and($explanation['decision'])->toBe(PolicyDecision::NotApplicable)
            ->and($explanation['matching_policies'])->toBe([])
            ->and($explanation['algorithm'])->toBe('deny_overrides');
    });
});

describe('RoleTemplateService', function (): void {
    beforeEach(function (): void {
        $this->service = new RoleTemplateService;
    });

    it('can be instantiated', function (): void {
        expect($this->service)->toBeInstanceOf(RoleTemplateService::class);
    });

    it('clears cache', function (): void {
        Cache::shouldReceive('forget')
            ->with('permissions:templates:hierarchy_tree')
            ->once();

        $this->service->clearCache();
    });

    it('finds by slug', function (): void {
        $result = $this->service->findBySlug('non-existent-slug');

        expect($result)->toBeNull();
    });
});
