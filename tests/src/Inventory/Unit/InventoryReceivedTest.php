<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Events\InventoryReceived;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;

class InventoryReceivedTest extends InventoryTestCase
{
    protected InventoryItem $item;

    protected InventoryLocation $location;

    protected InventoryLevel $level;

    protected InventoryMovement $movement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create([
            'name' => 'Test Location',
            'code' => 'TEST',
        ]);
        $this->level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 10,
        ]);
        $this->movement = InventoryMovement::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'to_location_id' => $this->location->id,
            'type' => 'receipt',
            'quantity' => 5,
        ]);
    }

    public function test_event_stores_properties_correctly(): void
    {
        $event = new InventoryReceived($this->item, $this->level, $this->movement);

        expect($event->inventoryable)->toBe($this->item);
        expect($event->level)->toBe($this->level);
        expect($event->movement)->toBe($this->movement);
    }
}
