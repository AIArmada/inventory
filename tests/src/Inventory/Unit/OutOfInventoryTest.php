<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Events\OutOfInventory;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;

class OutOfInventoryTest extends InventoryTestCase
{
    protected InventoryItem $item;

    protected InventoryLocation $location;

    protected InventoryLevel $level;

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
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
        ]);
    }

    public function test_event_stores_properties_correctly(): void
    {
        $event = new OutOfInventory($this->item, $this->level);

        expect($event->inventoryable)->toBe($this->item);
        expect($event->level)->toBe($this->level);
    }

    public function test_get_location_id_returns_correct_value(): void
    {
        $event = new OutOfInventory($this->item, $this->level);

        expect($event->getLocationId())->toBe($this->location->id);
    }
}
