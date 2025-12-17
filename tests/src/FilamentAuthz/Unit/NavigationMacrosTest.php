<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Services\PermissionAggregator;
use AIArmada\FilamentAuthz\Support\Macros\NavigationItemMacros;
use AIArmada\FilamentAuthz\Support\Macros\NavigationMacros;
use Filament\Navigation\NavigationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

afterEach(function (): void {
    \Mockery::close();
});

beforeEach(function (): void {
    NavigationMacros::register();
    NavigationItemMacros::register();
});

test('navigation macros hide items when unauthenticated', function (): void {
    $item = NavigationItem::make('Orders')->visibleForPermission('orders.view');

    expect($item->isVisible())->toBeFalse();

    $anyItem = NavigationItem::make('Orders Any')->visibleForAnyPermission(['orders.view', 'orders.update']);
    expect($anyItem->isVisible())->toBeFalse();

    $allItem = NavigationItem::make('Orders All')->visibleForAllPermissions(['orders.view', 'orders.update']);
    expect($allItem->isVisible())->toBeFalse();
});

test('visibleForPermission uses the permission aggregator', function (): void {
    $user = User::create([
        'name' => 'Nav Macro User',
        'email' => 'nav-macro-user@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $aggregator = \Mockery::mock(PermissionAggregator::class);
    $aggregator->shouldReceive('userHasPermission')
        ->once()
        ->withArgs(fn (object $passedUser, string $permission): bool => ($passedUser->getKey() === $user->getKey()) && ($permission === 'orders.view'))
        ->andReturn(true);

    app()->instance(PermissionAggregator::class, $aggregator);

    $item = NavigationItem::make('Orders')->visibleForPermission('orders.view');

    expect($item->isVisible())->toBeTrue();
});

test('visibleForAnyPermission and visibleForAllPermissions delegate to the aggregator', function (): void {
    $user = User::create([
        'name' => 'Nav Macro Any/All',
        'email' => 'nav-macro-any-all@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $aggregator = \Mockery::mock(PermissionAggregator::class);
    $aggregator->shouldReceive('userHasAnyPermission')
        ->once()
        ->andReturnTrue();
    $aggregator->shouldReceive('userHasAllPermissions')
        ->once()
        ->andReturnFalse();

    app()->instance(PermissionAggregator::class, $aggregator);

    $anyItem = NavigationItem::make('Any')->visibleForAnyPermission(['orders.view', 'orders.update']);
    expect($anyItem->isVisible())->toBeTrue();

    $allItem = NavigationItem::make('All')->visibleForAllPermissions(['orders.view', 'orders.update']);
    expect($allItem->isVisible())->toBeFalse();
});

test('visibleForRole checks user roles', function (): void {
    Role::create(['name' => 'Admin', 'guard_name' => 'web']);

    $user = User::create([
        'name' => 'Nav Role User',
        'email' => 'nav-role-user@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);
    $user->assignRole('Admin');

    $item = NavigationItem::make('Admin')->visibleForRole(['Admin', 'Manager']);

    expect($item->isVisible())->toBeTrue();
});

test('navigation item macros require permission or role', function (): void {
    $user = User::create([
        'name' => 'Nav Item Macro User',
        'email' => 'nav-item-macro-user@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    Permission::create(['name' => 'orders.view', 'guard_name' => 'web']);
    $user->givePermissionTo('orders.view');

    Role::create(['name' => 'Manager', 'guard_name' => 'web']);
    $user->assignRole('Manager');

    $permissionItem = NavigationItem::make('Orders')->requiresPermission('orders.view');
    expect($permissionItem->isVisible())->toBeTrue();

    $roleItem = NavigationItem::make('Management')->requiresRole(['Manager', 'Admin']);
    expect($roleItem->isVisible())->toBeTrue();
});
