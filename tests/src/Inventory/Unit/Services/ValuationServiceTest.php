<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Models\InventoryCostLayer;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryValuationSnapshot;
use AIArmada\Inventory\Services\FifoCostService;
use AIArmada\Inventory\Services\StandardCostService;
use AIArmada\Inventory\Services\ValuationService;
use AIArmada\Inventory\Services\WeightedAverageCostService;

beforeEach(function (): void {
    $this->location = InventoryLocation::factory()->create(['is_active' => true]);
    $this->product = InventoryItem::create(['name' => 'Test Product']);
    $this->fifoCostService = new FifoCostService();
    $this->weightedAverageCostService = new WeightedAverageCostService();
    $this->standardCostService = new StandardCostService();
    $this->service = new ValuationService(
        $this->fifoCostService,
        $this->weightedAverageCostService,
        $this->standardCostService
    );
});

describe('calculateValuation', function (): void {
    it('uses FIFO method when specified', function (): void {
        InventoryCostLayer::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'costing_method' => CostingMethod::Fifo,
            'quantity' => 100,
            'remaining_quantity' => 100,
            'unit_cost_minor' => 1000,
        ]);

        $result = $this->service->calculateValuation($this->product, CostingMethod::Fifo);

        expect($result['quantity'])->toBe(100);
        expect($result['value'])->toBe(100000);
        expect($result['average_cost'])->toBe(1000);
    });

    it('uses weighted average method when specified', function (): void {
        InventoryCostLayer::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'costing_method' => CostingMethod::WeightedAverage,
            'quantity' => 50,
            'remaining_quantity' => 50,
            'unit_cost_minor' => 2000,
        ]);

        $result = $this->service->calculateValuation($this->product, CostingMethod::WeightedAverage);

        expect($result['quantity'])->toBe(50);
        expect($result['value'])->toBe(100000);
    });

    it('uses standard cost method when specified', function (): void {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
        ]);

        $result = $this->service->calculateValuation($this->product, CostingMethod::Standard);

        expect($result['quantity'])->toBe(100);
    });

    it('filters by location when provided', function (): void {
        $otherLocation = InventoryLocation::factory()->create();

        InventoryCostLayer::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'costing_method' => CostingMethod::Fifo,
            'quantity' => 50,
            'remaining_quantity' => 50,
            'unit_cost_minor' => 1000,
        ]);

        InventoryCostLayer::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $otherLocation->id,
            'costing_method' => CostingMethod::Fifo,
            'quantity' => 100,
            'remaining_quantity' => 100,
            'unit_cost_minor' => 2000,
        ]);

        $result = $this->service->calculateValuation($this->product, CostingMethod::Fifo, $this->location->id);

        expect($result['quantity'])->toBe(50);
        expect($result['value'])->toBe(50000);
    });
});

describe('getLocationValuation', function (): void {
    it('calculates total valuation for a location', function (): void {
        $product2 = InventoryItem::create(['name' => 'Test Product 2']);

        InventoryCostLayer::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'costing_method' => CostingMethod::Fifo,
            'quantity' => 50,
            'remaining_quantity' => 50,
            'unit_cost_minor' => 1000,
        ]);

        InventoryCostLayer::factory()->create([
            'inventoryable_type' => $product2->getMorphClass(),
            'inventoryable_id' => $product2->id,
            'location_id' => $this->location->id,
            'costing_method' => CostingMethod::Fifo,
            'quantity' => 30,
            'remaining_quantity' => 30,
            'unit_cost_minor' => 2000,
        ]);

        $result = $this->service->getLocationValuation($this->location->id, CostingMethod::Fifo);

        expect($result['total_quantity'])->toBe(80);
        expect($result['total_value'])->toBe(110000);
        expect($result['sku_count'])->toBe(2);
    });
});

describe('getTotalValuation', function (): void {
    it('calculates total valuation across all locations', function (): void {
        $location2 = InventoryLocation::factory()->create(['is_active' => true]);

        InventoryCostLayer::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'costing_method' => CostingMethod::Fifo,
            'quantity' => 50,
            'remaining_quantity' => 50,
            'unit_cost_minor' => 1000,
        ]);

        InventoryCostLayer::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $location2->id,
            'costing_method' => CostingMethod::Fifo,
            'quantity' => 30,
            'remaining_quantity' => 30,
            'unit_cost_minor' => 1500,
        ]);

        $result = $this->service->getTotalValuation(CostingMethod::Fifo);

        expect($result['total_quantity'])->toBe(80);
        expect($result['total_value'])->toBe(95000);
        expect($result['sku_count'])->toBe(1);
        expect($result['location_count'])->toBe(2);
    });
});

describe('createSnapshot', function (): void {
    it('creates a valuation snapshot', function (): void {
        InventoryCostLayer::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'costing_method' => CostingMethod::Fifo,
            'quantity' => 100,
            'remaining_quantity' => 100,
            'unit_cost_minor' => 1000,
        ]);

        $snapshot = $this->service->createSnapshot(CostingMethod::Fifo);

        expect($snapshot)->toBeInstanceOf(InventoryValuationSnapshot::class);
        expect($snapshot->total_quantity)->toBe(100);
        expect($snapshot->total_value_minor)->toBe(100000);
        expect($snapshot->costing_method)->toBe(CostingMethod::Fifo);
    });

    it('creates location-specific snapshot', function (): void {
        InventoryCostLayer::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'costing_method' => CostingMethod::Fifo,
            'quantity' => 50,
            'remaining_quantity' => 50,
            'unit_cost_minor' => 1000,
        ]);

        $snapshot = $this->service->createSnapshot(CostingMethod::Fifo, $this->location->id);

        expect($snapshot->location_id)->toBe($this->location->id);
        expect($snapshot->total_quantity)->toBe(50);
    });

    it('calculates variance from previous snapshot', function (): void {
        InventoryValuationSnapshot::factory()->create([
            'costing_method' => CostingMethod::Fifo,
            'location_id' => null,
            'total_value_minor' => 80000,
            'snapshot_date' => now()->subDay(),
        ]);

        InventoryCostLayer::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'costing_method' => CostingMethod::Fifo,
            'quantity' => 100,
            'remaining_quantity' => 100,
            'unit_cost_minor' => 1000,
        ]);

        $snapshot = $this->service->createSnapshot(CostingMethod::Fifo);

        expect($snapshot->variance_from_previous_minor)->toBe(20000);
    });
});

describe('getLatestSnapshot', function (): void {
    it('returns latest snapshot for method', function (): void {
        InventoryValuationSnapshot::factory()->create([
            'costing_method' => CostingMethod::Fifo,
            'location_id' => null,
            'snapshot_date' => '2024-01-01',
        ]);

        $newer = InventoryValuationSnapshot::factory()->create([
            'costing_method' => CostingMethod::Fifo,
            'location_id' => null,
            'snapshot_date' => '2024-06-15',
        ]);

        $result = $this->service->getLatestSnapshot(CostingMethod::Fifo);

        expect($result->id)->toBe($newer->id);
    });

    it('filters by location', function (): void {
        InventoryValuationSnapshot::factory()->create([
            'costing_method' => CostingMethod::Fifo,
            'location_id' => null,
            'snapshot_date' => now()->subDay(),
            'total_value_minor' => 100000,
        ]);

        $locationSnapshot = InventoryValuationSnapshot::factory()->create([
            'costing_method' => CostingMethod::Fifo,
            'location_id' => $this->location->id,
            'snapshot_date' => now(),
            'total_value_minor' => 50000,
        ]);

        $result = $this->service->getLatestSnapshot(CostingMethod::Fifo, $this->location->id);

        expect($result->id)->toBe($locationSnapshot->id);
    });

    it('returns null when no snapshots exist', function (): void {
        $result = $this->service->getLatestSnapshot(CostingMethod::Fifo);

        expect($result)->toBeNull();
    });
});

describe('getSnapshotsForRange', function (): void {
    it('returns snapshots within date range', function (): void {
        InventoryValuationSnapshot::factory()->create([
            'costing_method' => CostingMethod::Fifo,
            'location_id' => null,
            'snapshot_date' => now()->subDays(10),
        ]);

        InventoryValuationSnapshot::factory()->create([
            'costing_method' => CostingMethod::Fifo,
            'location_id' => null,
            'snapshot_date' => now()->subDays(5),
        ]);

        InventoryValuationSnapshot::factory()->create([
            'costing_method' => CostingMethod::Fifo,
            'location_id' => null,
            'snapshot_date' => now()->subDay(),
        ]);

        $result = $this->service->getSnapshotsForRange(
            CostingMethod::Fifo,
            now()->subDays(7),
            now()
        );

        expect($result)->toHaveCount(2);
    });
});

describe('compareValuations', function (): void {
    it('compares two costing methods', function (): void {
        InventoryCostLayer::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'costing_method' => CostingMethod::Fifo,
            'quantity' => 100,
            'remaining_quantity' => 100,
            'unit_cost_minor' => 1000,
        ]);

        InventoryCostLayer::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'costing_method' => CostingMethod::WeightedAverage,
            'quantity' => 100,
            'remaining_quantity' => 100,
            'unit_cost_minor' => 1200,
        ]);

        $result = $this->service->compareValuations(
            $this->product,
            CostingMethod::Fifo,
            CostingMethod::WeightedAverage
        );

        expect($result['method1']['value'])->toBe(100000);
        expect($result['method2']['value'])->toBe(120000);
        expect($result['difference'])->toBe(-20000);
    });
});

describe('generateLocationReport', function (): void {
    it('generates report for all active locations', function (): void {
        $location2 = InventoryLocation::factory()->create(['is_active' => true, 'name' => 'Warehouse B']);

        InventoryCostLayer::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'costing_method' => CostingMethod::Fifo,
            'quantity' => 50,
            'remaining_quantity' => 50,
            'unit_cost_minor' => 1000,
        ]);

        InventoryCostLayer::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $location2->id,
            'costing_method' => CostingMethod::Fifo,
            'quantity' => 30,
            'remaining_quantity' => 30,
            'unit_cost_minor' => 2000,
        ]);

        $report = $this->service->generateLocationReport(CostingMethod::Fifo);

        expect($report)->toHaveKey($this->location->id);
        expect($report)->toHaveKey($location2->id);
        expect($report[$this->location->id]['quantity'])->toBe(50);
        expect($report[$location2->id]['quantity'])->toBe(30);
    });
});

describe('createDailySnapshots', function (): void {
    it('creates snapshots for all locations plus global', function (): void {
        $location2 = InventoryLocation::factory()->create(['is_active' => true]);

        InventoryCostLayer::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'costing_method' => CostingMethod::Fifo,
            'quantity' => 50,
            'remaining_quantity' => 50,
            'unit_cost_minor' => 1000,
        ]);

        $snapshots = $this->service->createDailySnapshots(CostingMethod::Fifo);

        expect($snapshots->count())->toBeGreaterThanOrEqual(2);
        expect($snapshots->first()->location_id)->toBeNull();
    });
});
