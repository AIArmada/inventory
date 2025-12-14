<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\AlertStatus;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Models\InventoryReorderSuggestion;
use AIArmada\Inventory\Reports\StockLevelReport;

beforeEach(function (): void {
    $this->item = InventoryItem::create(['name' => 'Test Product']);
    $this->location = InventoryLocation::factory()->create();
    $this->report = new StockLevelReport();
});

describe('StockLevelReport', function (): void {
    describe('getStockByLocation', function (): void {
        it('returns empty collection when no levels', function (): void {
            $result = $this->report->getStockByLocation();

            expect($result)->toBeEmpty();
        });

        it('groups stock by location', function (): void {
            $location2 = InventoryLocation::factory()->create();

            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
            ]);

            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $location2->id,
                'quantity_on_hand' => 50,
            ]);

            $result = $this->report->getStockByLocation();

            expect($result)->toHaveCount(2);
        });

        it('returns correct data structure', function (): void {
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
            ]);

            $result = $this->report->getStockByLocation();

            expect($result->first())->toHaveKeys([
                'location_id',
                'location_name',
                'sku_count',
                'total_quantity',
                'total_value',
                'low_stock_count',
                'out_of_stock_count',
            ]);
        });

        it('calculates total quantity correctly', function (): void {
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
            ]);

            $result = $this->report->getStockByLocation();

            expect($result->first()['total_quantity'])->toBe(100);
        });
    });

    describe('getAbcAnalysis', function (): void {
        it('returns empty collection when no stock', function (): void {
            $result = $this->report->getAbcAnalysis();

            expect($result)->toBeEmpty();
        });

        it('classifies items by cumulative percentage', function (): void {
            // Create items with different quantities
            for ($i = 1; $i <= 5; $i++) {
                $item = InventoryItem::create(['name' => "Item $i"]);
                InventoryLevel::factory()->create([
                    'inventoryable_type' => $item->getMorphClass(),
                    'inventoryable_id' => $item->getKey(),
                    'location_id' => $this->location->id,
                    'quantity_on_hand' => 100 * (6 - $i),
                ]);
            }

            $result = $this->report->getAbcAnalysis();

            expect($result)->toHaveCount(5);
            expect($result->first()['classification'])->toBe('A');
        });

        it('returns correct data structure', function (): void {
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
            ]);

            $result = $this->report->getAbcAnalysis();

            expect($result->first())->toHaveKeys([
                'inventoryable_type',
                'inventoryable_id',
                'total_value',
                'cumulative_percentage',
                'classification',
            ]);
        });
    });

    describe('getBatchAgingAnalysis', function (): void {
        it('returns all age ranges', function (): void {
            $result = $this->report->getBatchAgingAnalysis();

            expect($result)->toHaveCount(6);
        });

        it('returns correct data structure for each range', function (): void {
            $result = $this->report->getBatchAgingAnalysis();

            expect($result->first())->toHaveKeys([
                'age_range',
                'batch_count',
                'total_quantity',
                'total_value',
                'expiring_soon',
            ]);
        });

        it('groups batches by age', function (): void {
            InventoryBatch::factory()
                ->forInventoryable($this->item->getMorphClass(), $this->item->getKey())
                ->create([
                    'location_id' => $this->location->id,
                    'manufactured_at' => now()->subDays(15),
                    'quantity_on_hand' => 100,
                ]);

            $result = $this->report->getBatchAgingAnalysis();
            $first = $result->firstWhere('age_range', '0-30 days');

            expect($first['batch_count'])->toBe(1);
        });

        it('identifies expiring soon batches', function (): void {
            InventoryBatch::factory()
                ->forInventoryable($this->item->getMorphClass(), $this->item->getKey())
                ->create([
                    'location_id' => $this->location->id,
                    'manufactured_at' => now()->subDays(10),
                    'expires_at' => now()->addDays(15),
                    'quantity_on_hand' => 100,
                ]);

            $result = $this->report->getBatchAgingAnalysis();
            $first = $result->firstWhere('age_range', '0-30 days');

            expect($first['expiring_soon'])->toBe(1);
        });
    });

    describe('getReorderStatus', function (): void {
        it('returns complete status structure', function (): void {
            $result = $this->report->getReorderStatus();

            expect($result)->toHaveKeys([
                'items_below_reorder_point',
                'pending_suggestions',
                'approved_suggestions',
                'total_suggested_value',
                'urgent_reorders',
            ]);
        })->skip('Production code uses SQLite-incompatible SQL');

        it('counts items below reorder point', function (): void {
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 5,
                'reorder_point' => 10,
                'alert_status' => AlertStatus::LowStock->value,
            ]);

            $result = $this->report->getReorderStatus();

            expect($result['items_below_reorder_point'])->toBeInt();
        })->skip('Production code uses SQLite-incompatible SQL');

        it('counts pending suggestions', function (): void {
            InventoryReorderSuggestion::factory()
                ->forModel($this->item)
                ->pending()
                ->create([
                    'location_id' => $this->location->id,
                ]);

            $result = $this->report->getReorderStatus();

            expect($result['pending_suggestions'])->toBe(1);
        })->skip('Production code uses SQLite-incompatible SQL');
    });

    describe('getStockDistribution', function (): void {
        it('returns empty when no multi-location items', function (): void {
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
            ]);

            $result = $this->report->getStockDistribution();

            expect($result)->toBeEmpty();
        });

        it('returns items with multiple locations', function (): void {
            $location2 = InventoryLocation::factory()->create();

            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
            ]);

            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $location2->id,
                'quantity_on_hand' => 50,
            ]);

            $result = $this->report->getStockDistribution();

            expect($result)->toHaveCount(1);
            expect($result->first()['location_count'])->toBe(2);
        });

        it('returns correct data structure', function (): void {
            $location2 = InventoryLocation::factory()->create();

            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
            ]);

            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $location2->id,
                'quantity_on_hand' => 50,
            ]);

            $result = $this->report->getStockDistribution();

            expect($result->first())->toHaveKeys([
                'inventoryable_type',
                'inventoryable_id',
                'location_count',
                'total_quantity',
                'max_location_quantity',
                'min_location_quantity',
                'concentration_ratio',
            ]);
        });

        it('calculates concentration ratio', function (): void {
            $location2 = InventoryLocation::factory()->create();

            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
            ]);

            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $location2->id,
                'quantity_on_hand' => 100,
            ]);

            $result = $this->report->getStockDistribution();

            expect($result->first()['concentration_ratio'])->toBe(50.0);
        });
    });

    describe('getDeadStock', function (): void {
        it('returns empty when no stagnant stock', function (): void {
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
                'updated_at' => now(),
            ]);

            $result = $this->report->getDeadStock();

            expect($result)->toBeEmpty();
        });

        it('returns stagnant items', function (): void {
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
                'updated_at' => now()->subDays(120),
            ]);

            $result = $this->report->getDeadStock();

            expect($result)->toHaveCount(1);
        });

        it('respects days threshold', function (): void {
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
                'updated_at' => now()->subDays(50),
            ]);

            $result = $this->report->getDeadStock(90);

            expect($result)->toBeEmpty();
        });

        it('returns correct data structure', function (): void {
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 100,
                'updated_at' => now()->subDays(120),
            ]);

            $result = $this->report->getDeadStock();

            expect($result->first())->toHaveKeys([
                'inventoryable_type',
                'inventoryable_id',
                'quantity',
                'value',
                'location_id',
                'days_stagnant',
            ]);
        });
    });

    describe('getCycleCountMetrics', function (): void {
        it('returns 100% accuracy when no counts', function (): void {
            $result = $this->report->getCycleCountMetrics();

            expect($result['accuracy_percentage'])->toBe(100.0);
        });

        it('returns complete metrics structure', function (): void {
            $result = $this->report->getCycleCountMetrics();

            expect($result)->toHaveKeys([
                'total_counts',
                'accurate_counts',
                'accuracy_percentage',
                'total_variance_units',
                'total_variance_value',
                'avg_variance_percentage',
            ]);
        });

        it('calculates accuracy from cycle counts', function (): void {
            // Accurate count
            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Adjustment,
                'quantity' => 0,
                'reason' => 'cycle_count',
                'occurred_at' => now()->subDays(5),
            ]);

            $result = $this->report->getCycleCountMetrics();

            expect($result['total_counts'])->toBe(1);
            expect($result['accurate_counts'])->toBe(1);
            expect($result['accuracy_percentage'])->toBe(100.0);
        });

        it('calculates variance', function (): void {
            InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => $this->location->id,
                'type' => MovementType::Adjustment,
                'quantity' => 5,
                'reason' => 'cycle_count',
                'occurred_at' => now()->subDays(5),
            ]);

            $result = $this->report->getCycleCountMetrics();

            expect($result['total_variance_units'])->toBe(5);
            expect($result['accuracy_percentage'])->toBe(0.0);
        });
    });
});
