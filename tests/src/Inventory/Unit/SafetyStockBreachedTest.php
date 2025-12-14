<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Enums\AlertStatus;
use AIArmada\Inventory\Events\SafetyStockBreached;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;

class SafetyStockBreachedTest extends InventoryTestCase
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
            'quantity_on_hand' => 3,
            'quantity_reserved' => 0,
            'safety_stock' => 10,
        ]);
    }

    public function test_event_stores_properties_correctly(): void
    {
        $event = new SafetyStockBreached($this->item, $this->level, AlertStatus::LowStock);

        expect($event->inventoryable)->toBe($this->item);
        expect($event->level)->toBe($this->level);
        expect($event->previousStatus)->toBe(AlertStatus::LowStock);
    }

    public function test_get_available_returns_correct_quantity(): void
    {
        $event = new SafetyStockBreached($this->item, $this->level, AlertStatus::LowStock);

        expect($event->getAvailable())->toBe(3);
    }

    public function test_get_safety_stock_returns_correct_value(): void
    {
        $event = new SafetyStockBreached($this->item, $this->level, AlertStatus::LowStock);

        expect($event->getSafetyStock())->toBe(10);
    }

    public function test_get_deficit_calculates_correctly(): void
    {
        $event = new SafetyStockBreached($this->item, $this->level, AlertStatus::LowStock);

        expect($event->getDeficit())->toBe(7); // 10 - 3 = 7
    }

    public function test_get_safety_stock_returns_zero_when_null(): void
    {
        $anotherLocation = InventoryLocation::factory()->create([
            'name' => 'Another Location',
            'code' => 'TEST2',
        ]);
        $levelWithoutSafetyStock = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $anotherLocation->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'safety_stock' => null,
        ]);

        $event = new SafetyStockBreached($this->item, $levelWithoutSafetyStock, AlertStatus::LowStock);

        expect($event->getSafetyStock())->toBe(0);
        expect($event->getDeficit())->toBe(0); // 0 - 5 = 0 (max(0, ...))
    }
}