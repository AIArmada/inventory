<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Inventory\Exceptions\InsufficientStockException;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\InventoryService;
use Illuminate\Database\Eloquent\Model;

it('scopes availability to current owner and global locations when enabled', function (): void {
    config()->set('inventory.owner.enabled', true);
    config()->set('inventory.owner.include_global', true);

    $ownerA = InventoryItem::create(['name' => 'Owner A']);
    $ownerB = InventoryItem::create(['name' => 'Owner B']);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(
            private readonly ?Model $owner,
        ) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    app()->forgetInstance(InventoryService::class);

    $inventoryable = InventoryItem::create(['name' => 'SKU']);

    $locationA = InventoryLocation::factory()->create([
        'code' => 'OWN-A',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $locationB = InventoryLocation::factory()->create([
        'code' => 'OWN-B',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    $globalLocation = InventoryLocation::factory()->create([
        'code' => 'GLOBAL',
        'owner_type' => null,
        'owner_id' => null,
    ]);

    InventoryLevel::factory()
        ->forInventoryable($inventoryable->getMorphClass(), $inventoryable->getKey())
        ->forLocation($locationA)
        ->create([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

    InventoryLevel::factory()
        ->forInventoryable($inventoryable->getMorphClass(), $inventoryable->getKey())
        ->forLocation($locationB)
        ->create([
            'quantity_on_hand' => 7,
            'quantity_reserved' => 0,
        ]);

    InventoryLevel::factory()
        ->forInventoryable($inventoryable->getMorphClass(), $inventoryable->getKey())
        ->forLocation($globalLocation)
        ->create([
            'quantity_on_hand' => 11,
            'quantity_reserved' => 0,
        ]);

    $service = app(InventoryService::class);

    expect($service->getTotalAvailable($inventoryable))->toBe(16);

    expect($service->getAvailability($inventoryable))->toMatchArray([
        $locationA->id => 5,
        $globalLocation->id => 11,
    ]);
});

it('excludes global inventory when include_global is false', function (): void {
    config()->set('inventory.owner.enabled', true);
    config()->set('inventory.owner.include_global', false);

    $ownerA = InventoryItem::create(['name' => 'Owner A']);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(
            private readonly ?Model $owner,
        ) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    app()->forgetInstance(InventoryService::class);

    $inventoryable = InventoryItem::create(['name' => 'SKU']);

    $locationA = InventoryLocation::factory()->create([
        'code' => 'OWN-A-ONLY',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $globalLocation = InventoryLocation::factory()->create([
        'code' => 'GLOBAL-ONLY',
        'owner_type' => null,
        'owner_id' => null,
    ]);

    InventoryLevel::factory()
        ->forInventoryable($inventoryable->getMorphClass(), $inventoryable->getKey())
        ->forLocation($locationA)
        ->create([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

    InventoryLevel::factory()
        ->forInventoryable($inventoryable->getMorphClass(), $inventoryable->getKey())
        ->forLocation($globalLocation)
        ->create([
            'quantity_on_hand' => 11,
            'quantity_reserved' => 0,
        ]);

    $service = app(InventoryService::class);

    expect($service->getTotalAvailable($inventoryable))->toBe(5);

    expect($service->getAvailability($inventoryable))->toMatchArray([
        $locationA->id => 5,
    ]);
});

it('blocks mutations against locations outside current owner scope', function (): void {
    config()->set('inventory.owner.enabled', true);
    config()->set('inventory.owner.include_global', true);

    $ownerA = InventoryItem::create(['name' => 'Owner A']);
    $ownerB = InventoryItem::create(['name' => 'Owner B']);

    app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
    {
        public function __construct(
            private readonly ?Model $owner,
        ) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });

    app()->forgetInstance(InventoryService::class);

    $inventoryable = InventoryItem::create(['name' => 'SKU']);

    $locationB = InventoryLocation::factory()->create([
        'code' => 'OWN-B-MUT',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    $service = app(InventoryService::class);

    expect(fn () => $service->receive($inventoryable, $locationB->id, 1))
        ->toThrow(InvalidArgumentException::class, 'Invalid location for current owner');

    InventoryLevel::factory()
        ->forInventoryable($inventoryable->getMorphClass(), $inventoryable->getKey())
        ->forLocation($locationB)
        ->create([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

    expect(fn () => $service->ship($inventoryable, $locationB->id, 1))
        ->toThrow(InsufficientStockException::class);
});

it('treats a null resolved owner as global-only (never owner_type-only corrupt rows)', function (): void {
    config()->set('inventory.owner.enabled', true);
    config()->set('inventory.owner.include_global', true);

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    app()->forgetInstance(InventoryService::class);

    $ownerA = InventoryItem::create(['name' => 'Owner A']);
    $inventoryable = InventoryItem::create(['name' => 'SKU']);

    $globalLocation = InventoryLocation::factory()->create([
        'code' => 'GLOBAL-NULL-OWNER',
        'owner_type' => null,
        'owner_id' => null,
    ]);

    $corruptLocation = InventoryLocation::factory()->create([
        'code' => 'CORRUPT-NULL-OWNER',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => null,
    ]);

    InventoryLevel::factory()
        ->forInventoryable($inventoryable->getMorphClass(), $inventoryable->getKey())
        ->forLocation($globalLocation)
        ->create([
            'quantity_on_hand' => 11,
            'quantity_reserved' => 0,
        ]);

    InventoryLevel::factory()
        ->forInventoryable($inventoryable->getMorphClass(), $inventoryable->getKey())
        ->forLocation($corruptLocation)
        ->create([
            'quantity_on_hand' => 7,
            'quantity_reserved' => 0,
        ]);

    $service = app(InventoryService::class);

    expect($service->getTotalAvailable($inventoryable))->toBe(11);
    expect($service->getAvailability($inventoryable))->toMatchArray([
        $globalLocation->id => 11,
    ]);
});
