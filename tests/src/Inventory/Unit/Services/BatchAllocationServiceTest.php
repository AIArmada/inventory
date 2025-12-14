<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\BatchStatus;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\BatchAllocationService;
use AIArmada\Inventory\Services\BatchService;

beforeEach(function (): void {
    $this->location = InventoryLocation::factory()->create();
    $this->product = InventoryItem::create(['name' => 'Test Product']);
    $this->batchService = new BatchService();
    $this->service = new BatchAllocationService($this->batchService);
});

describe('allocateFefo', function (): void {
    it('allocates from earliest expiring batches first', function (): void {
        $batch1 = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'status' => BatchStatus::Active,
            'expires_at' => now()->addDays(30),
        ]);

        $batch2 = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'status' => BatchStatus::Active,
            'expires_at' => now()->addDays(10),
        ]);

        $allocations = $this->service->allocateFefo($this->product, 30);

        expect($allocations)->toHaveCount(1);
        expect($allocations[0]['batch']->id)->toBe($batch2->id);
        expect($allocations[0]['quantity'])->toBe(30);
    });

    it('allocates from multiple batches when needed', function (): void {
        $batch1 = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 30,
            'quantity_reserved' => 0,
            'status' => BatchStatus::Active,
            'expires_at' => now()->addDays(5),
        ]);

        $batch2 = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 30,
            'quantity_reserved' => 0,
            'status' => BatchStatus::Active,
            'expires_at' => now()->addDays(10),
        ]);

        $allocations = $this->service->allocateFefo($this->product, 50);

        expect($allocations)->toHaveCount(2);
        expect($allocations[0]['batch']->id)->toBe($batch1->id);
        expect($allocations[0]['quantity'])->toBe(30);
        expect($allocations[1]['batch']->id)->toBe($batch2->id);
        expect($allocations[1]['quantity'])->toBe(20);
    });

    it('throws exception for zero or negative quantity', function (): void {
        $this->service->allocateFefo($this->product, 0);
    })->throws(InvalidArgumentException::class);

    it('throws exception when insufficient inventory', function (): void {
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'status' => BatchStatus::Active,
        ]);

        $this->service->allocateFefo($this->product, 100);
    })->throws(InvalidArgumentException::class);

    it('filters by location when provided', function (): void {
        $otherLocation = InventoryLocation::factory()->create();

        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $otherLocation->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
            'status' => BatchStatus::Active,
        ]);

        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'status' => BatchStatus::Active,
        ]);

        $allocations = $this->service->allocateFefo($this->product, 30, $this->location->id);

        expect($allocations)->toHaveCount(1);
        expect($allocations[0]['batch']->id)->toBe($batch->id);
    });
});

describe('allocateFifo', function (): void {
    it('allocates from oldest received batches first', function (): void {
        $batch1 = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'status' => BatchStatus::Active,
            'received_at' => now()->subDays(10),
        ]);

        $batch2 = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'status' => BatchStatus::Active,
            'received_at' => now()->subDays(5),
        ]);

        $allocations = $this->service->allocateFifo($this->product, 30);

        expect($allocations)->toHaveCount(1);
        expect($allocations[0]['batch']->id)->toBe($batch1->id);
        expect($allocations[0]['quantity'])->toBe(30);
    });

    it('throws exception for zero quantity', function (): void {
        $this->service->allocateFifo($this->product, -1);
    })->throws(InvalidArgumentException::class);
});

describe('reserveBatches', function (): void {
    it('increments reserved quantity on batches', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'status' => BatchStatus::Active,
        ]);

        $allocations = [['batch' => $batch, 'quantity' => 20]];

        $this->service->reserveBatches($allocations);

        expect($batch->fresh()->quantity_reserved)->toBe(20);
    });

    it('reserves across multiple batches in transaction', function (): void {
        $batch1 = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 30,
            'quantity_reserved' => 5,
            'status' => BatchStatus::Active,
        ]);

        $batch2 = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 30,
            'quantity_reserved' => 0,
            'status' => BatchStatus::Active,
        ]);

        $allocations = [
            ['batch' => $batch1, 'quantity' => 10],
            ['batch' => $batch2, 'quantity' => 15],
        ];

        $this->service->reserveBatches($allocations);

        expect($batch1->fresh()->quantity_reserved)->toBe(15);
        expect($batch2->fresh()->quantity_reserved)->toBe(15);
    });
});

describe('releaseBatches', function (): void {
    it('decrements reserved quantity on batches', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 20,
            'status' => BatchStatus::Active,
        ]);

        $allocations = [['batch' => $batch, 'quantity' => 10]];

        $this->service->releaseBatches($allocations);

        expect($batch->fresh()->quantity_reserved)->toBe(10);
    });
});

describe('commitBatches', function (): void {
    it('decrements both reserved and on_hand quantities', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 20,
            'status' => BatchStatus::Active,
        ]);

        $allocations = [['batch' => $batch, 'quantity' => 15]];

        $this->service->commitBatches($allocations);

        $batch->refresh();
        expect($batch->quantity_reserved)->toBe(5);
        expect($batch->quantity_on_hand)->toBe(35);
    });
});

describe('checkAvailability', function (): void {
    it('returns true when sufficient inventory exists', function (): void {
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 20,
            'status' => BatchStatus::Active,
        ]);

        expect($this->service->checkAvailability($this->product, 50))->toBeTrue();
    });

    it('returns false when insufficient inventory', function (): void {
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 30,
            'quantity_reserved' => 20,
            'status' => BatchStatus::Active,
        ]);

        expect($this->service->checkAvailability($this->product, 50))->toBeFalse();
    });
});

describe('getFefoAllocationPlan', function (): void {
    it('returns allocation plan with availability info', function (): void {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'batch_number' => 'BATCH-001',
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
            'status' => BatchStatus::Active,
            'expires_at' => now()->addDays(30),
        ]);

        $plan = $this->service->getFefoAllocationPlan($this->product, 50);

        expect($plan['available'])->toBeTrue();
        expect($plan['total_available'])->toBe(100);
        expect($plan['requested'])->toBe(50);
        expect($plan['allocations'])->toHaveCount(1);
        expect($plan['allocations'][0]['batch_id'])->toBe($batch->id);
        expect($plan['allocations'][0]['batch_number'])->toBe('BATCH-001');
        expect($plan['allocations'][0]['quantity'])->toBe(50);
    });

    it('indicates unavailable when insufficient stock', function (): void {
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 30,
            'quantity_reserved' => 0,
            'status' => BatchStatus::Active,
        ]);

        $plan = $this->service->getFefoAllocationPlan($this->product, 50);

        expect($plan['available'])->toBeFalse();
        expect($plan['total_available'])->toBe(30);
        expect($plan['requested'])->toBe(50);
    });
});
