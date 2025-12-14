<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Enums\AlertStatus;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\StockThresholdService;


class StockThresholdServiceTest extends InventoryTestCase
{
    protected StockThresholdService $service;

    protected InventoryItem $item;

    protected InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(StockThresholdService::class);
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create([
            'name' => 'Test Location',
            'code' => 'TEST',
        ]);
    }

    public function test_calculate_status_returns_out_of_stock_when_available_is_zero(): void
    {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
        ]);

        $status = $this->service->calculateStatus($level);

        expect($status)->toBe(AlertStatus::OutOfStock);
    }

    public function test_calculate_status_returns_safety_breached_when_below_safety_stock(): void
    {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'safety_stock' => 10,
        ]);

        $status = $this->service->calculateStatus($level);

        expect($status)->toBe(AlertStatus::SafetyBreached);
    }

    public function test_calculate_status_returns_low_stock_when_below_reorder_point(): void
    {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'reorder_point' => 10,
        ]);

        $status = $this->service->calculateStatus($level);

        expect($status)->toBe(AlertStatus::LowStock);
    }

    public function test_calculate_status_returns_over_stock_when_above_max_stock(): void
    {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
            'max_stock' => 50,
        ]);

        $status = $this->service->calculateStatus($level);

        expect($status)->toBe(AlertStatus::OverStock);
    }

    public function test_calculate_status_returns_none_when_normal(): void
    {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'reorder_point' => 10,
            'max_stock' => 100,
        ]);

        $status = $this->service->calculateStatus($level);

        expect($status)->toBe(AlertStatus::None);
    }

    public function test_needs_attention_returns_true_for_critical_statuses(): void
    {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
        ]);

        $needsAttention = $this->service->needsAttention($level);

        expect($needsAttention)->toBeTrue();
    }

    public function test_needs_attention_returns_false_for_normal_and_over_stock(): void
    {
        $location2 = InventoryLocation::factory()->create([
            'name' => 'Test Location 2',
            'code' => 'TEST2',
        ]);
        $location3 = InventoryLocation::factory()->create([
            'name' => 'Test Location 3',
            'code' => 'TEST3',
        ]);
        $normalLevel = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location2->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);

        $overStockLevel = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location3->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
            'max_stock' => 50,
        ]);

        expect($this->service->needsAttention($normalLevel))->toBeFalse();
        expect($this->service->needsAttention($overStockLevel))->toBeFalse();
    }
}