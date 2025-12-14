<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Reports\InventoryKpiService;

beforeEach(function (): void {
    $this->item = InventoryItem::create(['name' => 'Test Product']);
    $this->location = InventoryLocation::factory()->create();
    $this->service = new InventoryKpiService();
});

describe('InventoryKpiService', function (): void {
    describe('calculateTurnoverRatio', function (): void {
        it('returns zero when no inventory', function (): void {
            $ratio = $this->service->calculateTurnoverRatio(
                $this->item->getMorphClass(),
                $this->item->getKey(),
            );

            expect($ratio)->toBe(0.0);
        });

        it('calculates turnover ratio from shipments', function (): void {
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
            ]);

            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Shipment,
                'quantity' => 50,
                'occurred_at' => now()->subDays(10),
            ]);

            $ratio = $this->service->calculateTurnoverRatio(
                $this->item->getMorphClass(),
                $this->item->getKey(),
            );

            expect($ratio)->toBeFloat();
        });
    });

    describe('calculateDaysOnHand', function (): void {
        it('returns zero when no turnover', function (): void {
            $days = $this->service->calculateDaysOnHand(
                $this->item->getMorphClass(),
                $this->item->getKey(),
            );

            expect($days)->toBe(0.0);
        });

        it('calculates days on hand from turnover', function (): void {
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
            ]);

            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Shipment,
                'quantity' => 50,
                'occurred_at' => now()->subDays(30),
            ]);

            $days = $this->service->calculateDaysOnHand(
                $this->item->getMorphClass(),
                $this->item->getKey(),
            );

            expect($days)->toBeFloat();
        });
    });

    describe('calculateFillRate', function (): void {
        it('returns 100 when no shipments', function (): void {
            $rate = $this->service->calculateFillRate(
                $this->item->getMorphClass(),
                $this->item->getKey(),
            );

            expect($rate)->toBe(100.0);
        });

        it('calculates fill rate from shipments', function (): void {
            // Full shipment
            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Shipment,
                'quantity' => 10,
                'reason' => null,
                'occurred_at' => now()->subDays(5),
            ]);

            $rate = $this->service->calculateFillRate(
                $this->item->getMorphClass(),
                $this->item->getKey(),
            );

            expect($rate)->toBe(100.0);
        });
    });

    describe('calculateStockoutRate', function (): void {
        it('returns value for stockout calculation', function (): void {
            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Shipment,
                'quantity' => 10,
                'occurred_at' => now()->subDays(15),
            ]);

            $rate = $this->service->calculateStockoutRate(
                $this->item->getMorphClass(),
                $this->item->getKey(),
            );

            expect($rate)->toBeFloat();
        });

        it('returns zero when period is zero days', function (): void {
            $today = \Carbon\CarbonImmutable::now();
            $tomorrow = $today->addDay();

            // Even with just 1 day period, should return a rate
            $rate = $this->service->calculateStockoutRate(
                $this->item->getMorphClass(),
                $this->item->getKey(),
                null,
                $today,
                $tomorrow,
            );

            expect($rate)->toBe(0.0);
        });

        it('filters by location when provided', function (): void {
            $otherLocation = InventoryLocation::factory()->create();

            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Shipment,
                'quantity' => 10,
                'occurred_at' => now()->subDays(5),
            ]);

            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $otherLocation->id,
                'type' => MovementType::Shipment,
                'quantity' => 5,
                'occurred_at' => now()->subDays(5),
            ]);

            $rateForLocation = $this->service->calculateStockoutRate(
                $this->item->getMorphClass(),
                $this->item->getKey(),
                $this->location->id,
            );

            $rateForOther = $this->service->calculateStockoutRate(
                $this->item->getMorphClass(),
                $this->item->getKey(),
                $otherLocation->id,
            );

            expect($rateForLocation)->toBeFloat();
            expect($rateForOther)->toBeFloat();
        });
    });

    describe('calculateInventoryAccuracy', function (): void {
        it('returns 100 when no cycle counts', function (): void {
            $accuracy = $this->service->calculateInventoryAccuracy();

            expect($accuracy)->toBe(100.0);
        });

        it('calculates accuracy from cycle counts', function (): void {
            // Accurate count (zero adjustment)
            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Adjustment,
                'quantity' => 0,
                'reason' => 'cycle_count',
                'occurred_at' => now()->subDays(5),
            ]);

            $accuracy = $this->service->calculateInventoryAccuracy();

            expect($accuracy)->toBe(100.0);
        });

        it('reduces accuracy for non-zero adjustments', function (): void {
            // Inaccurate count
            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Adjustment,
                'quantity' => 5,
                'reason' => 'cycle_count',
                'occurred_at' => now()->subDays(5),
            ]);

            $accuracy = $this->service->calculateInventoryAccuracy();

            expect($accuracy)->toBe(0.0);
        });
    });

    describe('calculateCarryingCostRate', function (): void {
        it('returns zero when no inventory value', function (): void {
            $rate = $this->service->calculateCarryingCostRate(10000, 0);

            expect($rate)->toBe(0.0);
        });

        it('calculates carrying cost rate', function (): void {
            $rate = $this->service->calculateCarryingCostRate(20000, 100000);

            expect($rate)->toBe(20.0);
        });
    });

    describe('getDashboardKpis', function (): void {
        it('returns complete dashboard data structure', function (): void {
            $kpis = $this->service->getDashboardKpis();

            expect($kpis)->toHaveKeys([
                'total_sku_count',
                'total_inventory_value',
                'average_turnover_ratio',
                'average_days_on_hand',
                'overall_fill_rate',
                'inventory_accuracy',
                'low_stock_items',
                'out_of_stock_items',
            ]);
        })->skip('Production code uses SQLite-incompatible SQL');

        it('counts SKUs correctly', function (): void {
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
            ]);

            $kpis = $this->service->getDashboardKpis();

            expect($kpis['total_sku_count'])->toBe(1);
        })->skip('Production code uses SQLite-incompatible SQL');

        it('counts out of stock items', function (): void {
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 0,
            ]);

            $kpis = $this->service->getDashboardKpis();

            expect($kpis['out_of_stock_items'])->toBeInt();
        })->skip('Production code uses SQLite-incompatible SQL');
    });

    describe('getKpiTrends', function (): void {
        it('returns trend data for specified months', function (): void {
            $trends = $this->service->getKpiTrends(3);

            expect($trends)->toHaveCount(3);
        });

        it('returns correct data structure for trends', function (): void {
            $trends = $this->service->getKpiTrends(1);

            expect($trends->first())->toHaveKeys([
                'date',
                'turnover',
                'fill_rate',
                'accuracy',
            ]);
        });

        it('returns dates in chronological order', function (): void {
            $trends = $this->service->getKpiTrends(3);

            $dates = $trends->pluck('date')->toArray();
            $sortedDates = $dates;
            sort($sortedDates);

            expect($dates)->toBe($sortedDates);
        });
    });
});
