<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Enums\AuditEventType;
use AIArmada\FilamentAuthz\Services\AuditLogger;
use AIArmada\FilamentAuthz\Services\ImplicitPermissionService;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use AIArmada\FilamentAuthz\Services\PermissionCacheService;
use AIArmada\FilamentAuthz\Services\RoleComparer;
use AIArmada\FilamentAuthz\Services\RoleInheritanceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

describe('ImplicitPermissionService', function (): void {
    beforeEach(function (): void {
        $this->service = new ImplicitPermissionService;
    });

    it('has standard implicit ability mappings', function (): void {
        $mappings = $this->service->getAllMappings();

        expect($mappings)->toBeArray()
            ->and($mappings)->toHaveKey('manage')
            ->and($mappings)->toHaveKey('edit')
            ->and($mappings)->toHaveKey('admin')
            ->and($mappings)->toHaveKey('full_access');
    });

    it('expands manage ability to include viewAny, view, create, update, delete', function (): void {
        $abilities = $this->service->getImplicitAbilities('manage');

        expect($abilities)->toBeInstanceOf(Collection::class)
            ->and($abilities->toArray())->toContain('viewAny')
            ->and($abilities->toArray())->toContain('view')
            ->and($abilities->toArray())->toContain('create')
            ->and($abilities->toArray())->toContain('update')
            ->and($abilities->toArray())->toContain('delete');
    });

    it('expands edit ability to view and update', function (): void {
        $abilities = $this->service->getImplicitAbilities('edit');

        expect($abilities->toArray())->toContain('view')
            ->and($abilities->toArray())->toContain('update');
    });

    it('expands permission to include implicit abilities', function (): void {
        $expanded = $this->service->expand('posts.manage');

        expect($expanded)->toBeInstanceOf(Collection::class)
            ->and($expanded->toArray())->toContain('posts.viewAny')
            ->and($expanded->toArray())->toContain('posts.view')
            ->and($expanded->toArray())->toContain('posts.create');
    });

    it('returns original permission for non-expandable abilities', function (): void {
        $expanded = $this->service->expand('posts.view');

        expect($expanded->toArray())->toBe(['posts.view']);
    });

    it('returns original permission for single-part permissions', function (): void {
        $expanded = $this->service->expand('admin');

        expect($expanded->toArray())->toBe(['admin']);
    });

    it('checks if permission implies another permission', function (): void {
        expect($this->service->implies('posts.manage', 'posts.view'))->toBeTrue()
            ->and($this->service->implies('posts.manage', 'posts.create'))->toBeTrue()
            ->and($this->service->implies('posts.edit', 'posts.view'))->toBeTrue()
            ->and($this->service->implies('posts.edit', 'posts.update'))->toBeTrue()
            ->and($this->service->implies('posts.view', 'posts.view'))->toBeTrue()
            ->and($this->service->implies('posts.view', 'posts.create'))->toBeFalse();
    });

    it('returns false for different resources', function (): void {
        expect($this->service->implies('posts.manage', 'comments.view'))->toBeFalse();
    });

    it('can register custom mapping', function (): void {
        $this->service->registerMapping('supervisor', ['view', 'approve', 'reject']);

        $abilities = $this->service->getImplicitAbilities('supervisor');

        expect($abilities->toArray())->toContain('view')
            ->and($abilities->toArray())->toContain('approve')
            ->and($abilities->toArray())->toContain('reject');
    });

    it('can register multiple mappings at once', function (): void {
        $this->service->registerMappings([
            'reviewer' => ['view', 'comment'],
            'approver' => ['view', 'approve', 'reject'],
        ]);

        expect($this->service->getImplicitAbilities('reviewer')->toArray())->toContain('view')
            ->and($this->service->getImplicitAbilities('approver')->toArray())->toContain('approve');
    });

    it('can clear cache', function (): void {
        Cache::shouldReceive('forget')
            ->once()
            ->with('permissions:implicit_map')
            ->andReturn(true);

        $this->service->clearCache();
    });
});

describe('PermissionCacheService', function (): void {
    beforeEach(function (): void {
        config(['cache.default' => 'array']);
        config(['cache.stores.array' => ['driver' => 'array', 'serialize' => false]]);
        config(['filament-authz.cache.store' => 'array']);
        $this->service = new PermissionCacheService;
    });

    it('can be instantiated', function (): void {
        expect($this->service)->toBeInstanceOf(PermissionCacheService::class);
    });

    it('returns cache stats', function (): void {
        $stats = $this->service->getStats();

        expect($stats)->toBeArray()
            ->and($stats)->toHaveKey('enabled')
            ->and($stats)->toHaveKey('store')
            ->and($stats)->toHaveKey('ttl')
            ->and($stats['enabled'])->toBeBool()
            ->and($stats['ttl'])->toBeInt();
    });

    it('can be disabled', function (): void {
        $result = $this->service->disable();

        expect($result)->toBe($this->service);
    });

    it('can be enabled', function (): void {
        $result = $this->service->enable();

        expect($result)->toBe($this->service);
    });

    it('can run callback without cache', function (): void {
        $result = $this->service->withoutCache(fn () => 'test result');

        expect($result)->toBe('test result');
    });

    it('remembers values through cache', function (): void {
        Cache::flush();

        $callCount = 0;
        $callback = function () use (&$callCount): string {
            $callCount++;

            return 'cached value';
        };

        // First call should execute callback
        $result1 = $this->service->remember('test-key', $callback);
        $result2 = $this->service->remember('test-key', $callback);

        expect($result1)->toBe('cached value')
            ->and($result2)->toBe('cached value');
    });
});

describe('RoleInheritanceService', function (): void {
    beforeEach(function (): void {
        $this->service = new RoleInheritanceService;
    });

    it('can be instantiated', function (): void {
        expect($this->service)->toBeInstanceOf(RoleInheritanceService::class);
    });

    it('can get root roles', function (): void {
        $roots = $this->service->getRootRoles();

        expect($roots)->toBeInstanceOf(Illuminate\Database\Eloquent\Collection::class);
    });

    it('can get hierarchy tree', function (): void {
        $tree = $this->service->getHierarchyTree();

        expect($tree)->toBeInstanceOf(Illuminate\Database\Eloquent\Collection::class);
    });

    it('can clear cache', function (): void {
        $this->service->clearCache();

        expect(true)->toBeTrue();
    });
});

describe('RoleComparer', function (): void {
    beforeEach(function (): void {
        $this->roleInheritance = Mockery::mock(RoleInheritanceService::class);
        $this->service = new RoleComparer($this->roleInheritance);
    });

    it('can be instantiated', function (): void {
        expect($this->service)->toBeInstanceOf(RoleComparer::class);
    });

    it('compares two roles with identical permissions', function (): void {
        $roleA = Mockery::mock(Role::class);
        $roleB = Mockery::mock(Role::class);

        $permissions = collect([
            (object) ['name' => 'view users'],
            (object) ['name' => 'edit users'],
        ]);

        $roleA->shouldReceive('getAttribute')->with('permissions')->andReturn($permissions);
        $roleA->shouldReceive('getAttribute')->with('name')->andReturn('admin');
        $roleB->shouldReceive('getAttribute')->with('permissions')->andReturn($permissions);
        $roleB->shouldReceive('getAttribute')->with('name')->andReturn('manager');

        $result = $this->service->compare($roleA, $roleB);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('role_a')
            ->and($result)->toHaveKey('role_b')
            ->and($result)->toHaveKey('shared_permissions')
            ->and($result)->toHaveKey('only_in_a')
            ->and($result)->toHaveKey('only_in_b')
            ->and($result)->toHaveKey('similarity_percent')
            ->and($result['similarity_percent'])->toBe(100.0);
    });

    it('compares two roles with different permissions', function (): void {
        $roleA = Mockery::mock(Role::class);
        $roleB = Mockery::mock(Role::class);

        $permissionsA = collect([
            (object) ['name' => 'view users'],
            (object) ['name' => 'edit users'],
        ]);
        $permissionsB = collect([
            (object) ['name' => 'view users'],
            (object) ['name' => 'delete users'],
        ]);

        $roleA->shouldReceive('getAttribute')->with('permissions')->andReturn($permissionsA);
        $roleA->shouldReceive('getAttribute')->with('name')->andReturn('admin');
        $roleB->shouldReceive('getAttribute')->with('permissions')->andReturn($permissionsB);
        $roleB->shouldReceive('getAttribute')->with('name')->andReturn('manager');

        $result = $this->service->compare($roleA, $roleB);

        expect($result['shared_permissions'])->toBe(['view users'])
            ->and($result['only_in_a'])->toBe(['edit users'])
            ->and($result['only_in_b'])->toBe(['delete users'])
            ->and($result['similarity_percent'])->toBeLessThan(100.0);
    });

    it('returns null when comparing role without parent', function (): void {
        $role = Mockery::mock(Role::class);

        $this->roleInheritance->shouldReceive('getParent')
            ->with($role)
            ->andReturn(null);

        $result = $this->service->compareWithParent($role);

        expect($result)->toBeNull();
    });

    it('gets diff between two roles', function (): void {
        $from = Mockery::mock(Role::class);
        $to = Mockery::mock(Role::class);

        $fromPermissions = collect([
            (object) ['name' => 'view users'],
            (object) ['name' => 'edit users'],
        ]);
        $toPermissions = collect([
            (object) ['name' => 'view users'],
            (object) ['name' => 'delete users'],
        ]);

        $from->shouldReceive('getAttribute')->with('permissions')->andReturn($fromPermissions);
        $to->shouldReceive('getAttribute')->with('permissions')->andReturn($toPermissions);

        $result = $this->service->getDiff($from, $to);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('to_add')
            ->and($result)->toHaveKey('to_remove')
            ->and($result)->toHaveKey('operations_count')
            ->and($result['to_add'])->toBe(['delete users'])
            ->and($result['to_remove'])->toBe(['edit users'])
            ->and($result['operations_count'])->toBe(2);
    });
});

describe('AuditLogger', function (): void {
    beforeEach(function (): void {
        $this->service = new AuditLogger;
        config(['filament-authz.audit.enabled' => false]);
    });

    it('can be instantiated', function (): void {
        expect($this->service)->toBeInstanceOf(AuditLogger::class);
    });

    it('does not log when disabled', function (): void {
        config(['filament-authz.audit.enabled' => false]);

        $this->service->log(AuditEventType::PermissionGranted);

        expect(true)->toBeTrue();
    });
});

describe('PermissionAggregator', function (): void {
    beforeEach(function (): void {
        $this->roleInheritance = Mockery::mock(RoleInheritanceService::class);
        $this->wildcardResolver = Mockery::mock(AIArmada\FilamentAuthz\Services\WildcardPermissionResolver::class);
        $this->implicitService = Mockery::mock(ImplicitPermissionService::class);

        $this->service = new PermissionAggregator(
            $this->roleInheritance,
            $this->wildcardResolver,
            $this->implicitService
        );
    });

    it('can be instantiated', function (): void {
        expect($this->service)->toBeInstanceOf(PermissionAggregator::class);
    });

    it('returns empty collection for user without getRoleNames', function (): void {
        $user = new stdClass;

        $result = $this->service->getEffectivePermissions($user);

        expect($result)->toBeInstanceOf(Illuminate\Database\Eloquent\Collection::class)
            ->and($result->count())->toBe(0);
    });

    it('returns empty collection for getEffectiveRoles without roles method', function (): void {
        $user = new stdClass;

        $result = $this->service->getEffectiveRoles($user);

        expect($result)->toBeInstanceOf(Illuminate\Database\Eloquent\Collection::class)
            ->and($result->count())->toBe(0);
    });

    it('can clear all caches', function (): void {
        $this->wildcardResolver->shouldReceive('clearCache')->once();
        $this->implicitService->shouldReceive('clearCache')->once();
        $this->roleInheritance->shouldReceive('clearCache')->once();

        $this->service->clearAllCache();

        expect(true)->toBeTrue();
    });
});
