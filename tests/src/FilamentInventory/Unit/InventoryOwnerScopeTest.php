<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentInventory\Fixtures\TestOwner;
use AIArmada\Commerce\Tests\FilamentInventory\Fixtures\TestOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentInventory\Support\InventoryOwnerScope;
use AIArmada\Inventory\Models\InventoryLocation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::dropIfExists('filament_inventory_test_owners');

    Schema::create('filament_inventory_test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('inventory.owner.enabled', false);
    config()->set('inventory.owner.include_global', true);

    OwnerContext::clearOverride();
});

it('does not scope queries when owner scoping is disabled', function (): void {
    $global = InventoryLocation::factory()->create([
        'owner_type' => null,
        'owner_id' => null,
    ]);

    config()->set('inventory.owner.enabled', false);

    expect(InventoryOwnerScope::isEnabled())->toBeFalse();
    expect(InventoryOwnerScope::resolveOwner())->toBeNull();
    expect(InventoryOwnerScope::cacheKeySuffix())->toBe('owner=disabled');

    $count = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
        ->whereKey($global->id)
        ->count();

    expect($count)->toBe(1);
});

it('scopes to global-only when enabled but no resolver is bound', function (): void {
    $global = InventoryLocation::factory()->create([
        'owner_type' => null,
        'owner_id' => null,
    ]);

    $owned = InventoryLocation::factory()->create([
        'owner_type' => TestOwner::class,
        'owner_id' => 'some-owner-id',
    ]);

    config()->set('inventory.owner.enabled', true);

    OwnerContext::withOwner(null, function () use ($global, $owned): void {
        expect(InventoryOwnerScope::resolveOwner())->toBeNull();

        $query = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query());

        expect($query->whereKey($global->id)->exists())->toBeTrue();
        expect($query->whereKey($owned->id)->exists())->toBeFalse();

        expect(InventoryOwnerScope::cacheKeySuffix())
            ->toBe('owner=null|includeGlobal=1');
    });
});

it('scopes to the resolved owner and optionally includes global rows', function (): void {
    $ownerA = TestOwner::create(['name' => 'Owner A']);
    $ownerB = TestOwner::create(['name' => 'Owner B']);

    $global = InventoryLocation::factory()->create([
        'owner_type' => null,
        'owner_id' => null,
    ]);

    $locationA = InventoryLocation::factory()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $locationB = InventoryLocation::factory()->create([
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    config()->set('inventory.owner.enabled', true);
    config()->set('inventory.owner.include_global', false);

    app()->bind(AIArmada\CommerceSupport\Contracts\OwnerResolverInterface::class, fn () => new TestOwnerResolver($ownerA));

    $ownerOnlyQuery = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query());

    expect($ownerOnlyQuery->whereKey($locationA->id)->exists())->toBeTrue();
    expect($ownerOnlyQuery->whereKey($global->id)->exists())->toBeFalse();
    expect($ownerOnlyQuery->whereKey($locationB->id)->exists())->toBeFalse();

    expect(InventoryOwnerScope::cacheKeySuffix())
        ->toBe('owner=' . $ownerA->getMorphClass() . ':' . $ownerA->getKey() . '|includeGlobal=0');

    config()->set('inventory.owner.include_global', true);

    expect(InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
        ->whereKey($locationA->id)
        ->exists())->toBeTrue();

    expect(InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
        ->whereKey($global->id)
        ->exists())->toBeTrue();

    expect(InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
        ->whereKey($locationB->id)
        ->exists())->toBeFalse();
});
