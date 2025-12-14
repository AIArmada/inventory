<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Enums\SerialCondition;
use AIArmada\Inventory\Enums\SerialStatus;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventorySerial;
use AIArmada\Inventory\Services\SerialService;

class SerialServiceTest extends InventoryTestCase
{
    protected SerialService $service;

    protected InventoryItem $item;

    protected InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SerialService;
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create(['is_active' => true]);
    }

    public function test_register_serial(): void
    {
        $serial = $this->service->register(
            $this->item,
            'SN-001',
            $this->location->id,
            null,
            SerialCondition::New,
            1000,
            now()->addYear(),
            'user-123'
        );

        expect($serial)->toBeInstanceOf(InventorySerial::class);
        expect($serial->serial_number)->toBe('SN-001');
        expect($serial->status)->toBe(SerialStatus::Available->value);
        expect($serial->condition)->toBe(SerialCondition::New->value);
        expect($serial->history)->toHaveCount(1);
    }

    public function test_find_by_serial_number(): void
    {
        InventorySerial::factory()->create([
            'serial_number' => 'SN-FIND',
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
        ]);

        $found = $this->service->findBySerialNumber('SN-FIND');

        expect($found)->not->toBeNull();
        expect($found->serial_number)->toBe('SN-FIND');
    }

    public function test_find_by_serial_number_returns_null_when_not_found(): void
    {
        $found = $this->service->findBySerialNumber('NONEXISTENT');

        expect($found)->toBeNull();
    }

    public function test_get_serials_for_model(): void
    {
        InventorySerial::factory()->count(3)->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
        ]);

        $serials = $this->service->getSerialsForModel($this->item);

        expect($serials)->toHaveCount(3);
    }

    public function test_get_available_serials(): void
    {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => SerialStatus::Available->value,
            'condition' => SerialCondition::New->value,
        ]);
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => SerialStatus::Sold->value,
        ]);

        $available = $this->service->getAvailableSerials($this->item, $this->location->id);

        expect($available)->toHaveCount(1);
    }

    public function test_transfer_serial(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
        ]);
        $newLocation = InventoryLocation::factory()->create();

        $transferred = $this->service->transfer($serial, $newLocation->id, 'user-123', 'Moving to warehouse B');

        expect($transferred->location_id)->toBe($newLocation->id);
    }

    public function test_reserve_serial(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::Available->value,
        ]);

        $reserved = $this->service->reserve($serial, 'order-123', 'user-123');

        expect($reserved->status)->toBe(SerialStatus::Reserved->value);
    }

    public function test_reserve_serial_throws_for_invalid_status(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::Sold->value,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->reserve($serial, 'order-123');
    }

    public function test_release_serial(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::Reserved->value,
        ]);

        $released = $this->service->release($serial, 'user-123');

        expect($released->status)->toBe(SerialStatus::Available->value);
    }

    public function test_sell_serial(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::Reserved->value,
        ]);

        $sold = $this->service->sell($serial, 'order-123', 'customer-456', 'user-123');

        expect($sold->status)->toBe(SerialStatus::Sold->value);
        expect($sold->order_id)->toBe('order-123');
        expect($sold->customer_id)->toBe('customer-456');
    }

    public function test_ship_serial(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => SerialStatus::Sold->value,
        ]);

        $shipped = $this->service->ship($serial, 'TRACK-123', 'user-123');

        expect($shipped->status)->toBe(SerialStatus::Shipped->value);
        expect($shipped->location_id)->toBeNull();
    }

    public function test_process_return(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::Shipped->value,
        ]);

        $returned = $this->service->processReturn(
            $serial,
            $this->location->id,
            SerialCondition::Used,
            'Customer returned',
            'user-123'
        );

        expect($returned->status)->toBe(SerialStatus::Returned->value);
        expect($returned->condition)->toBe(SerialCondition::Used->value);
        expect($returned->location_id)->toBe($this->location->id);
    }

    public function test_start_repair(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::Returned->value,
        ]);

        $inRepair = $this->service->startRepair($serial, 'Screen damage', 'user-123');

        expect($inRepair->status)->toBe(SerialStatus::InRepair->value);
    }

    public function test_complete_repair(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::InRepair->value,
            'condition' => SerialCondition::Damaged->value,
        ]);

        $repaired = $this->service->completeRepair($serial, SerialCondition::Refurbished, 'Repaired successfully', 'user-123');

        expect($repaired->status)->toBe(SerialStatus::Available->value);
        expect($repaired->condition)->toBe(SerialCondition::Refurbished->value);
    }

    public function test_dispose(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => SerialStatus::Returned->value,
            'condition' => SerialCondition::Damaged->value,
        ]);

        $disposed = $this->service->dispose($serial, 'Beyond repair', 'user-123');

        expect($disposed->status)->toBe(SerialStatus::Disposed->value);
        expect($disposed->location_id)->toBeNull();
    }

    public function test_update_warranty(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'warranty_expires_at' => now()->addYear(),
        ]);

        $newExpiry = now()->addYears(2);
        $updated = $this->service->updateWarranty($serial, $newExpiry, 'Extended warranty', 'user-123');

        expect($updated->warranty_expires_at->toDateString())->toBe($newExpiry->toDateString());
    }

    public function test_get_history(): void
    {
        $serial = $this->service->register($this->item, 'SN-HISTORY', $this->location->id);
        $this->service->reserve($serial->fresh(), 'order-123');
        $this->service->release($serial->fresh());

        $history = $this->service->getHistory($serial, 10);

        expect($history)->toHaveCount(3);
    }

    public function test_get_history_with_limit(): void
    {
        $serial = $this->service->register($this->item, 'SN-LIMIT', $this->location->id);
        $this->service->reserve($serial->fresh(), 'order-123');
        $this->service->release($serial->fresh());
        $this->service->reserve($serial->fresh(), 'order-456');

        $history = $this->service->getHistory($serial, 2);

        expect($history)->toHaveCount(2);
    }
}
