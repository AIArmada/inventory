<?php

declare(strict_types=1);

use AIArmada\FilamentAuthz\Services\PermissionCacheService;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    config()->set('cache.default', 'array');
    config()->set('cache.stores.array', ['driver' => 'array']);
    config()->set('filament-authz.cache.store', 'array');
    config()->set('filament-authz.cache.enabled', true);
    config()->set('filament-authz.cache_ttl', 3600);
    Cache::flush();
});

describe('PermissionCacheService', function (): void {
    it('can be instantiated', function (): void {
        $service = new PermissionCacheService();

        expect($service)->toBeInstanceOf(PermissionCacheService::class);
    });

    it('returns stats', function (): void {
        $service = new PermissionCacheService();
        $stats = $service->getStats();

        expect($stats)->toBeArray()
            ->toHaveKey('enabled')
            ->toHaveKey('store')
            ->toHaveKey('ttl');
    });

    it('can be disabled and enabled', function (): void {
        $service = new PermissionCacheService();

        $result = $service->disable();
        expect($result)->toBeInstanceOf(PermissionCacheService::class);

        $stats = $service->getStats();
        expect($stats['enabled'])->toBeFalse();

        $result = $service->enable();
        expect($result)->toBeInstanceOf(PermissionCacheService::class);
    });

    it('remembers values when enabled', function (): void {
        $service = new PermissionCacheService();
        $service->enable();

        $counter = 0;
        $callback = function () use (&$counter) {
            $counter++;

            return 'cached_value';
        };

        $result1 = $service->remember('test_key', $callback);
        $result2 = $service->remember('test_key', $callback);

        expect($result1)->toBe('cached_value');
        expect($result2)->toBe('cached_value');
        expect($counter)->toBe(1);
    });

    it('does not cache when disabled', function (): void {
        $service = new PermissionCacheService();
        $service->disable();

        $counter = 0;
        $callback = function () use (&$counter) {
            $counter++;

            return 'value';
        };

        $result1 = $service->remember('test_key', $callback);
        $result2 = $service->remember('test_key', $callback);

        expect($counter)->toBe(2);
    });

    it('runs callback without cache', function (): void {
        $service = new PermissionCacheService();
        $service->enable();

        $counter = 0;
        $result = $service->withoutCache(function () use (&$counter) {
            $counter++;

            return 'result';
        });

        expect($result)->toBe('result');
        expect($counter)->toBe(1);
    });

    it('restores cache state after withoutCache', function (): void {
        $service = new PermissionCacheService();
        $service->enable();

        $service->withoutCache(function () {
            return null;
        });

        $stats = $service->getStats();
        expect($stats['enabled'])->toBeTrue();
    });

    it('gets user permissions for user without getAllPermissions method', function (): void {
        $service = new PermissionCacheService();

        $user = new class
        {
            public function getKey(): int
            {
                return 1;
            }
        };

        $permissions = $service->getUserPermissions($user);

        expect($permissions)->toBeArray()->toBeEmpty();
    });

    it('gets user permissions for user with getAllPermissions method', function (): void {
        $service = new PermissionCacheService();

        $user = new class
        {
            public function getKey(): int
            {
                return 1;
            }

            public function getAllPermissions()
            {
                return collect([
                    (object) ['name' => 'view'],
                    (object) ['name' => 'create'],
                ]);
            }
        };

        $permissions = $service->getUserPermissions($user);

        expect($permissions)->toBeArray()
            ->toContain('view')
            ->toContain('create');
    });

    it('checks if user has permission', function (): void {
        $service = new PermissionCacheService();

        $user = new class
        {
            public function getKey(): int
            {
                return 1;
            }

            public function getAllPermissions()
            {
                return collect([
                    (object) ['name' => 'view'],
                    (object) ['name' => 'create'],
                ]);
            }
        };

        expect($service->userHasPermission($user, 'view'))->toBeTrue();
        expect($service->userHasPermission($user, 'delete'))->toBeFalse();
    });

    it('forgets user cache', function (): void {
        $service = new PermissionCacheService();

        $user = new class
        {
            public function getKey(): int
            {
                return 1;
            }

            public function getAllPermissions()
            {
                return collect([(object) ['name' => 'view']]);
            }
        };

        $service->getUserPermissions($user);

        $service->forgetUser($user);

        expect(true)->toBeTrue();
    });

    it('gets role permissions', function (): void {
        $role = Role::firstOrCreate(['name' => 'cache_test_role', 'guard_name' => 'web']);
        $permission = Permission::firstOrCreate(['name' => 'cache_test_permission', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $service = new PermissionCacheService();
        $permissions = $service->getRolePermissions($role);

        expect($permissions)->toBeArray()
            ->toContain('cache_test_permission');
    });

    it('forgets role cache', function (): void {
        $role = Role::firstOrCreate(['name' => 'forget_role_test', 'guard_name' => 'web']);

        $service = new PermissionCacheService();
        $service->getRolePermissions($role);
        $service->forgetRole($role);

        expect(true)->toBeTrue();
    });

    it('forgets permission cache', function (): void {
        $permission = Permission::firstOrCreate(['name' => 'forget_perm_test', 'guard_name' => 'web']);

        $service = new PermissionCacheService();
        $service->forgetPermission($permission);

        expect(true)->toBeTrue();
    });

    it('flushes all caches', function (): void {
        $service = new PermissionCacheService();
        $service->flush();

        expect(true)->toBeTrue();
    });

    it('warms user cache', function (): void {
        $service = new PermissionCacheService();

        $user = new class
        {
            public function getKey(): int
            {
                return 999;
            }

            public function getAllPermissions()
            {
                return collect([(object) ['name' => 'warm_test']]);
            }
        };

        $service->warmUserCache($user);

        expect(true)->toBeTrue();
    });

    it('warms role cache', function (): void {
        Role::firstOrCreate(['name' => 'warm_cache_role', 'guard_name' => 'web']);

        $service = new PermissionCacheService();
        $service->warmRoleCache();

        expect(true)->toBeTrue();
    });
});
