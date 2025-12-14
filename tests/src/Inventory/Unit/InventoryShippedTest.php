<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Events\InventoryShipped;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;

class InventoryShippedTest extends InventoryTestCase
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
            'quantity_on_hand' => 5,
        ]);
        $this->movement = InventoryMovement::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'from_location_id' => $this->location->id,
            'to_location_id' => null,
            'type' => 'shipment',
            'quantity' => 5,
        ]);
    }

    public function test_event_stores_properties_correctly(): void
    {
        $event = new InventoryShipped($this->item, $this->level, $this->movement);

        expect($event->inventoryable)->toBe($this->item);
        expect($event->level)->toBe($this->level);
        expect($event->movement)->toBe($this->movement);
    }
}