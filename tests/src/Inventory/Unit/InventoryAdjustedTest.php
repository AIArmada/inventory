<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Events\InventoryAdjusted;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;

class InventoryAdjustedTest extends InventoryTestCase
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
            'type' => 'adjustment',
            'quantity' => 5,
        ]);
    }

    public function test_event_stores_properties_correctly(): void
    {
        $event = new InventoryAdjusted(
            $this->item,
            $this->level,
            $this->movement,
            10,
            15
        );

        expect($event->inventoryable)->toBe($this->item);
        expect($event->level)->toBe($this->level);
        expect($event->movement)->toBe($this->movement);
        expect($event->oldQuantity)->toBe(10);
        expect($event->newQuantity)->toBe(15);
    }

    public function test_get_difference_calculates_correctly(): void
    {
        $event = new InventoryAdjusted(
            $this->item,
            $this->level,
            $this->movement,
            10,
            15
        );

        expect($event->getDifference())->toBe(5);
    }

    public function test_get_difference_handles_negative_adjustments(): void
    {
        $event = new InventoryAdjusted(
            $this->item,
            $this->level,
            $this->movement,
            15,
            10
        );

        expect($event->getDifference())->toBe(-5);
    }
}
