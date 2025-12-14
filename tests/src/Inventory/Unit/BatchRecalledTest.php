<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Events\BatchRecalled;
use AIArmada\Inventory\Models\InventoryBatch;
use Illuminate\Database\Eloquent\Collection;

class BatchRecalledTest extends InventoryTestCase
{
    protected InventoryItem $item;

    protected Collection $batches;

    protected function setUp(): void
    {
        parent::setUp();

        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-003',
            'quantity_on_hand' => 25,
        ]);
        $this->batches = new Collection([$batch]);
    }

    public function test_event_stores_properties_correctly(): void
    {
        $event = new BatchRecalled($this->batches, 'Safety recall', $this->item);

        expect($event->batches)->toBe($this->batches);
        expect($event->reason)->toBe('Safety recall');
        expect($event->inventoryable)->toBe($this->item);
    }

    public function test_get_total_quantity_affected_returns_correct_value(): void
    {
        $event = new BatchRecalled($this->batches, 'Safety recall', $this->item);

        expect($event->getTotalQuantityAffected())->toBe(25);
    }

    public function test_get_batch_numbers_returns_correct_value(): void
    {
        $event = new BatchRecalled($this->batches, 'Safety recall', $this->item);

        expect($event->getBatchNumbers())->toBe(['BATCH-003']);
    }
}
