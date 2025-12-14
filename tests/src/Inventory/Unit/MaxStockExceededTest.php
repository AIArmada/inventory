<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Events\MaxStockExceeded;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;

class MaxStockExceededTest extends InventoryTestCase
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
            'quantity_on_hand' => 150,
            'quantity_reserved' => 0,
            'max_stock' => 100,
        ]);
    }

    public function test_event_stores_properties_correctly(): void
    {
        $event = new MaxStockExceeded($this->item, $this->level);

        expect($event->inventoryable)->toBe($this->item);
        expect($event->level)->toBe($this->level);
    }

    public function test_get_on_hand_returns_correct_quantity(): void
    {
        $event = new MaxStockExceeded($this->item, $this->level);

        expect($event->getOnHand())->toBe(150);
    }

    public function test_get_max_stock_returns_correct_value(): void
    {
        $event = new MaxStockExceeded($this->item, $this->level);

        expect($event->getMaxStock())->toBe(100);
    }

    public function test_get_overage_calculates_correctly(): void
    {
        $event = new MaxStockExceeded($this->item, $this->level);

        expect($event->getOverage())->toBe(50); // 150 - 100 = 50
    }

    public function test_get_max_stock_returns_zero_when_null(): void
    {
        $anotherLocation = InventoryLocation::factory()->create([
            'name' => 'Another Location',
            'code' => 'TEST2',
        ]);
        $levelWithoutMaxStock = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $anotherLocation->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'max_stock' => null,
        ]);

        $event = new MaxStockExceeded($this->item, $levelWithoutMaxStock);

        expect($event->getMaxStock())->toBe(0);
        expect($event->getOverage())->toBe(50); // 50 - 0 = 50
    }
}
