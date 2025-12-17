<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\BatchService;
use Illuminate\Database\Eloquent\Model;

/**
 * @return array{
 *     ownerA: InventoryItem,
 *     ownerB: InventoryItem,
 *     sku: InventoryItem,
 *     locationA: InventoryLocation,
 *     locationB: InventoryLocation,
 *     locationGlobal: InventoryLocation,
 *     service: BatchService
 * }
 */
function makeOwnerScopedBatchServiceFixture(): array
{
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

    $sku = InventoryItem::create(['name' => 'SKU']);

    $locationA = InventoryLocation::factory()->create([
        'code' => 'LOC-A',
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    $locationB = InventoryLocation::factory()->create([
        'code' => 'LOC-B',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    $locationGlobal = InventoryLocation::factory()->create([
        'code' => 'LOC-G',
        'owner_type' => null,
        'owner_id' => null,
    ]);

    $service = new BatchService;

    return [
        'ownerA' => $ownerA,
        'ownerB' => $ownerB,
        'sku' => $sku,
        'locationA' => $locationA,
        'locationB' => $locationB,
        'locationGlobal' => $locationGlobal,
        'service' => $service,
    ];
}

it('scopes BatchService::findByBatchNumber to current owner (and global when enabled)', function (): void {
    $fixture = makeOwnerScopedBatchServiceFixture();
    $sku = $fixture['sku'];
    $locationA = $fixture['locationA'];
    $locationB = $fixture['locationB'];
    $locationGlobal = $fixture['locationGlobal'];
    $service = $fixture['service'];

    InventoryBatch::factory()->create([
        'inventoryable_type' => $sku->getMorphClass(),
        'inventoryable_id' => $sku->getKey(),
        'batch_number' => 'BATCH-A',
        'location_id' => $locationA->id,
    ]);

    InventoryBatch::factory()->create([
        'inventoryable_type' => $sku->getMorphClass(),
        'inventoryable_id' => $sku->getKey(),
        'batch_number' => 'BATCH-B',
        'location_id' => $locationB->id,
    ]);

    InventoryBatch::factory()->create([
        'inventoryable_type' => $sku->getMorphClass(),
        'inventoryable_id' => $sku->getKey(),
        'batch_number' => 'BATCH-G',
        'location_id' => $locationGlobal->id,
    ]);

    expect($service->findByBatchNumber('BATCH-A'))
        ->not->toBeNull();

    expect($service->findByBatchNumber('BATCH-B'))
        ->toBeNull();

    expect($service->findByBatchNumber('BATCH-G'))
        ->not->toBeNull();
});

it('can exclude global batches when include_global is false', function (): void {
    $fixture = makeOwnerScopedBatchServiceFixture();
    $sku = $fixture['sku'];
    $locationGlobal = $fixture['locationGlobal'];
    $service = $fixture['service'];

    config()->set('inventory.owner.include_global', false);

    InventoryBatch::factory()->create([
        'inventoryable_type' => $sku->getMorphClass(),
        'inventoryable_id' => $sku->getKey(),
        'batch_number' => 'BATCH-G2',
        'location_id' => $locationGlobal->id,
    ]);

    expect($service->findByBatchNumber('BATCH-G2'))
        ->toBeNull();
});

it('scopes BatchService::getBatchesForModel to current owner (and global when enabled)', function (): void {
    $fixture = makeOwnerScopedBatchServiceFixture();
    $sku = $fixture['sku'];
    $locationA = $fixture['locationA'];
    $locationB = $fixture['locationB'];
    $locationGlobal = $fixture['locationGlobal'];
    $service = $fixture['service'];

    InventoryBatch::factory()->create([
        'inventoryable_type' => $sku->getMorphClass(),
        'inventoryable_id' => $sku->getKey(),
        'batch_number' => 'BATCH-A1',
        'location_id' => $locationA->id,
    ]);

    InventoryBatch::factory()->create([
        'inventoryable_type' => $sku->getMorphClass(),
        'inventoryable_id' => $sku->getKey(),
        'batch_number' => 'BATCH-B1',
        'location_id' => $locationB->id,
    ]);

    InventoryBatch::factory()->create([
        'inventoryable_type' => $sku->getMorphClass(),
        'inventoryable_id' => $sku->getKey(),
        'batch_number' => 'BATCH-G1',
        'location_id' => $locationGlobal->id,
    ]);

    $batches = $service->getBatchesForModel($sku);

    expect($batches->pluck('batch_number')->all())
        ->toContain('BATCH-A1', 'BATCH-G1')
        ->not->toContain('BATCH-B1');
});

it('prevents creating batches at locations outside current owner scope', function (): void {
    $fixture = makeOwnerScopedBatchServiceFixture();
    $sku = $fixture['sku'];
    $locationB = $fixture['locationB'];
    $service = $fixture['service'];

    expect(fn () => $service->createBatch(
        $sku,
        'BATCH-X',
        $locationB->id,
        10
    ))->toThrow(InvalidArgumentException::class, 'Invalid location for current owner');
});
