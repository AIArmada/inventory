<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Events\LowInventoryDetected;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;

class LowInventoryDetectedTest extends InventoryTestCase
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
            'quantity_on_hand' => 5,
            'quantity_reserved' => 2,
            'reorder_point' => 10,
        ]);
    }

    public function test_event_stores_properties_correctly(): void
    {
        $event = new LowInventoryDetected($this->item, $this->level);

        expect($event->inventoryable)->toBe($this->item);
        expect($event->level)->toBe($this->level);
    }

    public function test_get_available_returns_correct_quantity(): void
    {
        $event = new LowInventoryDetected($this->item, $this->level);

        expect($event->getAvailable())->toBe(3); // 5 - 2 = 3
    }

    public function test_get_reorder_point_returns_correct_value(): void
    {
        $event = new LowInventoryDetected($this->item, $this->level);

        expect($event->getReorderPoint())->toBe(10);
    }

    public function test_get_reorder_point_returns_default_when_null(): void
    {
        $anotherLocation = InventoryLocation::factory()->create([
            'name' => 'Another Location',
            'code' => 'TEST2',
        ]);
        $levelWithoutReorderPoint = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $anotherLocation->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 2,
            'reorder_point' => null,
        ]);

        $event = new LowInventoryDetected($this->item, $levelWithoutReorderPoint);

        expect($event->getReorderPoint())->toBe(10); // default value
    }
}