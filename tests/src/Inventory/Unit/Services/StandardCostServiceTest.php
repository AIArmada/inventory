<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryStandardCost;
use AIArmada\Inventory\Services\StandardCostService;

class StandardCostServiceTest extends InventoryTestCase
{
    protected StandardCostService $service;

    protected InventoryItem $item;

    protected InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new StandardCostService;
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create(['is_active' => true]);
    }

    public function test_set_standard_cost(): void
    {
        $cost = $this->service->setStandardCost(
            $this->item,
            1000,
            now(),
            null,
            'admin',
            'Initial cost'
        );

        expect($cost)->toBeInstanceOf(InventoryStandardCost::class);
        expect($cost->standard_cost_minor)->toBe(1000);
        expect($cost->approved_by)->toBe('admin');
    }

    public function test_set_standard_cost_expires_current(): void
    {
        $oldCost = $this->service->setStandardCost($this->item, 500, now()->subMonth());
        $newCost = $this->service->setStandardCost($this->item, 600, now());

        expect($oldCost->fresh()->effective_to)->not->toBeNull();
        expect($newCost->effective_to)->toBeNull();
    }

    public function test_get_current_standard_cost(): void
    {
        $this->service->setStandardCost($this->item, 1000, now());

        $current = $this->service->getCurrentStandardCost($this->item);

        expect($current)->not->toBeNull();
        expect($current->standard_cost_minor)->toBe(1000);
    }

    public function test_get_standard_cost_at(): void
    {
        $this->service->setStandardCost($this->item, 500, now()->subMonths(2), now()->subMonth());
        $this->service->setStandardCost($this->item, 600, now()->subMonth());

        $pastCost = $this->service->getStandardCostAt($this->item, now()->subMonths(1)->subDays(15));

        expect($pastCost->standard_cost_minor)->toBe(500);
    }

    public function test_get_current_cost_value(): void
    {
        $this->service->setStandardCost($this->item, 1000, now());

        $value = $this->service->getCurrentCostValue($this->item);

        expect($value)->toBe(1000);
    }

    public function test_get_current_cost_value_returns_null_when_none(): void
    {
        $value = $this->service->getCurrentCostValue($this->item);

        expect($value)->toBeNull();
    }

    public function test_calculate_valuation(): void
    {
        $this->service->setStandardCost($this->item, 500, now());

        $valuation = $this->service->calculateValuation($this->item, 100);

        expect($valuation['quantity'])->toBe(100);
        expect($valuation['value'])->toBe(50000);
        expect($valuation['unit_cost'])->toBe(500);
    }

    public function test_calculate_variance_favorable(): void
    {
        $this->service->setStandardCost($this->item, 1000, now());

        // Actual cost is lower than standard = favorable
        $variance = $this->service->calculateVariance($this->item, 800);

        expect($variance['variance'])->toBe(-200);
        expect($variance['favorable'])->toBeTrue();
    }

    public function test_calculate_variance_unfavorable(): void
    {
        $this->service->setStandardCost($this->item, 1000, now());

        // Actual cost is higher than standard = unfavorable
        $variance = $this->service->calculateVariance($this->item, 1200);

        expect($variance['variance'])->toBe(200);
        expect($variance['favorable'])->toBeFalse();
    }

    public function test_get_cost_history(): void
    {
        $this->service->setStandardCost($this->item, 500, now()->subMonths(2), now()->subMonth());
        $this->service->setStandardCost($this->item, 600, now()->subMonth(), now());
        $this->service->setStandardCost($this->item, 700, now());

        $history = $this->service->getCostHistory($this->item);

        expect($history)->toHaveCount(3);
    }

    public function test_get_future_costs(): void
    {
        $this->service->setStandardCost($this->item, 500, now());
        InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'standard_cost_minor' => 600,
            'effective_from' => now()->addMonth(),
        ]);

        $future = $this->service->getFutureCosts($this->item);

        expect($future)->toHaveCount(1);
        expect($future->first()->standard_cost_minor)->toBe(600);
    }

    public function test_expire_current_cost(): void
    {
        $this->service->setStandardCost($this->item, 500, now());

        $result = $this->service->expireCurrentCost($this->item);

        expect($result)->toBeTrue();

        $current = $this->service->getCurrentStandardCost($this->item);
        expect($current)->toBeNull();
    }

    public function test_expire_current_cost_returns_false_when_none(): void
    {
        $result = $this->service->expireCurrentCost($this->item);

        expect($result)->toBeFalse();
    }

    public function test_has_standard_cost(): void
    {
        expect($this->service->hasStandardCost($this->item))->toBeFalse();

        $this->service->setStandardCost($this->item, 500, now());

        expect($this->service->hasStandardCost($this->item))->toBeTrue();
    }

    public function test_schedule_cost_change(): void
    {
        $this->service->setStandardCost($this->item, 500, now());

        $scheduled = $this->service->scheduleCostChange(
            $this->item,
            600,
            now()->addMonth(),
            'admin',
            'Annual review'
        );

        expect($scheduled)->toBeInstanceOf(InventoryStandardCost::class);
        expect($scheduled->standard_cost_minor)->toBe(600);
    }

    public function test_schedule_cost_change_throws_for_past_date(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->scheduleCostChange($this->item, 600, now()->subDay());
    }

    public function test_cancel_scheduled_cost(): void
    {
        $scheduled = InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'effective_from' => now()->addMonth(),
        ]);

        $result = $this->service->cancelScheduledCost($scheduled);

        expect($result)->toBeTrue();
        expect(InventoryStandardCost::find($scheduled->id))->toBeNull();
    }

    public function test_cancel_scheduled_cost_throws_for_active_cost(): void
    {
        $activeCost = InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'effective_from' => now()->subDay(),
            'effective_to' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->cancelScheduledCost($activeCost);
    }
}
