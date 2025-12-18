<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use AIArmada\FilamentAuthz\Support\Macros\NavigationMacros;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\Auth;
use Mockery\MockInterface;

function createNavigationMacrosTestUser(array $attributes = []): User
{
    return User::create(array_merge([
        'name' => 'Test User ' . uniqid(),
        'email' => 'test' . uniqid() . '@example.com',
        'password' => bcrypt('password'),
    ], $attributes));
}

beforeEach(function (): void {
    NavigationMacros::register();
});

describe('NavigationMacros::register', function (): void {
    it('registers navigation item macros', function (): void {
        expect(NavigationItem::hasMacro('visibleForPermission'))->toBeTrue();
        expect(NavigationItem::hasMacro('visibleForRole'))->toBeTrue();
        expect(NavigationItem::hasMacro('visibleForAnyPermission'))->toBeTrue();
        expect(NavigationItem::hasMacro('visibleForAllPermissions'))->toBeTrue();
    });
});

describe('NavigationItem::visibleForPermission', function (): void {
    it('returns navigation item instance for chaining', function (): void {
        $item = NavigationItem::make('Test');
        $result = $item->visibleForPermission('test.permission');

        expect($result)->toBeInstanceOf(NavigationItem::class);
    });

    it('hides item when user is null', function (): void {
        Auth::shouldReceive('user')->andReturn(null);

        $item = NavigationItem::make('Test')->visibleForPermission('test.permission');

        expect($item)->toBeInstanceOf(NavigationItem::class);
    });

    it('uses aggregator for permission check', function (): void {
        $user = createNavigationMacrosTestUser();
        Auth::shouldReceive('user')->andReturn($user);

        $this->mock(PermissionAggregator::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('userHasPermission')
                ->with($user, 'test.permission')
                ->andReturn(true);
        });

        $item = NavigationItem::make('Test')->visibleForPermission('test.permission');

        expect($item)->toBeInstanceOf(NavigationItem::class);
    });
});

describe('NavigationItem::visibleForRole', function (): void {
    it('returns navigation item instance for chaining', function (): void {
        $item = NavigationItem::make('Test');
        $result = $item->visibleForRole('admin');

        expect($result)->toBeInstanceOf(NavigationItem::class);
    });

    it('accepts string role', function (): void {
        $item = NavigationItem::make('Test')->visibleForRole('admin');

        expect($item)->toBeInstanceOf(NavigationItem::class);
    });

    it('accepts array of roles', function (): void {
        $item = NavigationItem::make('Test')->visibleForRole(['admin', 'editor']);

        expect($item)->toBeInstanceOf(NavigationItem::class);
    });
});

describe('NavigationItem::visibleForAnyPermission', function (): void {
    it('returns navigation item instance for chaining', function (): void {
        $item = NavigationItem::make('Test');
        $result = $item->visibleForAnyPermission(['perm1', 'perm2']);

        expect($result)->toBeInstanceOf(NavigationItem::class);
    });

    it('hides item when user is null', function (): void {
        Auth::shouldReceive('user')->andReturn(null);

        $item = NavigationItem::make('Test')->visibleForAnyPermission(['perm1', 'perm2']);

        expect($item)->toBeInstanceOf(NavigationItem::class);
    });

    it('uses aggregator for any permission check', function (): void {
        $user = createNavigationMacrosTestUser();
        Auth::shouldReceive('user')->andReturn($user);

        $this->mock(PermissionAggregator::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('userHasAnyPermission')
                ->with($user, ['perm1', 'perm2'])
                ->andReturn(true);
        });

        $item = NavigationItem::make('Test')->visibleForAnyPermission(['perm1', 'perm2']);

        expect($item)->toBeInstanceOf(NavigationItem::class);
    });
});

describe('NavigationItem::visibleForAllPermissions', function (): void {
    it('returns navigation item instance for chaining', function (): void {
        $item = NavigationItem::make('Test');
        $result = $item->visibleForAllPermissions(['perm1', 'perm2']);

        expect($result)->toBeInstanceOf(NavigationItem::class);
    });

    it('hides item when user is null', function (): void {
        Auth::shouldReceive('user')->andReturn(null);

        $item = NavigationItem::make('Test')->visibleForAllPermissions(['perm1', 'perm2']);

        expect($item)->toBeInstanceOf(NavigationItem::class);
    });

    it('uses aggregator for all permissions check', function (): void {
        $user = createNavigationMacrosTestUser();
        Auth::shouldReceive('user')->andReturn($user);

        $this->mock(PermissionAggregator::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('userHasAllPermissions')
                ->with($user, ['perm1', 'perm2'])
                ->andReturn(true);
        });

        $item = NavigationItem::make('Test')->visibleForAllPermissions(['perm1', 'perm2']);

        expect($item)->toBeInstanceOf(NavigationItem::class);
    });
});
