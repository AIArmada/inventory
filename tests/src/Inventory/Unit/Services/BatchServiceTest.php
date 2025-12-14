<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Enums\BatchStatus;
use AIArmada\Inventory\Events\BatchCreated;
use AIArmada\Inventory\Events\BatchExpired;
use AIArmada\Inventory\Events\BatchRecalled;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\BatchService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Event;

class BatchServiceTest extends InventoryTestCase
{
    protected BatchService $service;

    protected InventoryItem $item;

    protected InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new BatchService;
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create();
    }

    public function test_create_batch(): void
    {
        Event::fake();

        $batch = $this->service->createBatch(
            $this->item,
            'BATCH-001',
            $this->location->id,
            100,
            now()->addMonths(6),
            now()->subMonth()
        );

        expect($batch)->toBeInstanceOf(InventoryBatch::class);
        expect($batch->batch_number)->toBe('BATCH-001');
        expect($batch->quantity_on_hand)->toBe(100);
        expect($batch->status)->toBe(BatchStatus::Active->value);

        Event::assertDispatched(BatchCreated::class);
    }

    public function test_create_batch_throws_for_zero_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->createBatch(
            $this->item,
            'BATCH-001',
            $this->location->id,
            0
        );
    }

    public function test_create_batch_throws_for_negative_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->createBatch(
            $this->item,
            'BATCH-001',
            $this->location->id,
            -10
        );
    }

    public function test_find_by_batch_number(): void
    {
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-FIND',
            'location_id' => $this->location->id,
        ]);

        $found = $this->service->findByBatchNumber('BATCH-FIND');

        expect($found)->not->toBeNull();
        expect($found->batch_number)->toBe('BATCH-FIND');
    }

    public function test_find_by_batch_number_returns_null_when_not_found(): void
    {
        $found = $this->service->findByBatchNumber('NONEXISTENT');

        expect($found)->toBeNull();
    }

    public function test_get_batches_for_model(): void
    {
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-1',
            'location_id' => $this->location->id,
        ]);
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-2',
            'location_id' => $this->location->id,
        ]);

        $batches = $this->service->getBatchesForModel($this->item);

        expect($batches)->toHaveCount(2);
    }

    public function test_get_allocatable_batches(): void
    {
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-ACTIVE',
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
        ]);
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-QUARANTINED',
            'location_id' => $this->location->id,
            'status' => BatchStatus::Quarantined->value,
            'quantity_on_hand' => 50,
        ]);

        $allocatable = $this->service->getAllocatableBatches($this->item);

        expect($allocatable)->toHaveCount(1);
    }

    public function test_get_total_available(): void
    {
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-T1',
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 20,
            'expires_at' => null,
        ]);
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-T2',
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 10,
            'expires_at' => null,
        ]);

        $total = $this->service->getTotalAvailable($this->item);

        // (100-20) + (50-10) = 80 + 40 = 120
        expect($total)->toBe(120);
    }

    public function test_quarantine_batch(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-Q',
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
        ]);

        $result = $this->service->quarantine($batch, 'Quality issue found');

        expect($result->status)->toBe(BatchStatus::Quarantined->value);
    }

    public function test_recall_batches(): void
    {
        Event::fake();

        $batch1 = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-R1',
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
        ]);
        $batch2 = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-R2',
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
        ]);

        $batches = new Collection([$batch1, $batch2]);
        $result = $this->service->recallBatches($batches, 'Safety recall');

        expect($result)->toHaveCount(2);
        expect($batch1->fresh()->status)->toBe(BatchStatus::Recalled->value);
        expect($batch2->fresh()->status)->toBe(BatchStatus::Recalled->value);

        Event::assertDispatched(BatchRecalled::class);
    }

    public function test_transfer_batch(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-TRANSFER',
            'location_id' => $this->location->id,
            'quantity_reserved' => 0,
        ]);

        $newLocation = InventoryLocation::factory()->create();

        $result = $this->service->transferBatch($batch, $newLocation);

        expect($result->location_id)->toBe($newLocation->id);
    }

    public function test_transfer_batch_throws_when_reserved(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-RESERVED',
            'location_id' => $this->location->id,
            'quantity_reserved' => 10,
        ]);

        $newLocation = InventoryLocation::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->service->transferBatch($batch, $newLocation);
    }

    public function test_split_batch(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-ORIGINAL',
            'location_id' => $this->location->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
        ]);

        $newBatch = $this->service->splitBatch($batch, 30, 'BATCH-SPLIT');

        expect($newBatch->batch_number)->toBe('BATCH-SPLIT');
        expect($newBatch->quantity_on_hand)->toBe(30);
        expect($batch->fresh()->quantity_on_hand)->toBe(70);
    }

    public function test_split_batch_throws_for_invalid_quantity(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-ORIGINAL',
            'location_id' => $this->location->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->splitBatch($batch, 0, 'BATCH-SPLIT');
    }

    public function test_merge_batches(): void
    {
        $batch1 = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-M1',
            'location_id' => $this->location->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);
        $batch2 = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-M2',
            'location_id' => $this->location->id,
            'quantity_on_hand' => 30,
            'quantity_reserved' => 0,
        ]);

        $batches = new Collection([$batch1, $batch2]);
        $merged = $this->service->mergeBatches($batches, 'BATCH-MERGED');

        expect($merged->batch_number)->toBe('BATCH-MERGED');
        expect($merged->quantity_on_hand)->toBe(80);
        expect(InventoryBatch::find($batch1->id))->toBeNull();
        expect(InventoryBatch::find($batch2->id))->toBeNull();
    }

    public function test_merge_batches_throws_for_single_batch(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-SINGLE',
            'location_id' => $this->location->id,
        ]);

        $batches = new Collection([$batch]);
        $this->expectException(InvalidArgumentException::class);
        $this->service->mergeBatches($batches, 'BATCH-MERGED');
    }

    public function test_get_expiring_batches(): void
    {
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-EXPIRING',
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
            'expires_at' => now()->addDays(15),
        ]);
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-SAFE',
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
            'expires_at' => now()->addDays(60),
        ]);

        $expiring = $this->service->getExpiringBatches(30);

        expect($expiring)->toHaveCount(1);
        expect($expiring->first()->batch_number)->toBe('BATCH-EXPIRING');
    }

    public function test_scope_with_status(): void
    {
        $activeBatch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-ACTIVE',
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
        ]);

        $quarantineBatch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-QUARANTINE',
            'location_id' => $this->location->id,
            'status' => BatchStatus::Quarantined->value,
        ]);

        $activeBatches = InventoryBatch::withStatus(BatchStatus::Active)->get();
        $quarantineBatches = InventoryBatch::withStatus(BatchStatus::Quarantined)->get();

        expect($activeBatches)->toHaveCount(1);
        expect($activeBatches->first()->id)->toBe($activeBatch->id);

        expect($quarantineBatches)->toHaveCount(1);
        expect($quarantineBatches->first()->id)->toBe($quarantineBatch->id);
    }

    public function test_process_expired_batches(): void
    {
        Event::fake();

        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-EXPIRED',
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
            'expires_at' => now()->subDay(),
        ]);

        $count = $this->service->processExpiredBatches();

        expect($count)->toBe(1);
        Event::assertDispatched(BatchExpired::class);
    }
}
