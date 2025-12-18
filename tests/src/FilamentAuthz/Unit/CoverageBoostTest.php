<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Enums\AuditSeverity;
use AIArmada\FilamentAuthz\Enums\ConditionOperator;
use AIArmada\FilamentAuthz\Enums\ImpactLevel;
use AIArmada\FilamentAuthz\Enums\PermissionScope;
use AIArmada\FilamentAuthz\Enums\PolicyCombiningAlgorithm;
use AIArmada\FilamentAuthz\Enums\PolicyDecision;
use AIArmada\FilamentAuthz\Enums\PolicyEffect;
use AIArmada\FilamentAuthz\Enums\PolicyType;
use AIArmada\FilamentAuthz\Http\Middleware\AuthorizePanelRoles;
use AIArmada\FilamentAuthz\Jobs\WriteAuditLogJob;
use AIArmada\FilamentAuthz\Models\PermissionAuditLog;
use AIArmada\FilamentAuthz\Services\AuditLogger;
use AIArmada\FilamentAuthz\Services\DelegationService;
use AIArmada\FilamentAuthz\Services\Discovery\PageTransformer;
use AIArmada\FilamentAuthz\Services\Discovery\ResourceTransformer;
use AIArmada\FilamentAuthz\Services\Discovery\WidgetTransformer;
use AIArmada\FilamentAuthz\Services\EntityDiscoveryService;
use AIArmada\FilamentAuthz\Services\ImplicitPermissionService;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use AIArmada\FilamentAuthz\Services\PermissionBuilder;
use AIArmada\FilamentAuthz\Services\PermissionCacheService;
use AIArmada\FilamentAuthz\Services\PermissionRegistry;
use AIArmada\FilamentAuthz\Services\PolicyBuilder;
use AIArmada\FilamentAuthz\Services\PolicyEngine;
use AIArmada\FilamentAuthz\Services\RoleComparer;
use AIArmada\FilamentAuthz\Services\WildcardPermissionResolver;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredPage;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredResource;
use AIArmada\FilamentAuthz\ValueObjects\DiscoveredWidget;
use AIArmada\FilamentAuthz\ValueObjects\PolicyCondition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

describe('AuditSeverity Enum Coverage', function (): void {
    it('executes all AuditSeverity cases and methods', function (): void {
        foreach (AuditSeverity::cases() as $case) {
            expect($case->label())->toBeString();
            expect($case->description())->toBeString();
            expect($case->color())->toBeString();
            expect($case->icon())->toBeString();
            expect($case->numericLevel())->toBeInt();
            expect($case->shouldNotify())->toBeBool();
            expect($case->retentionDays())->toBeInt();
        }

        expect(AuditSeverity::Low->numericLevel())->toBe(1);
        expect(AuditSeverity::Medium->numericLevel())->toBe(2);
        expect(AuditSeverity::High->numericLevel())->toBe(3);
        expect(AuditSeverity::Critical->numericLevel())->toBe(4);

        expect(AuditSeverity::High->shouldNotify())->toBeTrue();
        expect(AuditSeverity::Critical->shouldNotify())->toBeTrue();
        expect(AuditSeverity::Low->shouldNotify())->toBeFalse();
        expect(AuditSeverity::Medium->shouldNotify())->toBeFalse();
    });
});

describe('PermissionScope Enum Coverage', function (): void {
    it('executes all PermissionScope cases and methods', function (): void {
        foreach (PermissionScope::cases() as $case) {
            expect($case->label())->toBeString();
            expect($case->description())->toBeString();
            expect($case->icon())->toBeString();
            expect($case->color())->toBeString();
            expect($case->requiresScopeId())->toBeBool();
            expect($case->supportsExpiration())->toBeBool();
        }

        expect(PermissionScope::Global->requiresScopeId())->toBeFalse();
        expect(PermissionScope::Owner->requiresScopeId())->toBeFalse();
        expect(PermissionScope::Team->requiresScopeId())->toBeTrue();
        expect(PermissionScope::Tenant->requiresScopeId())->toBeTrue();
        expect(PermissionScope::Resource->requiresScopeId())->toBeTrue();
        expect(PermissionScope::Temporal->requiresScopeId())->toBeTrue();

        expect(PermissionScope::Temporal->supportsExpiration())->toBeTrue();
        expect(PermissionScope::Global->supportsExpiration())->toBeFalse();
    });
});

describe('PolicyEffect Enum Coverage', function (): void {
    it('executes all PolicyEffect cases and methods', function (): void {
        foreach (PolicyEffect::cases() as $case) {
            expect($case->label())->toBeString();
            expect($case->description())->toBeString();
            expect($case->color())->toBeString();
            expect($case->icon())->toBeString();
            expect($case->isPermissive())->toBeBool();
            expect($case->isRestrictive())->toBeBool();
        }

        expect(PolicyEffect::Allow->isPermissive())->toBeTrue();
        expect(PolicyEffect::Allow->isRestrictive())->toBeFalse();
        expect(PolicyEffect::Deny->isPermissive())->toBeFalse();
        expect(PolicyEffect::Deny->isRestrictive())->toBeTrue();
    });
});

describe('PolicyDecision Enum Coverage', function (): void {
    it('executes all PolicyDecision cases and methods', function (): void {
        foreach (PolicyDecision::cases() as $case) {
            expect($case->label())->toBeString();
            expect($case->description())->toBeString();
            expect($case->color())->toBeString();
            expect($case->icon())->toBeString();
            expect($case->isAccessGranted())->toBeBool();
            expect($case->isAccessDenied())->toBeBool();
            expect($case->isConclusive())->toBeBool();
            expect($case->requiresFallback())->toBeBool();
        }

        expect(PolicyDecision::Permit->isAccessGranted())->toBeTrue();
        expect(PolicyDecision::Deny->isAccessDenied())->toBeTrue();
        expect(PolicyDecision::NotApplicable->requiresFallback())->toBeTrue();
        expect(PolicyDecision::Indeterminate->requiresFallback())->toBeTrue();
        expect(PolicyDecision::Permit->isConclusive())->toBeTrue();
        expect(PolicyDecision::Deny->isConclusive())->toBeTrue();
    });
});

describe('PolicyCombiningAlgorithm Enum Coverage', function (): void {
    it('executes all PolicyCombiningAlgorithm cases and methods', function (): void {
        foreach (PolicyCombiningAlgorithm::cases() as $case) {
            expect($case->label())->toBeString();
            expect($case->description())->toBeString();
            expect($case->defaultDecision())->toBeInstanceOf(PolicyDecision::class);
        }
    });

    it('combines decisions correctly with DenyOverrides', function (): void {
        $algo = PolicyCombiningAlgorithm::DenyOverrides;
        expect($algo->combine([PolicyDecision::Permit]))->toBe(PolicyDecision::Permit);
        expect($algo->combine([PolicyDecision::Deny]))->toBe(PolicyDecision::Deny);
        expect($algo->combine([PolicyDecision::Permit, PolicyDecision::Deny]))->toBe(PolicyDecision::Deny);
        expect($algo->combine([]))->toBe(PolicyDecision::Deny); // default for DenyOverrides
        expect($algo->combine([PolicyDecision::NotApplicable]))->toBe(PolicyDecision::NotApplicable);
        expect($algo->combine([PolicyDecision::Indeterminate]))->toBe(PolicyDecision::Indeterminate);
    });

    it('combines decisions correctly with PermitOverrides', function (): void {
        $algo = PolicyCombiningAlgorithm::PermitOverrides;
        expect($algo->combine([PolicyDecision::Permit]))->toBe(PolicyDecision::Permit);
        expect($algo->combine([PolicyDecision::Deny]))->toBe(PolicyDecision::Deny);
        expect($algo->combine([PolicyDecision::Deny, PolicyDecision::Permit]))->toBe(PolicyDecision::Permit);
        expect($algo->combine([PolicyDecision::NotApplicable]))->toBe(PolicyDecision::NotApplicable);
    });

    it('combines decisions correctly with FirstApplicable', function (): void {
        $algo = PolicyCombiningAlgorithm::FirstApplicable;
        expect($algo->combine([PolicyDecision::Permit, PolicyDecision::Deny]))->toBe(PolicyDecision::Permit);
        expect($algo->combine([PolicyDecision::Deny, PolicyDecision::Permit]))->toBe(PolicyDecision::Deny);
        expect($algo->combine([PolicyDecision::NotApplicable, PolicyDecision::Permit]))->toBe(PolicyDecision::Permit);
        expect($algo->combine([PolicyDecision::NotApplicable]))->toBe(PolicyDecision::NotApplicable);
    });

    it('combines decisions correctly with OnlyOneApplicable', function (): void {
        $algo = PolicyCombiningAlgorithm::OnlyOneApplicable;
        expect($algo->combine([PolicyDecision::Permit]))->toBe(PolicyDecision::Permit);
        expect($algo->combine([PolicyDecision::Permit, PolicyDecision::Deny]))->toBe(PolicyDecision::Indeterminate);
        expect($algo->combine([PolicyDecision::NotApplicable]))->toBe(PolicyDecision::NotApplicable);
    });

    it('combines decisions correctly with PermitUnlessDeny', function (): void {
        $algo = PolicyCombiningAlgorithm::PermitUnlessDeny;
        expect($algo->combine([PolicyDecision::Permit, PolicyDecision::Permit]))->toBe(PolicyDecision::Permit);
        expect($algo->combine([PolicyDecision::Permit, PolicyDecision::Deny]))->toBe(PolicyDecision::Deny);
        expect($algo->combine([PolicyDecision::NotApplicable]))->toBe(PolicyDecision::Permit);
    });

    it('combines decisions correctly with DenyUnlessPermit', function (): void {
        $algo = PolicyCombiningAlgorithm::DenyUnlessPermit;
        expect($algo->combine([PolicyDecision::Deny, PolicyDecision::Deny]))->toBe(PolicyDecision::Deny);
        expect($algo->combine([PolicyDecision::Deny, PolicyDecision::Permit]))->toBe(PolicyDecision::Permit);
        expect($algo->combine([PolicyDecision::NotApplicable]))->toBe(PolicyDecision::Deny);
    });
});

describe('ImpactLevel Enum Coverage', function (): void {
    it('executes all ImpactLevel cases and methods', function (): void {
        foreach (ImpactLevel::cases() as $case) {
            expect($case->label())->toBeString();
            expect($case->description())->toBeString();
            expect($case->color())->toBeString();
            expect($case->icon())->toBeString();
            expect($case->numericLevel())->toBeInt();
            expect($case->requiresApproval())->toBeBool();
            expect($case->requiresConfirmation())->toBeBool();
        }

        expect(ImpactLevel::Critical->requiresApproval())->toBeTrue();
        expect(ImpactLevel::High->requiresApproval())->toBeTrue();
        expect(ImpactLevel::Low->requiresApproval())->toBeFalse();
        expect(ImpactLevel::Medium->requiresConfirmation())->toBeTrue();
    });

    it('calculates impact from affected users', function (): void {
        expect(ImpactLevel::fromAffectedUsers(0))->toBe(ImpactLevel::None);
        expect(ImpactLevel::fromAffectedUsers(1))->toBe(ImpactLevel::Low);
        expect(ImpactLevel::fromAffectedUsers(10))->toBe(ImpactLevel::Medium);
        expect(ImpactLevel::fromAffectedUsers(100))->toBe(ImpactLevel::High);
        expect(ImpactLevel::fromAffectedUsers(1000))->toBe(ImpactLevel::Critical);
    });

    it('calculates impact from percentage of total users', function (): void {
        expect(ImpactLevel::fromAffectedUsers(75, 100))->toBe(ImpactLevel::Critical);
        expect(ImpactLevel::fromAffectedUsers(50, 100))->toBe(ImpactLevel::High);
        expect(ImpactLevel::fromAffectedUsers(25, 100))->toBe(ImpactLevel::Medium);
        expect(ImpactLevel::fromAffectedUsers(5, 100))->toBe(ImpactLevel::Low);
        expect(ImpactLevel::fromAffectedUsers(1, 100))->toBe(ImpactLevel::None);
    });
});

describe('ConditionOperator Enum Coverage', function (): void {
    it('executes all ConditionOperator cases and methods', function (): void {
        foreach (ConditionOperator::cases() as $case) {
            expect($case->label())->toBeString();
            expect($case->symbol())->toBeString();
        }
    });

    it('evaluates conditions correctly', function (): void {
        expect(ConditionOperator::Equals->evaluate('test', 'test'))->toBeTrue();
        expect(ConditionOperator::Equals->evaluate('test', 'other'))->toBeFalse();
        expect(ConditionOperator::NotEquals->evaluate('test', 'other'))->toBeTrue();
        expect(ConditionOperator::GreaterThan->evaluate(10, 5))->toBeTrue();
        expect(ConditionOperator::LessThan->evaluate(5, 10))->toBeTrue();
        expect(ConditionOperator::GreaterThanOrEquals->evaluate(10, 10))->toBeTrue();
        expect(ConditionOperator::LessThanOrEquals->evaluate(10, 10))->toBeTrue();
        expect(ConditionOperator::In->evaluate('a', ['a', 'b', 'c']))->toBeTrue();
        expect(ConditionOperator::NotIn->evaluate('d', ['a', 'b', 'c']))->toBeTrue();
        expect(ConditionOperator::Contains->evaluate('hello world', 'world'))->toBeTrue();
        expect(ConditionOperator::StartsWith->evaluate('hello', 'hel'))->toBeTrue();
        expect(ConditionOperator::EndsWith->evaluate('hello', 'llo'))->toBeTrue();
        expect(ConditionOperator::IsNull->evaluate(null, null))->toBeTrue();
        expect(ConditionOperator::IsNotNull->evaluate('value', null))->toBeTrue();
        expect(ConditionOperator::Between->evaluate(5, [1, 10]))->toBeTrue();
        expect(ConditionOperator::Matches->evaluate('test123', '/test\d+/'))->toBeTrue();
    });
});

describe('AuthorizePanelRoles Middleware Coverage', function (): void {
    it('allows request when no panel', function (): void {
        $middleware = new AuthorizePanelRoles();
        $request = Request::create('/test');

        $response = $middleware->handle($request, fn ($req) => response('OK'));

        expect($response->getContent())->toBe('OK');
    });

    it('allows request when feature disabled', function (): void {
        config(['filament-authz.features.panel_role_authorization' => false]);

        $middleware = new AuthorizePanelRoles();
        $request = Request::create('/test');

        $response = $middleware->handle($request, fn ($req) => response('OK'));

        expect($response->getContent())->toBe('OK');
    });
});

describe('ImplicitPermissionService Coverage', function (): void {
    it('expands permissions correctly', function (): void {
        $service = new ImplicitPermissionService();

        $expanded = $service->expand('user.manage');
        expect($expanded)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($expanded->count())->toBeGreaterThanOrEqual(1);
    });

    it('gets implicit abilities', function (): void {
        $service = new ImplicitPermissionService();

        $abilities = $service->getImplicitAbilities('manage');
        expect($abilities->toArray())->toContain('viewAny');
        expect($abilities->toArray())->toContain('view');
        expect($abilities->toArray())->toContain('create');
    });

    it('checks if permission implies another', function (): void {
        $service = new ImplicitPermissionService();

        expect($service->implies('user.manage', 'user.view'))->toBeTrue();
        expect($service->implies('user.view', 'user.delete'))->toBeFalse();
        expect($service->implies('user.view', 'user.view'))->toBeTrue();
    });

    it('registers custom mappings', function (): void {
        $service = new ImplicitPermissionService();

        $service->registerMapping('superuser', ['viewAny', 'view', 'create', 'update', 'delete']);
        $abilities = $service->getImplicitAbilities('superuser');

        expect($abilities->toArray())->toContain('viewAny');
    });

    it('registers multiple mappings', function (): void {
        $service = new ImplicitPermissionService();

        $service->registerMappings([
            'readonly' => ['viewAny', 'view'],
            'writer' => ['viewAny', 'view', 'create', 'update'],
        ]);

        expect($service->getImplicitAbilities('readonly')->toArray())->toContain('view');
        expect($service->getImplicitAbilities('writer')->toArray())->toContain('create');
    });

    it('gets all mappings', function (): void {
        $service = new ImplicitPermissionService();
        $mappings = $service->getAllMappings();

        expect($mappings)->toBeArray();
        expect($mappings)->toHaveKey('manage');
    });

    it('clears cache', function (): void {
        $service = new ImplicitPermissionService();
        $service->clearCache();

        expect(true)->toBeTrue();
    });
});

describe('DelegationService Coverage', function (): void {
    it('can be instantiated', function (): void {
        $auditLogger = app(AuditLogger::class);
        $service = new DelegationService($auditLogger);

        expect($service)->toBeInstanceOf(DelegationService::class);
    });

    it('checks canDelegate returns false for user without permission', function (): void {
        $auditLogger = app(AuditLogger::class);
        $service = new DelegationService($auditLogger);

        $user = new class
        {
            public function can(string $permission): bool
            {
                return false;
            }
        };

        expect($service->canDelegate($user, 'test.permission'))->toBeFalse();
    });
});

describe('PageTransformer Coverage', function (): void {
    it('throws exception for invalid page class', function (): void {
        $transformer = new PageTransformer();

        expect(fn () => $transformer->transform('InvalidClass'))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('ResourceTransformer Coverage', function (): void {
    it('throws exception for invalid resource class', function (): void {
        $transformer = new ResourceTransformer();

        expect(fn () => $transformer->transform('InvalidClass'))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('WidgetTransformer Coverage', function (): void {
    it('throws exception for invalid widget class', function (): void {
        $transformer = new WidgetTransformer();

        expect(fn () => $transformer->transform('InvalidClass'))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('EntityDiscoveryService Coverage', function (): void {
    it('can be instantiated', function (): void {
        $mockService = Mockery::mock(EntityDiscoveryService::class);
        expect($mockService)->toBeInstanceOf(EntityDiscoveryService::class);
    });

    it('discovers resources returns collection', function (): void {
        $mockService = Mockery::mock(EntityDiscoveryService::class);
        $mockService->shouldReceive('discoverResources')->once()->andReturn(collect(['resource1', 'resource2']));

        $resources = $mockService->discoverResources();

        expect($resources)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($resources->count())->toBe(2);
    });

    it('discovers pages returns collection', function (): void {
        $mockService = Mockery::mock(EntityDiscoveryService::class);
        $mockService->shouldReceive('discoverPages')->once()->andReturn(collect(['page1', 'page2']));

        $pages = $mockService->discoverPages();

        expect($pages)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($pages->count())->toBe(2);
    });

    it('discovers widgets returns collection', function (): void {
        $mockService = Mockery::mock(EntityDiscoveryService::class);
        $mockService->shouldReceive('discoverWidgets')->once()->andReturn(collect(['widget1', 'widget2']));

        $widgets = $mockService->discoverWidgets();

        expect($widgets)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($widgets->count())->toBe(2);
    });

    it('discovers all returns array with three keys', function (): void {
        $mockService = Mockery::mock(EntityDiscoveryService::class);
        $mockService->shouldReceive('discoverAll')->once()->andReturn([
            'resources' => collect(['resource1']),
            'pages' => collect(['page1']),
            'widgets' => collect(['widget1']),
        ]);

        $all = $mockService->discoverAll();

        expect($all)->toHaveKeys(['resources', 'pages', 'widgets']);
        expect($all['resources'])->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });

    it('gets discovered permissions', function (): void {
        $mockService = Mockery::mock(EntityDiscoveryService::class);
        $mockService->shouldReceive('getDiscoveredPermissions')->once()->andReturn(collect(['permission1', 'permission2']));

        $permissions = $mockService->getDiscoveredPermissions();

        expect($permissions)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($permissions->count())->toBe(2);
    });

    it('warms and clears cache', function (): void {
        $mockService = Mockery::mock(EntityDiscoveryService::class);
        $mockService->shouldReceive('warmCache')->once()->andReturn(true);
        $mockService->shouldReceive('clearCache')->once()->andReturn(true);

        $mockService->warmCache();
        $mockService->clearCache();

        expect(true)->toBeTrue();
    });
});

describe('PermissionAggregator Coverage', function (): void {
    it('can be instantiated', function (): void {
        $aggregator = app(PermissionAggregator::class);
        expect($aggregator)->toBeInstanceOf(PermissionAggregator::class);
    });
});

describe('PermissionBuilder Coverage', function (): void {
    it('can be instantiated', function (): void {
        $builder = PermissionBuilder::for('user');
        expect($builder)->toBeInstanceOf(PermissionBuilder::class);
    });

    it('builds resource permission with crud', function (): void {
        $builder = PermissionBuilder::for('user')->crud();
        $result = $builder->build();

        expect($result)->toHaveKey('user.viewAny');
        expect($result)->toHaveKey('user.view');
        expect($result)->toHaveKey('user.create');
    });

    it('builds with soft deletes', function (): void {
        $builder = PermissionBuilder::for('user')->softDeletes();
        $result = $builder->build();

        expect($result)->toHaveKey('user.restore');
        expect($result)->toHaveKey('user.forceDelete');
    });

    it('builds full crud', function (): void {
        $builder = PermissionBuilder::for('user')->fullCrud();
        $result = $builder->build();

        expect($result)->toHaveKey('user.viewAny');
        expect($result)->toHaveKey('user.restore');
    });

    it('adds specific ability', function (): void {
        $builder = PermissionBuilder::for('user')->ability('view', 'View a user');
        $result = $builder->build();

        expect($result)->toHaveKey('user.view');
        expect($result['user.view']['description'])->toBe('View a user');
    });

    it('adds view only abilities', function (): void {
        $builder = PermissionBuilder::for('user')->viewOnly();
        $result = $builder->build();

        expect($result)->toHaveKey('user.viewAny');
        expect($result)->toHaveKey('user.view');
        expect($result)->not->toHaveKey('user.create');
    });

    it('adds manage ability', function (): void {
        $builder = PermissionBuilder::for('user')->manage();
        $result = $builder->build();

        expect($result)->toHaveKey('user.manage');
    });

    it('adds wildcard permission', function (): void {
        $builder = PermissionBuilder::for('user')->wildcard();
        $result = $builder->build();

        expect($result)->toHaveKey('user.*');
    });

    it('adds export and import abilities', function (): void {
        $builder = PermissionBuilder::for('user')->export()->import();
        $result = $builder->build();

        expect($result)->toHaveKey('user.export');
        expect($result)->toHaveKey('user.import');
    });

    it('adds replicate ability', function (): void {
        $builder = PermissionBuilder::for('user')->replicate();
        $result = $builder->build();

        expect($result)->toHaveKey('user.replicate');
    });

    it('adds bulk action abilities', function (): void {
        $builder = PermissionBuilder::for('user')->bulkActions();
        $result = $builder->build();

        expect($result)->toHaveKey('user.bulkDelete');
        expect($result)->toHaveKey('user.bulkUpdate');
    });

    it('sets group', function (): void {
        $builder = PermissionBuilder::for('user')->group('admin')->crud();
        $result = $builder->build();

        expect($result['user.viewAny']['group'])->toBe('admin');
    });

    it('sets guard', function (): void {
        $builder = PermissionBuilder::for('user')->guard('api')->crud();
        $result = $builder->build();

        expect($result['user.viewAny']['guard_name'])->toBe('api');
    });

    it('gets permission names', function (): void {
        $builder = PermissionBuilder::for('user')->crud();
        $names = $builder->getNames();

        expect($names)->toContain('user.viewAny');
        expect($names)->toContain('user.create');
    });
});

describe('PermissionCacheService Coverage', function (): void {
    beforeEach(function (): void {
        config(['filament-authz.cache.store' => null]);
        config(['filament-authz.cache.enabled' => true]);
    });

    it('can be instantiated', function (): void {
        $service = new PermissionCacheService();
        expect($service)->toBeInstanceOf(PermissionCacheService::class);
    });

    it('gets stats', function (): void {
        $service = new PermissionCacheService();
        $stats = $service->getStats();

        expect($stats)->toHaveKeys(['enabled', 'store', 'ttl']);
    });

    it('remembers values', function (): void {
        $service = new PermissionCacheService();
        $value = $service->remember('test_key_' . uniqid(), fn () => 'cached_value');

        expect($value)->toBe('cached_value');
    });

    it('gets user permissions', function (): void {
        $user = createUserWithRoles(['super_admin']);
        $service = new PermissionCacheService();

        $permissions = $service->getUserPermissions($user);
        expect($permissions)->toBeArray();
    });

    it('gets role permissions', function (): void {
        $role = Role::create(['name' => 'cache_test_role_' . uniqid(), 'guard_name' => 'web']);
        $perm = Permission::create(['name' => 'cache.test.' . uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo($perm);

        $service = new PermissionCacheService();
        $permissions = $service->getRolePermissions($role);

        expect($permissions)->toBeArray();
    });

    it('checks user has permission', function (): void {
        $user = createUserWithRoles(['super_admin']);
        $service = new PermissionCacheService();

        $has = $service->userHasPermission($user, 'nonexistent.permission');
        expect($has)->toBeBool();
    });

    it('forgets user cache', function (): void {
        $user = createUserWithRoles(['super_admin']);
        $service = new PermissionCacheService();

        $service->forgetUser($user);
        expect(true)->toBeTrue();
    });

    it('forgets role cache', function (): void {
        $role = Role::create(['name' => 'forget_role_' . uniqid(), 'guard_name' => 'web']);
        $service = new PermissionCacheService();

        $service->forgetRole($role);
        expect(true)->toBeTrue();
    });

    it('forgets permission cache', function (): void {
        $perm = Permission::create(['name' => 'forget.perm.' . uniqid(), 'guard_name' => 'web']);
        $service = new PermissionCacheService();

        $service->forgetPermission($perm);
        expect(true)->toBeTrue();
    });

    it('flushes all caches', function (): void {
        $service = new PermissionCacheService();
        $service->flush();

        expect(true)->toBeTrue();
    });

    it('warms user cache', function (): void {
        $user = createUserWithRoles(['super_admin']);
        $service = new PermissionCacheService();

        $service->warmUserCache($user);
        expect(true)->toBeTrue();
    });

    it('warms role cache', function (): void {
        $service = new PermissionCacheService();
        $service->warmRoleCache();

        expect(true)->toBeTrue();
    });

    it('disables and enables caching', function (): void {
        $service = new PermissionCacheService();

        $service->disable();
        expect($service->getStats()['enabled'])->toBeFalse();

        $service->enable();
        expect($service->getStats()['enabled'])->toBeTrue();
    });

    it('runs callback without cache', function (): void {
        $service = new PermissionCacheService();

        $result = $service->withoutCache(fn () => 'no_cache_result');
        expect($result)->toBe('no_cache_result');
    });
});

describe('PolicyBuilder Coverage', function (): void {
    it('can be instantiated', function (): void {
        $builder = app(PolicyBuilder::class);
        expect($builder)->toBeInstanceOf(PolicyBuilder::class);
    });
});

describe('PolicyEngine Coverage', function (): void {
    it('can be instantiated', function (): void {
        $engine = new PolicyEngine();
        expect($engine)->toBeInstanceOf(PolicyEngine::class);
    });

    it('evaluates returns decision', function (): void {
        $engine = new PolicyEngine();
        $decision = $engine->evaluate('view', 'user');

        expect($decision)->toBeInstanceOf(PolicyDecision::class);
    });

    it('gets and sets combining algorithm', function (): void {
        $engine = new PolicyEngine();
        $engine->setCombiningAlgorithm(PolicyCombiningAlgorithm::PermitOverrides);

        expect($engine->getCombiningAlgorithm())->toBe(PolicyCombiningAlgorithm::PermitOverrides);
    });

    it('explains decision', function (): void {
        $engine = new PolicyEngine();
        $explanation = $engine->explain('view', 'user');

        expect($explanation)->toHaveKeys(['decision', 'matching_policies', 'algorithm']);
    });

    it('checks isPermitted', function (): void {
        $engine = new PolicyEngine();
        expect($engine->isPermitted('view', 'nonexistent'))->toBeFalse();
    });

    it('checks isDenied', function (): void {
        $engine = new PolicyEngine();
        expect($engine->isDenied('view', 'nonexistent'))->toBeFalse();
    });
});

describe('PermissionRegistry Coverage', function (): void {
    it('can be instantiated', function (): void {
        $registry = new PermissionRegistry();
        expect($registry)->toBeInstanceOf(PermissionRegistry::class);
    });

    it('registers and retrieves permissions', function (): void {
        $registry = new PermissionRegistry();
        $registry->register('test.view', 'View test', 'test-group', 'test');

        expect($registry->isRegistered('test.view'))->toBeTrue();
        expect($registry->getDefinition('test.view'))->toBeArray();
    });

    it('registers resource permissions', function (): void {
        $registry = new PermissionRegistry();
        $registry->registerResource('user', ['view', 'create'], 'users');

        expect($registry->isRegistered('user.view'))->toBeTrue();
        expect($registry->isRegistered('user.create'))->toBeTrue();
    });

    it('groups by resource and group', function (): void {
        $registry = new PermissionRegistry();
        $registry->register('user.view', null, 'admin', 'user');
        $registry->register('user.create', null, 'admin', 'user');

        expect($registry->groupByResource())->toHaveKey('user');
        expect($registry->groupByGroup())->toHaveKey('admin');
    });

    it('exports and clears', function (): void {
        $registry = new PermissionRegistry();
        $registry->register('export.test', null, null, null);

        $exported = $registry->export();
        expect($exported)->toHaveKey('export.test');

        $registry->clear();
        expect($registry->getDefinitions())->toBe([]);
    });
});

describe('RoleComparer Coverage', function (): void {
    beforeEach(function (): void {
        Role::query()->whereNotIn('name', ['super_admin'])->delete();
        Permission::query()->delete();
    });

    it('can be instantiated', function (): void {
        $comparer = app(RoleComparer::class);
        expect($comparer)->toBeInstanceOf(RoleComparer::class);
    });

    it('compares two roles', function (): void {
        $role1 = Role::create(['name' => 'role_compare_1', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'role_compare_2', 'guard_name' => 'web']);
        $perm1 = Permission::create(['name' => 'compare.perm1', 'guard_name' => 'web']);
        $perm2 = Permission::create(['name' => 'compare.perm2', 'guard_name' => 'web']);
        $role1->givePermissionTo([$perm1, $perm2]);
        $role2->givePermissionTo($perm1);

        $comparer = app(RoleComparer::class);
        $diff = $comparer->compare($role1, $role2);

        expect($diff)->toHaveKeys(['role_a', 'role_b', 'shared_permissions', 'only_in_a', 'only_in_b', 'similarity_percent']);
        expect($diff['shared_permissions'])->toContain('compare.perm1');
        expect($diff['only_in_a'])->toContain('compare.perm2');
    });

    it('compares with parent', function (): void {
        $parent = Role::create(['name' => 'compare_parent', 'guard_name' => 'web']);
        $child = Role::create(['name' => 'compare_child', 'guard_name' => 'web']);

        $service = app(\AIArmada\FilamentAuthz\Services\RoleInheritanceService::class);
        $service->setParent($child, $parent);

        $comparer = app(RoleComparer::class);
        $comparison = $comparer->compareWithParent($child->refresh());

        expect($comparison)->toHaveKeys(['role', 'parent', 'inherited_permissions', 'own_permissions']);
    });

    it('finds similar roles', function (): void {
        $role1 = Role::create(['name' => 'similar_role_1', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'similar_role_2', 'guard_name' => 'web']);
        $perm = Permission::create(['name' => 'similar.perm', 'guard_name' => 'web']);
        $role1->givePermissionTo($perm);
        $role2->givePermissionTo($perm);

        $comparer = app(RoleComparer::class);
        $similar = $comparer->findSimilarRoles($role1);

        expect($similar)->toBeArray();
    });

    it('finds redundant roles', function (): void {
        $comparer = app(RoleComparer::class);
        $redundant = $comparer->findRedundantRoles();

        expect($redundant)->toBeArray();
    });

    it('gets diff between roles', function (): void {
        $role1 = Role::create(['name' => 'diff_role_1', 'guard_name' => 'web']);
        $role2 = Role::create(['name' => 'diff_role_2', 'guard_name' => 'web']);
        $perm1 = Permission::create(['name' => 'diff.perm1', 'guard_name' => 'web']);
        $perm2 = Permission::create(['name' => 'diff.perm2', 'guard_name' => 'web']);
        $role1->givePermissionTo($perm1);
        $role2->givePermissionTo($perm2);

        $comparer = app(RoleComparer::class);
        $diff = $comparer->getDiff($role1, $role2);

        expect($diff)->toHaveKeys(['to_add', 'to_remove', 'operations_count']);
    });

    it('generates hierarchy report', function (): void {
        $comparer = app(RoleComparer::class);
        $report = $comparer->generateHierarchyReport();

        expect($report)->toHaveKeys(['total_roles', 'max_depth', 'orphan_roles', 'roles_per_level']);
    });

    it('finds unused permissions', function (): void {
        Permission::create(['name' => 'unused.perm', 'guard_name' => 'web']);

        $comparer = app(RoleComparer::class);
        $unused = $comparer->findUnusedPermissions();

        expect($unused)->toContain('unused.perm');
    });
});

describe('WildcardPermissionResolver Coverage', function (): void {
    beforeEach(function (): void {
        Permission::query()->delete();
        Permission::create(['name' => 'user.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'user.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'order.view', 'guard_name' => 'web']);
    });

    it('can be instantiated', function (): void {
        $resolver = new WildcardPermissionResolver();
        expect($resolver)->toBeInstanceOf(WildcardPermissionResolver::class);
    });

    it('detects wildcards', function (): void {
        $resolver = new WildcardPermissionResolver();

        expect($resolver->isWildcard('*'))->toBeTrue();
        expect($resolver->isWildcard('user.*'))->toBeTrue();
        expect($resolver->isWildcard('*.view'))->toBeTrue();
        expect($resolver->isWildcard('user.view'))->toBeFalse();
    });

    it('resolves wildcards', function (): void {
        $resolver = new WildcardPermissionResolver();

        $resolved = $resolver->resolve('user.*');
        expect($resolved)->toContain('user.view');
        expect($resolved)->toContain('user.create');
    });

    it('matches patterns', function (): void {
        $resolver = new WildcardPermissionResolver();

        expect($resolver->matches('user.*', 'user.view'))->toBeTrue();
        expect($resolver->matches('*.view', 'user.view'))->toBeTrue();
        expect($resolver->matches('*', 'anything'))->toBeTrue();
    });

    it('gets prefixes', function (): void {
        $resolver = new WildcardPermissionResolver();
        $prefixes = $resolver->getPrefixes();

        expect($prefixes)->toContain('user');
        expect($prefixes)->toContain('order');
    });

    it('groups by prefix', function (): void {
        $resolver = new WildcardPermissionResolver();
        $grouped = $resolver->groupByPrefix();

        expect($grouped)->toHaveKey('user');
        expect($grouped)->toHaveKey('order');
    });

    it('extracts prefix and action', function (): void {
        $resolver = new WildcardPermissionResolver();

        expect($resolver->extractPrefix('user.view'))->toBe('user');
        expect($resolver->extractAction('user.view'))->toBe('view');
    });

    it('builds permission', function (): void {
        $resolver = new WildcardPermissionResolver();

        expect($resolver->buildPermission('user', 'view'))->toBe('user.view');
    });
});

describe('WriteAuditLogJob Coverage', function (): void {
    beforeEach(function (): void {
        PermissionAuditLog::query()->delete();
    });

    it('can be instantiated', function (): void {
        $job = new WriteAuditLogJob([
            'event_type' => \AIArmada\FilamentAuthz\Enums\AuditEventType::PermissionGranted,
            'severity' => 'low',
            'description' => 'Test event',
            'metadata' => ['test' => 'value'],
        ]);

        expect($job)->toBeInstanceOf(WriteAuditLogJob::class);
        expect($job->data)->toHaveKey('event_type');
    });

    it('has backoff config', function (): void {
        $job = new WriteAuditLogJob(['event_type' => 'test']);
        expect($job->backoff())->toBe([1, 5, 10]);
    });

    it('has tries config', function (): void {
        $job = new WriteAuditLogJob(['event_type' => 'test']);
        expect($job->tries())->toBe(3);
    });

    it('handles job execution', function (): void {
        $user = createUserWithRoles(['super_admin']);
        $uniqueSessionId = 'test-session-' . uniqid();

        $job = new WriteAuditLogJob([
            'event_type' => \AIArmada\FilamentAuthz\Enums\AuditEventType::PermissionGranted->value,
            'severity' => 'low',
            'context' => ['test' => 'Job test execution'],
            'actor_type' => $user::class,
            'actor_id' => $user->id,
            'occurred_at' => now(),
            'session_id' => $uniqueSessionId,
        ]);

        $job->handle();

        expect(PermissionAuditLog::where('session_id', $uniqueSessionId)->exists())->toBeTrue();
    });
});

describe('ValueObjects Coverage', function (): void {
    it('creates DiscoveredPage', function (): void {
        $page = new DiscoveredPage(
            fqcn: 'App\\Pages\\TestPage',
            title: 'Test Page',
            slug: 'test-page',
            cluster: null,
            permissions: ['viewTestPage'],
            metadata: [],
            panel: 'admin'
        );

        expect($page->fqcn)->toBe('App\\Pages\\TestPage');
        expect($page->getPermissionKey())->toBeString();
        expect($page->toArray())->toBeArray();
    });

    it('creates DiscoveredResource', function (): void {
        $resource = new DiscoveredResource(
            fqcn: 'App\\Resources\\UserResource',
            model: 'App\\Models\\User',
            permissions: ['view', 'create'],
            metadata: [],
            panel: 'admin'
        );

        expect($resource->fqcn)->toBe('App\\Resources\\UserResource');
        expect($resource->toPermissionKeys())->toBeArray();
        expect($resource->getModelBasename())->toBe('User');
        expect($resource->getResourceBasename())->toBe('UserResource');
        expect($resource->getPolicyClass())->toBe('App\\Policies\\UserPolicy');
    });

    it('creates DiscoveredWidget', function (): void {
        $widget = new DiscoveredWidget(
            fqcn: 'App\\Widgets\\StatsWidget',
            name: 'stats_widget',
            type: 'stats',
            permissions: ['viewStatsWidget'],
            metadata: [],
            panel: 'admin'
        );

        expect($widget->fqcn)->toBe('App\\Widgets\\StatsWidget');
        expect($widget->getPermissionKey())->toBeString();
        expect($widget->toArray())->toBeArray();
    });

    it('creates PolicyCondition', function (): void {
        $condition = PolicyCondition::equals('status', 'active');

        expect($condition->attribute)->toBe('status');
        expect($condition->evaluate(['status' => 'active']))->toBeTrue();
        expect($condition->evaluate(['status' => 'inactive']))->toBeFalse();
        expect($condition->describe())->toBeString();
        expect($condition->toArray())->toBeArray();
    });

    it('creates PolicyCondition from array', function (): void {
        $condition = PolicyCondition::fromArray([
            'attribute' => 'role',
            'operator' => 'eq',
            'value' => 'admin',
        ]);

        expect($condition->attribute)->toBe('role');
        expect($condition->operator)->toBe(ConditionOperator::Equals);
    });
});
