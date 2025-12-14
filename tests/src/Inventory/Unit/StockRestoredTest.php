<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Enums\AlertStatus;
use AIArmada\Inventory\Events\StockRestored;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;

class StockRestoredTest extends InventoryTestCase
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
            'quantity_on_hand' => 25,
            'quantity_reserved' => 0,
            'reorder_point' => 10,
        ]);
    }

    public function test_event_stores_properties_correctly(): void
    {
        $event = new StockRestored($this->item, $this->level, AlertStatus::LowStock);

        expect($event->inventoryable)->toBe($this->item);
        expect($event->level)->toBe($this->level);
        expect($event->previousStatus)->toBe(AlertStatus::LowStock);
    }

    public function test_get_available_returns_correct_quantity(): void
    {
        $event = new StockRestored($this->item, $this->level, AlertStatus::LowStock);

        expect($event->getAvailable())->toBe(25);
    }

    public function test_get_reorder_point_returns_correct_value(): void
    {
        $event = new StockRestored($this->item, $this->level, AlertStatus::LowStock);

        expect($event->getReorderPoint())->toBe(10);
    }

    public function test_is_above_reorder_point_returns_true_when_above_threshold(): void
    {
        $event = new StockRestored($this->item, $this->level, AlertStatus::LowStock);

        expect($event->isAboveReorderPoint())->toBeTrue();
    }

    public function test_is_above_reorder_point_returns_false_when_at_or_below_threshold(): void
    {
        $anotherLocation = InventoryLocation::factory()->create([
            'name' => 'Another Location',
            'code' => 'TEST2',
        ]);
        $levelAtReorderPoint = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $anotherLocation->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'reorder_point' => 10,
        ]);

        $event = new StockRestored($this->item, $levelAtReorderPoint, AlertStatus::LowStock);

        expect($event->isAboveReorderPoint())->toBeFalse();
    }
}
