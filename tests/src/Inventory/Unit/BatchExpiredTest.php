<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Events\BatchExpired;
use AIArmada\Inventory\Models\InventoryBatch;

class BatchExpiredTest extends InventoryTestCase
{
    protected InventoryItem $item;

    protected InventoryBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_number' => 'BATCH-002',
            'quantity_on_hand' => 50,
        ]);
    }

    public function test_event_stores_properties_correctly(): void
    {
        $event = new BatchExpired($this->batch, $this->item);

        expect($event->batch)->toBe($this->batch);
        expect($event->inventoryable)->toBe($this->item);
    }

    public function test_get_batch_number_returns_correct_value(): void
    {
        $event = new BatchExpired($this->batch, $this->item);

        expect($event->getBatchNumber())->toBe('BATCH-002');
    }

    public function test_get_remaining_quantity_returns_correct_value(): void
    {
        $event = new BatchExpired($this->batch, $this->item);

        expect($event->getRemainingQuantity())->toBe(50);
    }
}