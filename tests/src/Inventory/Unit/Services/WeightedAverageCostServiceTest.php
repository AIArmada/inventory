<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Models\InventoryCostLayer;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\WeightedAverageCostService;

class WeightedAverageCostServiceTest extends InventoryTestCase
{
    protected WeightedAverageCostService $service;

    protected InventoryItem $item;

    protected InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new WeightedAverageCostService;
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create(['is_active' => true]);
    }

    public function test_record_purchase_first_purchase(): void
    {
        $result = $this->service->recordPurchase(
            $this->item,
            100,
            500,
            $this->location->id,
            null,
            'PO-001'
        );

        expect($result['new_average_cost'])->toBe(500);
        expect($result['layer'])->toBeInstanceOf(InventoryCostLayer::class);
        expect($result['layer']->quantity)->toBe(100);
    }

    public function test_record_purchase_calculates_weighted_average(): void
    {
        // First purchase: 100 @ 500 = 50000
        $this->service->recordPurchase($this->item, 100, 500, $this->location->id);

        // Second purchase: 100 @ 600 = 60000
        // Total: 200 units, 110000 value => 550 average
        $result = $this->service->recordPurchase($this->item, 100, 600, $this->location->id);

        expect($result['new_average_cost'])->toBe(550);
    }

    public function test_consume(): void
    {
        $this->service->recordPurchase($this->item, 100, 500, $this->location->id);

        $result = $this->service->consume($this->item, 30, $this->location->id);

        expect($result['consumed'])->toBe(30);
        expect($result['unit_cost'])->toBe(500);
        expect($result['cost'])->toBe(15000);
    }

    public function test_consume_partial_when_insufficient(): void
    {
        $this->service->recordPurchase($this->item, 50, 500, $this->location->id);

        $result = $this->service->consume($this->item, 100, $this->location->id);

        expect($result['consumed'])->toBe(50);
    }

    public function test_calculate_valuation(): void
    {
        $this->service->recordPurchase($this->item, 100, 500, $this->location->id);

        $valuation = $this->service->calculateValuation($this->item, $this->location->id);

        expect($valuation['quantity'])->toBe(100);
        expect($valuation['value'])->toBe(50000);
        expect($valuation['average_cost'])->toBe(500);
    }

    public function test_get_current_average_cost(): void
    {
        $this->service->recordPurchase($this->item, 100, 500, $this->location->id);
        $this->service->recordPurchase($this->item, 100, 600, $this->location->id);

        $avgCost = $this->service->getCurrentAverageCost($this->item, $this->location->id);

        expect($avgCost)->toBe(550);
    }

    public function test_recalculate(): void
    {
        // Add layers manually with different costs
        InventoryCostLayer::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity' => 100,
            'remaining_quantity' => 100,
            'unit_cost_minor' => 500,
            'costing_method' => CostingMethod::WeightedAverage,
        ]);
        InventoryCostLayer::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity' => 100,
            'remaining_quantity' => 100,
            'unit_cost_minor' => 700,
            'costing_method' => CostingMethod::WeightedAverage,
        ]);

        $newAvg = $this->service->recalculate($this->item, $this->location->id);

        // (100*500 + 100*700) / 200 = 600
        expect($newAvg)->toBe(600);
    }

    public function test_has_available_quantity_true(): void
    {
        $this->service->recordPurchase($this->item, 100, 500, $this->location->id);

        $hasQuantity = $this->service->hasAvailableQuantity($this->item, 80, $this->location->id);

        expect($hasQuantity)->toBeTrue();
    }

    public function test_has_available_quantity_false(): void
    {
        $this->service->recordPurchase($this->item, 50, 500, $this->location->id);

        $hasQuantity = $this->service->hasAvailableQuantity($this->item, 100, $this->location->id);

        expect($hasQuantity)->toBeFalse();
    }

    public function test_updates_existing_layers_on_purchase(): void
    {
        $result1 = $this->service->recordPurchase($this->item, 100, 500, $this->location->id);
        $layer1Id = $result1['layer']->id;

        $result2 = $this->service->recordPurchase($this->item, 100, 600, $this->location->id);

        // The first layer should now have the new weighted average cost
        $layer1 = InventoryCostLayer::find($layer1Id);
        expect($layer1->unit_cost_minor)->toBe($result2['new_average_cost']);
    }
}
