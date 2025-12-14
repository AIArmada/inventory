<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\SerialCondition;
use AIArmada\Inventory\Enums\SerialStatus;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventorySerial;
use AIArmada\Inventory\Services\SerialLookupService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(function (): void {
    $this->item = InventoryItem::create(['name' => 'Test Product']);
    $this->location = InventoryLocation::factory()->create();
    $this->batch = InventoryBatch::factory()->forInventoryable(
        InventoryItem::class,
        $this->item->id,
    )->create([
        'location_id' => $this->location->id,
    ]);
    $this->service = new SerialLookupService();
});

describe('SerialLookupService', function (): void {
    it('finds serial by exact serial number', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'serial_number' => 'SN-12345',
        ]);

        $found = $this->service->findBySerialNumber('SN-12345');

        expect($found)->not->toBeNull();
        expect($found->id)->toBe($serial->id);
    });

    it('returns null when serial not found', function (): void {
        $found = $this->service->findBySerialNumber('NONEXISTENT');

        expect($found)->toBeNull();
    });

    it('finds serial by serial number or fails', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'serial_number' => 'SN-12345',
        ]);

        $serial = $this->service->findBySerialNumberOrFail('SN-12345');

        expect($serial->serial_number)->toBe('SN-12345');
    });

    it('throws exception when serial not found', function (): void {
        $this->service->findBySerialNumberOrFail('NONEXISTENT');
    })->throws(ModelNotFoundException::class);

    it('searches serials by partial serial number', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'serial_number' => 'ABC-12345',
        ]);

        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'serial_number' => 'XYZ-12345',
        ]);

        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'serial_number' => 'ABC-99999',
        ]);

        $results = $this->service->searchBySerialNumber('12345');

        expect($results)->toHaveCount(2);
    });

    it('finds serial by order id', function (): void {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'order_id' => 'ORDER-123',
        ]);

        $found = $this->service->findByOrderId('ORDER-123');

        expect($found)->not->toBeNull();
        expect($found->id)->toBe($serial->id);
    });

    it('gets all serials by order id', function (): void {
        InventorySerial::factory()->count(3)->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'order_id' => 'ORDER-456',
        ]);

        $serials = $this->service->getAllByOrderId('ORDER-456');

        expect($serials)->toHaveCount(3);
    });

    it('gets serials by customer id', function (): void {
        InventorySerial::factory()->count(2)->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'customer_id' => 'CUST-789',
            'sold_at' => CarbonImmutable::now(),
        ]);

        $serials = $this->service->getByCustomerId('CUST-789');

        expect($serials)->toHaveCount(2);
    });

    it('gets serials for model', function (): void {
        InventorySerial::factory()->count(2)->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
        ]);

        $serials = $this->service->getForModel($this->item);

        expect($serials)->toHaveCount(2);
    });

    it('gets serials at location', function (): void {
        InventorySerial::factory()->count(3)->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
        ]);

        $serials = $this->service->getAtLocation($this->location->id);

        expect($serials)->toHaveCount(3);
    });

    it('gets serials by batch', function (): void {
        InventorySerial::factory()->count(2)->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
        ]);

        $serials = $this->service->getByBatch($this->batch->id);

        expect($serials)->toHaveCount(2);
    });

    it('gets serials by status', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'status' => SerialStatus::Available,
        ]);

        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'status' => SerialStatus::Sold,
        ]);

        $available = $this->service->getByStatus(SerialStatus::Available);

        expect($available)->toHaveCount(1);
    });

    it('gets serials by condition', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'condition' => SerialCondition::New,
        ]);

        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'condition' => SerialCondition::Damaged,
        ]);

        $newCondition = $this->service->getByCondition(SerialCondition::New);

        expect($newCondition)->toHaveCount(1);
    });

    it('gets available serials for sale', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'status' => SerialStatus::Available,
            'condition' => SerialCondition::New,
        ]);

        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'status' => SerialStatus::Sold,
        ]);

        $available = $this->service->getAvailableForSale($this->item);

        expect($available)->toHaveCount(1);
    });

    it('gets available serials filtered by location', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'status' => SerialStatus::Available,
            'condition' => SerialCondition::New,
        ]);

        $location2 = InventoryLocation::factory()->create();
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location2->id,
            'batch_id' => $this->batch->id,
            'status' => SerialStatus::Available,
            'condition' => SerialCondition::New,
        ]);

        $available = $this->service->getAvailableForSale($this->item, $this->location->id);

        expect($available)->toHaveCount(1);
    });

    it('gets serials with expiring warranty', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'warranty_expires_at' => CarbonImmutable::now()->addDays(15),
        ]);

        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'warranty_expires_at' => CarbonImmutable::now()->addDays(60),
        ]);

        $expiring = $this->service->getExpiringWarranty(30);

        expect($expiring)->toHaveCount(1);
    });

    it('gets customer warranty items', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'customer_id' => 'CUST-001',
            'status' => SerialStatus::Sold,
            'warranty_expires_at' => CarbonImmutable::now()->addYear(),
        ]);

        $items = $this->service->getCustomerWarrantyItems('CUST-001');

        expect($items)->toHaveCount(1);
    });

    it('searches with multiple criteria', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'serial_number' => 'TEST-001',
            'status' => SerialStatus::Available,
            'condition' => SerialCondition::New,
        ]);

        $results = $this->service->search([
            'serial_number' => 'TEST',
            'status' => SerialStatus::Available,
        ]);

        expect($results->total())->toBe(1);
    });

    it('counts serials by status for model', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'status' => SerialStatus::Available,
        ]);

        InventorySerial::factory()->count(2)->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'status' => SerialStatus::Sold,
        ]);

        $counts = $this->service->countByStatus($this->item);

        expect($counts[SerialStatus::Available->value])->toBe(1);
        expect($counts[SerialStatus::Sold->value])->toBe(2);
    });

    it('counts serials by condition for model', function (): void {
        InventorySerial::factory()->count(2)->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'condition' => SerialCondition::New,
        ]);

        $counts = $this->service->countByCondition($this->item);

        expect($counts[SerialCondition::New->value])->toBe(2);
    });

    it('gets total value at location', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'unit_cost_minor' => 1000,
        ]);

        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'unit_cost_minor' => 500,
        ]);

        $total = $this->service->getTotalValue($this->location->id);

        expect($total)->toBe(1500);
    });

    it('gets total value for model', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'unit_cost_minor' => 2000,
            'status' => SerialStatus::Available,
        ]);

        $total = $this->service->getTotalValueForModel($this->item, SerialStatus::Available->value);

        expect($total)->toBe(2000);
    });

    it('checks if serial number exists', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'serial_number' => 'EXISTING-001',
        ]);

        expect($this->service->serialNumberExists('EXISTING-001'))->toBeTrue();
        expect($this->service->serialNumberExists('NONEXISTENT'))->toBeFalse();
    });

    it('validates serial numbers availability', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'serial_number' => 'USED-001',
        ]);

        $result = $this->service->validateSerialNumbers(['USED-001', 'NEW-001', 'NEW-002']);

        expect($result['USED-001'])->toBeFalse(); // Not available
        expect($result['NEW-001'])->toBeTrue(); // Available
        expect($result['NEW-002'])->toBeTrue(); // Available
    });

    it('searches with array of statuses', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'status' => SerialStatus::Available,
        ]);

        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'status' => SerialStatus::Reserved,
        ]);

        $results = $this->service->search([
            'status' => [SerialStatus::Available, SerialStatus::Reserved],
        ]);

        expect($results->total())->toBe(2);
    });

    it('searches with date ranges', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'received_at' => CarbonImmutable::now()->subDays(5),
        ]);

        $results = $this->service->search([
            'received_from' => CarbonImmutable::now()->subWeek(),
            'received_to' => CarbonImmutable::now(),
        ]);

        expect($results->total())->toBe(1);
    });

    it('searches with cost range', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'unit_cost_minor' => 1500,
        ]);

        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'unit_cost_minor' => 500,
        ]);

        $results = $this->service->search([
            'min_cost' => 1000,
        ]);

        expect($results->total())->toBe(1);
    });

    it('searches with warranty filters', function (): void {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'batch_id' => $this->batch->id,
            'warranty_expires_at' => CarbonImmutable::now()->addYear(),
        ]);

        $results = $this->service->search([
            'has_warranty' => true,
            'under_warranty' => true,
        ]);

        expect($results->total())->toBe(1);
    });
});
