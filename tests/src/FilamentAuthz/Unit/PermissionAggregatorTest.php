<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\FilamentAuthz\Unit;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Services\ImplicitPermissionService;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use AIArmada\FilamentAuthz\Services\RoleInheritanceService;
use AIArmada\FilamentAuthz\Services\WildcardPermissionResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->roleInheritance = app(RoleInheritanceService::class);
    $this->wildcardResolver = app(WildcardPermissionResolver::class);
    $this->implicitService = app(ImplicitPermissionService::class);

    $this->aggregator = new PermissionAggregator(
        $this->roleInheritance,
        $this->wildcardResolver,
        $this->implicitService
    );
});

afterEach(function (): void {
    Mockery::close();
});

describe('PermissionAggregator', function (): void {
    describe('getEffectivePermissions', function (): void {
        it('returns empty collection for objects without getRoleNames', function (): void {
            $nonUser = new class
            {
                public string $name = 'Not a user';
            };

            $permissions = $this->aggregator->getEffectivePermissions($nonUser);

            expect($permissions)->toBeEmpty();
            expect($permissions)->toBeInstanceOf(EloquentCollection::class);
        });

        it('returns direct permissions for user', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'direct-perm@example.com',
                'password' => 'password',
            ]);

            Permission::create(['name' => 'direct.permission', 'guard_name' => 'web']);
            $user->givePermissionTo('direct.permission');
            Cache::flush();

            $permissions = $this->aggregator->getEffectivePermissions($user);

            expect($permissions)->toBeInstanceOf(EloquentCollection::class);
            expect($permissions->pluck('name')->toArray())->toContain('direct.permission');
        });

        it('returns role permissions for user', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'role-perm@example.com',
                'password' => 'password',
            ]);

            $role = Role::create(['name' => 'role-with-perm', 'guard_name' => 'web']);
            Permission::create(['name' => 'role.permission', 'guard_name' => 'web']);
            $role->givePermissionTo('role.permission');
            $user->assignRole($role);
            Cache::flush();

            $permissions = $this->aggregator->getEffectivePermissions($user);

            expect($permissions)->toBeInstanceOf(EloquentCollection::class);
            expect($permissions->pluck('name')->toArray())->toContain('role.permission');
        });

        it('uses caching for user permissions', function (): void {
            $user = User::create([
                'name' => 'Cache User',
                'email' => 'cache-user@example.com',
                'password' => 'password',
            ]);

            Cache::flush();
            $this->aggregator->getEffectivePermissions($user);

            $cacheKey = 'permissions:aggregated:user:' . $user->getKey();
            expect(Cache::has($cacheKey))->toBeTrue();
        });
    });

    describe('getEffectivePermissionNames', function (): void {
        it('returns permission names as collection', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'perm-names@example.com',
                'password' => 'password',
            ]);

            Permission::create(['name' => 'test.permission.name', 'guard_name' => 'web']);
            $user->givePermissionTo('test.permission.name');
            Cache::flush();

            $names = $this->aggregator->getEffectivePermissionNames($user);

            expect($names)->toBeInstanceOf(\Illuminate\Support\Collection::class);
            expect($names->toArray())->toContain('test.permission.name');
        });
    });

    describe('getEffectiveRolePermissions', function (): void {
        it('gets direct role permissions', function (): void {
            $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
            $permission = Permission::create(['name' => 'posts.edit', 'guard_name' => 'web']);
            $role->givePermissionTo($permission);

            Cache::flush();
            $permissions = $this->aggregator->getEffectiveRolePermissions($role);

            expect($permissions->pluck('name')->toArray())->toContain('posts.edit');
        });

        it('uses caching for role permissions', function (): void {
            $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

            // First call
            $this->aggregator->getEffectiveRolePermissions($role);

            $cacheKey = 'permissions:aggregated:role:' . $role->id;
            expect(Cache::has($cacheKey))->toBeTrue();
        });

        it('includes inherited permissions from parent roles', function (): void {
            $parentRole = Role::create(['name' => 'parent-role', 'guard_name' => 'web']);
            $childRole = Role::create(['name' => 'child-role', 'guard_name' => 'web']);

            $parentPermission = Permission::create(['name' => 'parent.permission', 'guard_name' => 'web']);
            $parentRole->givePermissionTo($parentPermission);

            // Set parent relationship if the service supports it
            $this->roleInheritance->setParent($childRole, $parentRole);

            Cache::flush();
            $permissions = $this->aggregator->getEffectiveRolePermissions($childRole);

            expect($permissions->pluck('name')->toArray())->toContain('parent.permission');
        });
    });

    describe('getPermissionSource', function (): void {
        it('identifies direct permission source', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            Permission::create(['name' => 'posts.view', 'guard_name' => 'web']);
            $user->givePermissionTo('posts.view');

            $source = $this->aggregator->getPermissionSource($user, 'posts.view');

            expect($source['type'])->toBe('direct');
        });

        it('identifies role permission source', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
            $permission = Permission::create(['name' => 'posts.edit', 'guard_name' => 'web']);
            $role->givePermissionTo($permission);
            $user->assignRole($role);

            $source = $this->aggregator->getPermissionSource($user, 'posts.edit');

            expect($source['type'])->toBe('role')
                ->and($source['source'])->toBe('editor');
        });

        it('identifies inherited permission source', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'inherited-source@example.com',
                'password' => 'password',
            ]);

            $parentRole = Role::create(['name' => 'source-parent', 'guard_name' => 'web']);
            $childRole = Role::create(['name' => 'source-child', 'guard_name' => 'web']);
            $permission = Permission::create(['name' => 'inherited.source.perm', 'guard_name' => 'web']);

            $parentRole->givePermissionTo($permission);
            $this->roleInheritance->setParent($childRole, $parentRole);
            $user->assignRole($childRole);
            Cache::flush();

            $source = $this->aggregator->getPermissionSource($user, 'inherited.source.perm');

            expect($source['type'])->toBe('inherited');
            expect($source['source'])->toBe('source-parent');
            expect($source['via'])->toBe('source-child');
        });

        it('identifies wildcard permission source', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'wildcard-source@example.com',
                'password' => 'password',
            ]);

            Permission::create(['name' => 'admin.*', 'guard_name' => 'web']);
            $user->givePermissionTo('admin.*');
            Cache::flush();

            $source = $this->aggregator->getPermissionSource($user, 'admin.users');

            expect($source['type'])->toBe('wildcard');
            expect($source['source'])->toBe('admin.*');
        });

        it('identifies implicit permission source', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'implicit-source@example.com',
                'password' => 'password',
            ]);

            config(['filament-authz.implicit_permissions' => [
                'content.manage' => ['content.view', 'content.edit'],
            ]]);

            Permission::create(['name' => 'content.manage', 'guard_name' => 'web']);
            $user->givePermissionTo('content.manage');
            Cache::flush();
            $this->implicitService->clearCache();

            $source = $this->aggregator->getPermissionSource($user, 'content.view');

            expect($source['type'])->toBe('implicit');
            expect($source['source'])->toBe('content.manage');
        });

        it('returns none when permission not found', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'no-source@example.com',
                'password' => 'password',
            ]);

            $source = $this->aggregator->getPermissionSource($user, 'nonexistent.permission');

            expect($source['type'])->toBe('none');
            expect($source['source'])->toBeNull();
            expect($source['via'])->toBeNull();
        });
    });

    describe('getEffectiveRoles', function (): void {
        it('returns empty for objects without roles method', function (): void {
            $nonUser = new class
            {
                public string $name = 'Not a user';
            };

            $roles = $this->aggregator->getEffectiveRoles($nonUser);

            expect($roles)->toBeEmpty();
            expect($roles)->toBeInstanceOf(EloquentCollection::class);
        });

        it('returns direct roles for user', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'effective-roles@example.com',
                'password' => 'password',
            ]);

            $role = Role::create(['name' => 'effective-role-test', 'guard_name' => 'web']);
            $user->assignRole($role);

            $roles = $this->aggregator->getEffectiveRoles($user);

            expect($roles)->toBeInstanceOf(EloquentCollection::class);
            expect($roles->pluck('name')->toArray())->toContain('effective-role-test');
        });

        it('returns inherited roles from parent', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'inherited-roles@example.com',
                'password' => 'password',
            ]);

            $parentRole = Role::create(['name' => 'inherited-parent', 'guard_name' => 'web']);
            $childRole = Role::create(['name' => 'inherited-child', 'guard_name' => 'web']);
            $this->roleInheritance->setParent($childRole, $parentRole);
            $user->assignRole($childRole);

            $roles = $this->aggregator->getEffectiveRoles($user);

            expect($roles->pluck('name')->toArray())->toContain('inherited-child');
            expect($roles->pluck('name')->toArray())->toContain('inherited-parent');
        });
    });

    describe('userHasAnyPermission', function (): void {
        it('returns false for empty permissions array', function (): void {
            $nonUser = new class
            {
                public string $name = 'Not a user';
            };

            $result = $this->aggregator->userHasAnyPermission($nonUser, []);

            expect($result)->toBeFalse();
        });

        it('returns false when user has no getRoleNames method', function (): void {
            $nonUser = new class
            {
                public string $name = 'Not a user';
            };

            $result = $this->aggregator->userHasAnyPermission($nonUser, ['test.permission']);

            expect($result)->toBeFalse();
        });

        it('returns true when user has one of the permissions', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'any-perm@example.com',
                'password' => 'password',
            ]);

            Permission::create(['name' => 'perm.one', 'guard_name' => 'web']);
            $user->givePermissionTo('perm.one');
            Cache::flush();

            $result = $this->aggregator->userHasAnyPermission($user, ['perm.one', 'perm.two', 'perm.three']);

            expect($result)->toBeTrue();
        });

        it('returns false when user has none of the permissions', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'no-any-perm@example.com',
                'password' => 'password',
            ]);

            Cache::flush();
            $result = $this->aggregator->userHasAnyPermission($user, ['perm.x', 'perm.y', 'perm.z']);

            expect($result)->toBeFalse();
        });
    });

    describe('userHasAllPermissions', function (): void {
        it('returns true for empty permissions array', function (): void {
            $nonUser = new class
            {
                public string $name = 'Not a user';
            };

            $result = $this->aggregator->userHasAllPermissions($nonUser, []);

            expect($result)->toBeTrue();
        });

        it('returns false when user has no permissions', function (): void {
            $nonUser = new class
            {
                public string $name = 'Not a user';
            };

            $result = $this->aggregator->userHasAllPermissions($nonUser, ['test.permission']);

            expect($result)->toBeFalse();
        });

        it('returns true when user has all permissions', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'all-perms@example.com',
                'password' => 'password',
            ]);

            Permission::create(['name' => 'all.one', 'guard_name' => 'web']);
            Permission::create(['name' => 'all.two', 'guard_name' => 'web']);
            $user->givePermissionTo(['all.one', 'all.two']);
            Cache::flush();

            $result = $this->aggregator->userHasAllPermissions($user, ['all.one', 'all.two']);

            expect($result)->toBeTrue();
        });

        it('returns false when user is missing one permission', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'partial-perms@example.com',
                'password' => 'password',
            ]);

            Permission::create(['name' => 'partial.one', 'guard_name' => 'web']);
            $user->givePermissionTo('partial.one');
            Cache::flush();

            $result = $this->aggregator->userHasAllPermissions($user, ['partial.one', 'partial.two']);

            expect($result)->toBeFalse();
        });
    });

    describe('userHasPermission', function (): void {
        it('returns false when user has no getRoleNames method', function (): void {
            $nonUser = new class
            {
                public string $name = 'Not a user';
            };

            $result = $this->aggregator->userHasPermission($nonUser, 'test.permission');

            expect($result)->toBeFalse();
        });

        it('returns true for direct permission match', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'direct-match@example.com',
                'password' => 'password',
            ]);

            Permission::create(['name' => 'direct.match.perm', 'guard_name' => 'web']);
            $user->givePermissionTo('direct.match.perm');
            Cache::flush();

            $result = $this->aggregator->userHasPermission($user, 'direct.match.perm');

            expect($result)->toBeTrue();
        });

        it('returns true for wildcard permission match', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'wildcard-match@example.com',
                'password' => 'password',
            ]);

            Permission::create(['name' => 'posts.*', 'guard_name' => 'web']);
            $user->givePermissionTo('posts.*');
            Cache::flush();

            $result = $this->aggregator->userHasPermission($user, 'posts.view');

            expect($result)->toBeTrue();
        });

        it('returns true for implicit permission match', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'implicit-match@example.com',
                'password' => 'password',
            ]);

            // Configure implicit permission
            config(['filament-authz.implicit_permissions' => [
                'posts.manage' => ['posts.view', 'posts.edit'],
            ]]);

            Permission::create(['name' => 'posts.manage', 'guard_name' => 'web']);
            $user->givePermissionTo('posts.manage');
            Cache::flush();
            $this->implicitService->clearCache();

            $result = $this->aggregator->userHasPermission($user, 'posts.view');

            expect($result)->toBeTrue();
        });

        it('returns false when permission not found', function (): void {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'no-perm@example.com',
                'password' => 'password',
            ]);

            Cache::flush();
            $result = $this->aggregator->userHasPermission($user, 'non.existent.permission');

            expect($result)->toBeFalse();
        });
    });

    describe('clearRoleCache', function (): void {
        it('clears role cache', function (): void {
            $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

            // Populate cache
            $this->aggregator->getEffectiveRolePermissions($role);

            $cacheKey = 'permissions:aggregated:role:' . $role->id;
            expect(Cache::has($cacheKey))->toBeTrue();

            $this->aggregator->clearRoleCache($role);

            expect(Cache::has($cacheKey))->toBeFalse();
        });

        it('clears descendant role caches', function (): void {
            $parentRole = Role::create(['name' => 'clear-parent', 'guard_name' => 'web']);
            $childRole = Role::create(['name' => 'clear-child', 'guard_name' => 'web']);
            $this->roleInheritance->setParent($childRole, $parentRole);

            // Populate caches
            $this->aggregator->getEffectiveRolePermissions($childRole);

            $this->aggregator->clearRoleCache($parentRole);

            // Child cache should be cleared
            $childCacheKey = 'permissions:aggregated:role:' . $childRole->id;
            expect(Cache::has($childCacheKey))->toBeFalse();
        });
    });

    describe('clearAllCache', function (): void {
        it('clears all related caches', function (): void {
            // This just verifies no exception is thrown
            $this->aggregator->clearAllCache();
            expect(true)->toBeTrue();
        });
    });

    describe('clearUserCache', function (): void {
        it('clears user cache key', function (): void {
            // Use a simple mock to test the cache key format
            $mockUser = new class
            {
                public function getKey(): string
                {
                    return 'test-user-123';
                }
            };

            // The method should call Cache::forget with the correct key
            Cache::shouldReceive('forget')
                ->once()
                ->with('permissions:aggregated:user:test-user-123');

            $this->aggregator->clearUserCache($mockUser);
        });
    });
});
