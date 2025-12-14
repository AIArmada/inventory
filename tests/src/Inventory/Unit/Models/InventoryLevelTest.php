<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Enums\AlertStatus;
use AIArmada\Inventory\Enums\AllocationStrategy;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;

class InventoryLevelTest extends InventoryTestCase
{
    protected InventoryItem $item;

    protected InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create([
            'name' => 'Test Location',
            'code' => 'TEST',
        ]);
    }

    public function test_can_create_inventory_level(): void
    {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 20,
        ]);

        expect($level)->toBeInstanceOf(InventoryLevel::class);
        expect($level->quantity_on_hand)->toBe(100);
        expect($level->quantity_reserved)->toBe(20);
    }

    public function test_available_attribute_calculates_correctly(): void
    {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 100,
            'quantity_reserved' => 30,
        ]);

        expect($level->available)->toBe(70);
    }

    public function test_available_never_goes_below_zero(): void
    {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 10,
            'quantity_reserved' => 50,
        ]);

        expect($level->available)->toBe(0);
    }

    public function test_get_available_quantity_method(): void
    {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 50,
            'quantity_reserved' => 10,
        ]);

        expect($level->getAvailableQuantity())->toBe(40);
    }

    public function test_is_low_stock_returns_true_when_below_threshold(): void
    {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'reorder_point' => 10,
        ]);

        expect($level->isLowStock())->toBeTrue();
    }

    public function test_is_low_stock_returns_false_when_above_threshold(): void
    {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'reorder_point' => 10,
        ]);

        expect($level->isLowStock())->toBeFalse();
    }

    public function test_is_low_stock_uses_custom_threshold(): void
    {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 15,
            'quantity_reserved' => 0,
            'reorder_point' => 10,
        ]);

        expect($level->isLowStock(20))->toBeTrue();
        expect($level->isLowStock(10))->toBeFalse();
    }

    public function test_is_safety_stock_breached_returns_true_when_below(): void
    {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'safety_stock' => 10,
        ]);

        expect($level->isSafetyStockBreached())->toBeTrue();
    }

    public function test_is_safety_stock_breached_returns_false_when_no_safety_stock(): void
    {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'safety_stock' => null,
        ]);

        expect($level->isSafetyStockBreached())->toBeFalse();
    }

    public function test_is_over_stocked_returns_true_when_above_max(): void
    {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 150,
            'max_stock' => 100,
        ]);

        expect($level->isOverStocked())->toBeTrue();
    }

    public function test_is_over_stocked_returns_false_when_no_max_stock(): void
    {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 1000,
            'max_stock' => null,
        ]);

        expect($level->isOverStocked())->toBeFalse();
    }

    public function test_get_alert_status_enum_returns_none_when_null(): void
    {
        $level = InventoryLevel::factory()->create([
            'alert_status' => null,
        ]);

        expect($level->getAlertStatusEnum())->toBe(AlertStatus::None);
    }

    public function test_get_alert_status_enum_returns_correct_status(): void
    {
        $level = InventoryLevel::factory()->create([
            'alert_status' => AlertStatus::LowStock->value,
        ]);

        expect($level->getAlertStatusEnum())->toBe(AlertStatus::LowStock);
    }

    public function test_has_available_returns_true_when_sufficient(): void
    {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 50,
            'quantity_reserved' => 10,
        ]);

        expect($level->hasAvailable(30))->toBeTrue();
        expect($level->hasAvailable(40))->toBeTrue();
    }

    public function test_has_available_returns_false_when_insufficient(): void
    {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 50,
            'quantity_reserved' => 10,
        ]);

        expect($level->hasAvailable(50))->toBeFalse();
    }

    public function test_get_effective_allocation_strategy_uses_own_strategy(): void
    {
        $level = InventoryLevel::factory()->create([
            'allocation_strategy' => AllocationStrategy::FIFO->value,
        ]);

        expect($level->getEffectiveAllocationStrategy())->toBe(AllocationStrategy::FIFO);
    }

    public function test_get_effective_allocation_strategy_with_allocation_strategy_set(): void
    {
        $level = InventoryLevel::factory()->create([
            'allocation_strategy' => AllocationStrategy::LeastStock->value,
        ]);

        expect($level->getEffectiveAllocationStrategy())->toBe(AllocationStrategy::LeastStock);
    }

    public function test_get_effective_allocation_strategy_uses_config_when_null(): void
    {
        config()->set('inventory.allocation_strategy', 'priority');

        $level = InventoryLevel::factory()->create([
            'allocation_strategy' => null,
        ]);

        expect($level->getEffectiveAllocationStrategy())->toBe(AllocationStrategy::Priority);
    }

    public function test_increment_on_hand(): void
    {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 100,
        ]);

        $level->incrementOnHand(25);

        expect($level->fresh()->quantity_on_hand)->toBe(125);
    }

    public function test_decrement_on_hand(): void
    {
        $level = InventoryLevel::factory()->create([
            'quantity_on_hand' => 100,
        ]);

        $level->decrementOnHand(30);

        expect($level->fresh()->quantity_on_hand)->toBe(70);
    }

    public function test_increment_reserved(): void
    {
        $level = InventoryLevel::factory()->create([
            'quantity_reserved' => 20,
        ]);

        $level->incrementReserved(10);

        expect($level->fresh()->quantity_reserved)->toBe(30);
    }

    public function test_decrement_reserved(): void
    {
        $level = InventoryLevel::factory()->create([
            'quantity_reserved' => 30,
        ]);

        $level->decrementReserved(15);

        expect($level->fresh()->quantity_reserved)->toBe(15);
    }

    public function test_scope_at_location(): void
    {
        $location2 = InventoryLocation::factory()->create();

        InventoryLevel::factory()->create(['location_id' => $this->location->id]);
        InventoryLevel::factory()->create(['location_id' => $this->location->id]);
        InventoryLevel::factory()->create(['location_id' => $location2->id]);

        $levels = InventoryLevel::atLocation($this->location->id)->get();

        expect($levels)->toHaveCount(2);
    }

    public function test_scope_low_stock(): void
    {
        InventoryLevel::factory()->create([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);
        InventoryLevel::factory()->create([
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);

        $lowStockLevels = InventoryLevel::lowStock(10)->get();

        expect($lowStockLevels)->toHaveCount(1);
    }

    public function test_scope_with_available(): void
    {
        InventoryLevel::factory()->create([
            'quantity_on_hand' => 10,
            'quantity_reserved' => 5,
        ]);
        InventoryLevel::factory()->create([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 5,
        ]);

        $available = InventoryLevel::withAvailable(3)->get();

        expect($available)->toHaveCount(1);
    }

    public function test_scope_with_alert_status(): void
    {
        InventoryLevel::factory()->create(['alert_status' => AlertStatus::LowStock->value]);
        InventoryLevel::factory()->create(['alert_status' => AlertStatus::None->value]);

        $lowStock = InventoryLevel::withAlertStatus(AlertStatus::LowStock)->get();

        expect($lowStock)->toHaveCount(1);
    }

    public function test_scope_needs_reorder(): void
    {
        InventoryLevel::factory()->create(['alert_status' => AlertStatus::LowStock->value]);
        InventoryLevel::factory()->create(['alert_status' => AlertStatus::SafetyBreached->value]);
        InventoryLevel::factory()->create(['alert_status' => AlertStatus::OutOfStock->value]);
        InventoryLevel::factory()->create(['alert_status' => AlertStatus::None->value]);

        $needsReorder = InventoryLevel::needsReorder()->get();

        expect($needsReorder)->toHaveCount(3);
    }

    public function test_scope_safety_stock_breached(): void
    {
        InventoryLevel::factory()->create([
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'safety_stock' => 10,
        ]);
        InventoryLevel::factory()->create([
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
            'safety_stock' => 10,
        ]);

        $breached = InventoryLevel::safetyStockBreached()->get();

        expect($breached)->toHaveCount(1);
    }

    public function test_scope_over_stocked(): void
    {
        InventoryLevel::factory()->create([
            'quantity_on_hand' => 150,
            'max_stock' => 100,
        ]);
        InventoryLevel::factory()->create([
            'quantity_on_hand' => 50,
            'max_stock' => 100,
        ]);

        $overStocked = InventoryLevel::overStocked()->get();

        expect($overStocked)->toHaveCount(1);
    }

    public function test_location_relationship(): void
    {
        $level = InventoryLevel::factory()->create([
            'location_id' => $this->location->id,
        ]);

        expect($level->location)->not->toBeNull();
        expect($level->location->id)->toBe($this->location->id);
    }

    public function test_inventoryable_relationship(): void
    {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
        ]);

        expect($level->inventoryable)->not->toBeNull();
        expect($level->inventoryable->id)->toBe($this->item->id);
    }

    public function test_allocations_relationship(): void
    {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
        ]);

        InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $level->id,
            'cart_id' => 'cart-1',
            'expires_at' => now()->addHour(),
        ]);

        expect($level->allocations)->toHaveCount(1);
    }

    public function test_deleting_level_cascades_to_allocations(): void
    {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
        ]);

        $allocation = InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $level->id,
            'cart_id' => 'cart-1',
            'expires_at' => now()->addHour(),
        ]);

        $level->delete();

        expect(InventoryAllocation::find($allocation->id))->toBeNull();
    }
}
