<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Inventory\Enums\SerialCondition;
use AIArmada\Inventory\Enums\SerialStatus;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventorySerial;
use AIArmada\Inventory\Services\SerialService;
use Illuminate\Database\Eloquent\Model;

/**
 * @return array{
 *     ownerA: InventoryItem,
 *     ownerB: InventoryItem,
 *     sku: InventoryItem,
 *     locationA: InventoryLocation,
 *     locationB: InventoryLocation,
 *     locationGlobal: InventoryLocation,
 *     service: SerialService
 * }
 */
function makeOwnerScopedSerialServiceFixture(): array
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
        'is_active' => true,
    ]);

    $locationB = InventoryLocation::factory()->create([
        'code' => 'LOC-B',
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
        'is_active' => true,
    ]);

    $locationGlobal = InventoryLocation::factory()->create([
        'code' => 'LOC-G',
        'owner_type' => null,
        'owner_id' => null,
        'is_active' => true,
    ]);

    $service = new SerialService;

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

it('scopes SerialService::findBySerialNumber to current owner (and global when enabled)', function (): void {
    $fixture = makeOwnerScopedSerialServiceFixture();
    $sku = $fixture['sku'];
    $locationA = $fixture['locationA'];
    $locationB = $fixture['locationB'];
    $locationGlobal = $fixture['locationGlobal'];
    $service = $fixture['service'];

    InventorySerial::factory()->create([
        'inventoryable_type' => $sku->getMorphClass(),
        'inventoryable_id' => $sku->getKey(),
        'serial_number' => 'SN-A',
        'location_id' => $locationA->id,
        'status' => SerialStatus::Available->value,
        'condition' => SerialCondition::New->value,
    ]);

    InventorySerial::factory()->create([
        'inventoryable_type' => $sku->getMorphClass(),
        'inventoryable_id' => $sku->getKey(),
        'serial_number' => 'SN-B',
        'location_id' => $locationB->id,
        'status' => SerialStatus::Available->value,
        'condition' => SerialCondition::New->value,
    ]);

    InventorySerial::factory()->create([
        'inventoryable_type' => $sku->getMorphClass(),
        'inventoryable_id' => $sku->getKey(),
        'serial_number' => 'SN-G',
        'location_id' => $locationGlobal->id,
        'status' => SerialStatus::Available->value,
        'condition' => SerialCondition::New->value,
    ]);

    expect($service->findBySerialNumber('SN-A'))
        ->not->toBeNull();

    expect($service->findBySerialNumber('SN-B'))
        ->toBeNull();

    expect($service->findBySerialNumber('SN-G'))
        ->not->toBeNull();
});

it('can exclude global serials when include_global is false', function (): void {
    $fixture = makeOwnerScopedSerialServiceFixture();
    $sku = $fixture['sku'];
    $locationGlobal = $fixture['locationGlobal'];
    $service = $fixture['service'];

    config()->set('inventory.owner.include_global', false);

    InventorySerial::factory()->create([
        'inventoryable_type' => $sku->getMorphClass(),
        'inventoryable_id' => $sku->getKey(),
        'serial_number' => 'SN-G2',
        'location_id' => $locationGlobal->id,
        'status' => SerialStatus::Available->value,
        'condition' => SerialCondition::New->value,
    ]);

    expect($service->findBySerialNumber('SN-G2'))
        ->toBeNull();
});

it('prevents registering serials at locations outside current owner scope', function (): void {
    $fixture = makeOwnerScopedSerialServiceFixture();
    $sku = $fixture['sku'];
    $locationB = $fixture['locationB'];
    $service = $fixture['service'];

    expect(fn () => $service->register(
        $sku,
        'SN-X',
        $locationB->id
    ))->toThrow(InvalidArgumentException::class, 'Invalid location for current owner');
});

it('prevents transferring serials to locations outside current owner scope', function (): void {
    $fixture = makeOwnerScopedSerialServiceFixture();
    $sku = $fixture['sku'];
    $locationA = $fixture['locationA'];
    $locationB = $fixture['locationB'];
    $service = $fixture['service'];

    $serial = InventorySerial::factory()->create([
        'inventoryable_type' => $sku->getMorphClass(),
        'inventoryable_id' => $sku->getKey(),
        'serial_number' => 'SN-T',
        'location_id' => $locationA->id,
        'status' => SerialStatus::Available->value,
        'condition' => SerialCondition::New->value,
    ]);

    expect(fn () => $service->transfer($serial, $locationB->id))
        ->toThrow(InvalidArgumentException::class, 'Invalid location for current owner');
});

it('allows finding shipped serials by serial number using history scope', function (): void {
    $fixture = makeOwnerScopedSerialServiceFixture();
    $sku = $fixture['sku'];
    $locationA = $fixture['locationA'];
    $service = $fixture['service'];

    $serial = $service->register(
        $sku,
        'SN-SHIPPED',
        $locationA->id,
        null,
        SerialCondition::New
    );

    $serial->update(['status' => SerialStatus::Sold->value]);

    $service->ship($serial->fresh(), 'TRACK-1');

    $found = $service->findBySerialNumber('SN-SHIPPED');

    expect($found)->not->toBeNull();
});
