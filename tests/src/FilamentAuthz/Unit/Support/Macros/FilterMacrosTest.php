<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use AIArmada\FilamentAuthz\Support\Macros\FilterMacros;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Auth;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function createFilterMacrosTestUser(array $attributes = []): User
{
    return User::create(array_merge([
        'name' => 'Test User ' . uniqid(),
        'email' => 'test' . uniqid() . '@example.com',
        'password' => bcrypt('password'),
    ], $attributes));
}

beforeEach(function (): void {
    FilterMacros::register();
});

describe('FilterMacros::register', function (): void {
    it('registers filter macros', function (): void {
        expect(Filter::hasMacro('visibleForPermission'))->toBeTrue();
        expect(Filter::hasMacro('visibleForRole'))->toBeTrue();
    });

    it('registers select filter macros', function (): void {
        expect(SelectFilter::hasMacro('roleOptions'))->toBeTrue();
        expect(SelectFilter::hasMacro('permissionOptions'))->toBeTrue();
        expect(SelectFilter::hasMacro('permissionGroupOptions'))->toBeTrue();
    });
});

describe('Filter::visibleForPermission', function (): void {
    it('returns filter instance for chaining', function (): void {
        $filter = Filter::make('test');
        $result = $filter->visibleForPermission('test.permission');

        expect($result)->toBeInstanceOf(Filter::class);
    });

    it('hides filter when user is null', function (): void {
        Auth::shouldReceive('user')->andReturn(null);

        $filter = Filter::make('test')->visibleForPermission('test.permission');

        expect($filter)->toBeInstanceOf(Filter::class);
    });

    it('uses aggregator for permission check', function (): void {
        $user = createFilterMacrosTestUser();
        Auth::shouldReceive('user')->andReturn($user);

        $this->mock(PermissionAggregator::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('userHasPermission')
                ->with($user, 'test.permission')
                ->andReturn(true);
        });

        $filter = Filter::make('test')->visibleForPermission('test.permission');

        expect($filter)->toBeInstanceOf(Filter::class);
    });
});

describe('Filter::visibleForRole', function (): void {
    it('returns filter instance for chaining', function (): void {
        $filter = Filter::make('test');
        $result = $filter->visibleForRole('admin');

        expect($result)->toBeInstanceOf(Filter::class);
    });

    it('accepts string role', function (): void {
        $filter = Filter::make('test')->visibleForRole('admin');

        expect($filter)->toBeInstanceOf(Filter::class);
    });

    it('accepts array of roles', function (): void {
        $filter = Filter::make('test')->visibleForRole(['admin', 'editor']);

        expect($filter)->toBeInstanceOf(Filter::class);
    });
});

describe('SelectFilter::roleOptions', function (): void {
    it('returns select filter instance for chaining', function (): void {
        Role::create(['name' => 'admin_' . uniqid(), 'guard_name' => 'web']);

        $filter = SelectFilter::make('test');
        $result = $filter->roleOptions();

        expect($result)->toBeInstanceOf(SelectFilter::class);
    });

    it('loads role options', function (): void {
        $role = Role::create(['name' => 'unique_role_' . uniqid(), 'guard_name' => 'web']);

        $filter = SelectFilter::make('test')->roleOptions();

        expect($filter)->toBeInstanceOf(SelectFilter::class);
    });
});

describe('SelectFilter::permissionOptions', function (): void {
    it('returns select filter instance for chaining', function (): void {
        Permission::create(['name' => 'test.permission.' . uniqid(), 'guard_name' => 'web']);

        $filter = SelectFilter::make('test');
        $result = $filter->permissionOptions();

        expect($result)->toBeInstanceOf(SelectFilter::class);
    });

    it('loads all permissions without prefix', function (): void {
        $permission = Permission::create(['name' => 'all.permission.' . uniqid(), 'guard_name' => 'web']);

        $filter = SelectFilter::make('test')->permissionOptions();

        expect($filter)->toBeInstanceOf(SelectFilter::class);
    });

    it('filters permissions by prefix', function (): void {
        $permission = Permission::create(['name' => 'prefix.permission.' . uniqid(), 'guard_name' => 'web']);

        $filter = SelectFilter::make('test')->permissionOptions('prefix');

        expect($filter)->toBeInstanceOf(SelectFilter::class);
    });
});

describe('SelectFilter::permissionGroupOptions', function (): void {
    it('returns select filter instance for chaining', function (): void {
        Permission::create(['name' => 'group.permission.' . uniqid(), 'guard_name' => 'web']);

        $filter = SelectFilter::make('test');
        $result = $filter->permissionGroupOptions();

        expect($result)->toBeInstanceOf(SelectFilter::class);
    });

    it('groups permissions by first segment', function (): void {
        Permission::create(['name' => 'products.view.' . uniqid(), 'guard_name' => 'web']);
        Permission::create(['name' => 'products.edit.' . uniqid(), 'guard_name' => 'web']);
        Permission::create(['name' => 'orders.view.' . uniqid(), 'guard_name' => 'web']);

        $filter = SelectFilter::make('test')->permissionGroupOptions();

        expect($filter)->toBeInstanceOf(SelectFilter::class);
    });
});
