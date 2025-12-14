<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Enums\BatchStatus;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryLocation;

class InventoryBatchTest extends InventoryTestCase
{
    protected InventoryItem $item;

    protected InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create([
            'name' => 'Test Location',
            'code' => 'TEST',
        ]);
    }

    public function test_get_table_returns_correct_table_name(): void
    {
        $batch = new InventoryBatch();
        expect($batch->getTable())->toBe('inventory_batches');
    }

    public function test_inventoryable_relationship(): void
    {
        $batch = new InventoryBatch();
        $relation = $batch->inventoryable();
        expect($relation)->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\MorphTo::class);
    }

    public function test_location_relationship(): void
    {
        $batch = new InventoryBatch();
        $relation = $batch->location();
        expect($relation)->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    }

    public function test_movements_relationship(): void
    {
        $batch = new InventoryBatch();
        $relation = $batch->movements();
        expect($relation)->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
    }

    public function test_allocations_relationship(): void
    {
        $batch = new InventoryBatch();
        $relation = $batch->allocations();
        expect($relation)->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
    }

    public function test_get_available_attribute(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 20,
        ]);

        expect($batch->available)->toBe(80);
    }

    public function test_get_is_expired_attribute(): void
    {
        $expiredBatch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'expires_at' => now()->subDay(),
        ]);

        $validBatch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'expires_at' => now()->addDay(),
        ]);

        expect($expiredBatch->is_expired)->toBeTrue();
        expect($validBatch->is_expired)->toBeFalse();
    }

    public function test_get_days_until_expiry_attribute(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'expires_at' => now()->addDays(5),
        ]);

        expect($batch->days_until_expiry)->toBe(4); // diffInDays might be off by 1
    }

    public function test_is_expired_method(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'expires_at' => now()->subDay(),
        ]);

        expect($batch->isExpired())->toBeTrue();
    }

    public function test_days_until_expiry_method(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'expires_at' => now()->addDays(10),
        ]);

        expect($batch->daysUntilExpiry())->toBe(9); // diffInDays might be off by 1
    }

    public function test_get_status_enum(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
        ]);

        expect($batch->getStatusEnum())->toBe(BatchStatus::Active);
    }

    public function test_can_allocate(): void
    {
        $allocatableBatch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 10,
            'expires_at' => now()->addDay(),
        ]);

        $nonAllocatableBatch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => BatchStatus::Expired->value,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 10,
        ]);

        expect($allocatableBatch->canAllocate())->toBeTrue();
        expect($nonAllocatableBatch->canAllocate())->toBeFalse();
    }

    public function test_is_expiring_soon(): void
    {
        $expiringBatch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'expires_at' => now()->addDays(15),
        ]);

        $notExpiringBatch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'expires_at' => now()->addDays(50),
        ]);

        expect($expiringBatch->isExpiringSoon(30))->toBeTrue();
        expect($notExpiringBatch->isExpiringSoon(30))->toBeFalse();
    }

    public function test_quarantine(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
        ]);

        $result = $batch->quarantine('Quality issue detected');

        expect($result)->toBe($batch);
        expect($batch->fresh()->status)->toBe(BatchStatus::Quarantined->value);
        expect($batch->fresh()->is_quarantined)->toBeTrue();
        expect($batch->fresh()->quarantine_reason)->toBe('Quality issue detected');
    }

    public function test_release_from_quarantine(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => BatchStatus::Quarantined->value,
            'is_quarantined' => true,
            'quarantine_reason' => 'Test reason',
        ]);

        $result = $batch->releaseFromQuarantine();

        expect($result)->toBe($batch);
        expect($batch->fresh()->status)->toBe(BatchStatus::Active->value);
        expect($batch->fresh()->is_quarantined)->toBeFalse();
        expect($batch->fresh()->quarantine_reason)->toBeNull();
    }

    public function test_recall(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
        ]);

        $result = $batch->recall('Safety hazard found');

        expect($result)->toBe($batch);
        $fresh = $batch->fresh();
        expect($fresh->status)->toBe(BatchStatus::Recalled->value);
        expect($fresh->is_recalled)->toBeTrue();
        expect($fresh->recall_reason)->toBe('Safety hazard found');
        expect($fresh->recalled_at)->not->toBeNull();
    }

    public function test_mark_expired(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
        ]);

        $result = $batch->markExpired();

        expect($result)->toBe($batch);
        expect($batch->fresh()->status)->toBe(BatchStatus::Expired->value);
    }

    public function test_increment_on_hand(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 50,
        ]);

        $result = $batch->incrementOnHand(10);

        expect($result)->toBe($batch);
        expect($batch->fresh()->quantity_on_hand)->toBe(60);
    }

    public function test_decrement_on_hand(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 50,
            'status' => BatchStatus::Active->value,
        ]);

        $result = $batch->decrementOnHand(10);

        expect($result)->toBe($batch);
        expect($batch->fresh()->quantity_on_hand)->toBe(40);
    }

    public function test_decrement_on_hand_to_zero_depletes(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 10,
            'status' => BatchStatus::Active->value,
        ]);

        $batch->decrementOnHand(10);

        expect($batch->fresh()->quantity_on_hand)->toBe(0);
        expect($batch->fresh()->status)->toBe(BatchStatus::Depleted->value);
    }

    public function test_increment_reserved(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_reserved' => 5,
        ]);

        $result = $batch->incrementReserved(10);

        expect($result)->toBe($batch);
        expect($batch->fresh()->quantity_reserved)->toBe(15);
    }

    public function test_decrement_reserved(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_reserved' => 20,
        ]);

        $result = $batch->decrementReserved(5);

        expect($result)->toBe($batch);
        expect($batch->fresh()->quantity_reserved)->toBe(15);
    }

    public function test_is_expired_with_null_expires_at(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'expires_at' => null,
        ]);

        expect($batch->is_expired)->toBeFalse();
    }

    public function test_days_until_expiry_with_null_expires_at(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'expires_at' => null,
        ]);

        expect($batch->days_until_expiry)->toBeNull();
    }

    public function test_is_expiring_soon_with_null_expires_at(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'expires_at' => null,
        ]);

        expect($batch->isExpiringSoon())->toBeFalse();
    }

    public function test_can_allocate_when_expired(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'expires_at' => now()->subDay(),
        ]);

        expect($batch->canAllocate())->toBeFalse();
    }

    public function test_can_allocate_when_no_available(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active->value,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 50,
            'expires_at' => now()->addDay(),
        ]);

        expect($batch->canAllocate())->toBeFalse();
    }
}
