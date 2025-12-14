<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\DemandPeriodType;
use AIArmada\Inventory\Models\InventoryDemandHistory;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\DemandForecastService;

beforeEach(function (): void {
    $this->location = InventoryLocation::factory()->create();
    $this->product = InventoryItem::create(['name' => 'Test Product']);
    $this->service = new DemandForecastService();
});

describe('recordDemand', function (): void {
    it('creates new demand history record', function (): void {
        $record = $this->service->recordDemand(
            $this->product,
            quantity: 100,
            fulfilledQuantity: 90
        );

        expect($record)->toBeInstanceOf(InventoryDemandHistory::class);
        expect($record->quantity_demanded)->toBe(100);
        expect($record->quantity_fulfilled)->toBe(90);
        expect($record->quantity_lost)->toBe(10);
        expect($record->order_count)->toBe(1);
    });

    it('updates existing record for same period', function (): void {
        $this->service->recordDemand($this->product, 50, 50);
        $record = $this->service->recordDemand($this->product, 30, 25);

        expect($record->quantity_demanded)->toBe(80);
        expect($record->quantity_fulfilled)->toBe(75);
        expect($record->quantity_lost)->toBe(5);
        expect($record->order_count)->toBe(2);

        expect(InventoryDemandHistory::count())->toBe(1);
    });

    it('creates record with location', function (): void {
        $record = $this->service->recordDemand(
            $this->product,
            quantity: 50,
            fulfilledQuantity: 50,
            locationId: $this->location->id
        );

        expect($record->location_id)->toBe($this->location->id);
    });

    it('creates record with custom period type', function (): void {
        $record = $this->service->recordDemand(
            $this->product,
            quantity: 100,
            fulfilledQuantity: 100,
            periodType: DemandPeriodType::Weekly
        );

        expect($record->period_type)->toBe(DemandPeriodType::Weekly);
    });

    it('handles zero lost quantity correctly', function (): void {
        $record = $this->service->recordDemand($this->product, 50, 50);

        expect($record->quantity_lost)->toBe(0);
    });
});

describe('calculateAverageDailyDemand', function (): void {
    it('calculates average daily demand', function (): void {
        for ($i = 0; $i < 10; $i++) {
            InventoryDemandHistory::factory()->create([
                'inventoryable_type' => $this->product->getMorphClass(),
                'inventoryable_id' => $this->product->id,
                'period_type' => DemandPeriodType::Daily,
                'period_date' => now()->subDays($i),
                'quantity_demanded' => 20,
            ]);
        }

        $avg = $this->service->calculateAverageDailyDemand($this->product, 30);

        expect($avg)->toBe(20 * 10 / 30);
    });

    it('returns zero when no history', function (): void {
        $avg = $this->service->calculateAverageDailyDemand($this->product, 30);

        expect($avg)->toBe(0.0);
    });

    it('filters by location when provided', function (): void {
        InventoryDemandHistory::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'period_type' => DemandPeriodType::Daily,
            'period_date' => now()->subDay(),
            'quantity_demanded' => 100,
        ]);

        InventoryDemandHistory::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => null,
            'period_type' => DemandPeriodType::Daily,
            'period_date' => now()->subDay(),
            'quantity_demanded' => 50,
        ]);

        $avg = $this->service->calculateAverageDailyDemand($this->product, 30, $this->location->id);

        expect($avg)->toBe(100 / 30);
    });
});

describe('calculateWeightedMovingAverage', function (): void {
    it('calculates weighted moving average', function (): void {
        for ($i = 0; $i < 4; $i++) {
            InventoryDemandHistory::factory()->create([
                'inventoryable_type' => $this->product->getMorphClass(),
                'inventoryable_id' => $this->product->id,
                'period_type' => DemandPeriodType::Daily,
                'period_date' => now()->subDays($i),
                'quantity_demanded' => 10 * ($i + 1),
            ]);
        }

        $wma = $this->service->calculateWeightedMovingAverage($this->product, 4);

        expect($wma)->toBeGreaterThan(0);
    });

    it('returns zero when no history', function (): void {
        $wma = $this->service->calculateWeightedMovingAverage($this->product, 4);

        expect($wma)->toBe(0.0);
    });

    it('uses custom weights when provided', function (): void {
        for ($i = 0; $i < 3; $i++) {
            InventoryDemandHistory::factory()->create([
                'inventoryable_type' => $this->product->getMorphClass(),
                'inventoryable_id' => $this->product->id,
                'period_type' => DemandPeriodType::Daily,
                'period_date' => now()->subDays($i),
                'quantity_demanded' => 100,
            ]);
        }

        $wma = $this->service->calculateWeightedMovingAverage(
            $this->product,
            3,
            [1.0, 1.0, 1.0]
        );

        expect($wma)->toBe(100.0);
    });
});

describe('calculateExponentialSmoothing', function (): void {
    it('calculates exponential smoothing forecast', function (): void {
        for ($i = 10; $i >= 0; $i--) {
            InventoryDemandHistory::factory()->create([
                'inventoryable_type' => $this->product->getMorphClass(),
                'inventoryable_id' => $this->product->id,
                'period_type' => DemandPeriodType::Daily,
                'period_date' => now()->subDays($i),
                'quantity_demanded' => 100,
            ]);
        }

        $forecast = $this->service->calculateExponentialSmoothing($this->product, 0.3, 10);

        expect($forecast)->toBe(100.0);
    });

    it('returns zero when no history', function (): void {
        $forecast = $this->service->calculateExponentialSmoothing($this->product);

        expect($forecast)->toBe(0.0);
    });

    it('weights recent values more with higher alpha', function (): void {
        InventoryDemandHistory::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'period_type' => DemandPeriodType::Daily,
            'period_date' => now()->subDays(2),
            'quantity_demanded' => 50,
        ]);

        InventoryDemandHistory::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'period_type' => DemandPeriodType::Daily,
            'period_date' => now()->subDay(),
            'quantity_demanded' => 150,
        ]);

        $forecast = $this->service->calculateExponentialSmoothing($this->product, 0.9, 10);

        expect($forecast)->toBeGreaterThan(100);
    });
});

describe('calculateDemandVariability', function (): void {
    it('calculates standard deviation of demand', function (): void {
        $demands = [100, 110, 90, 105, 95];

        foreach ($demands as $i => $demand) {
            InventoryDemandHistory::factory()->create([
                'inventoryable_type' => $this->product->getMorphClass(),
                'inventoryable_id' => $this->product->id,
                'period_type' => DemandPeriodType::Daily,
                'period_date' => now()->subDays($i),
                'quantity_demanded' => $demand,
            ]);
        }

        $variability = $this->service->calculateDemandVariability($this->product, 30);

        expect($variability)->toBeGreaterThan(0);
    });

    it('returns zero when insufficient data', function (): void {
        InventoryDemandHistory::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'period_type' => DemandPeriodType::Daily,
            'period_date' => now(),
            'quantity_demanded' => 100,
        ]);

        $variability = $this->service->calculateDemandVariability($this->product, 30);

        expect($variability)->toBe(0.0);
    });

    it('returns zero for constant demand', function (): void {
        for ($i = 0; $i < 5; $i++) {
            InventoryDemandHistory::factory()->create([
                'inventoryable_type' => $this->product->getMorphClass(),
                'inventoryable_id' => $this->product->id,
                'period_type' => DemandPeriodType::Daily,
                'period_date' => now()->subDays($i),
                'quantity_demanded' => 100,
            ]);
        }

        $variability = $this->service->calculateDemandVariability($this->product, 30);

        expect($variability)->toBe(0.0);
    });
});

describe('forecastDemand', function (): void {
    it('returns forecast with confidence intervals', function (): void {
        for ($i = 0; $i < 10; $i++) {
            InventoryDemandHistory::factory()->create([
                'inventoryable_type' => $this->product->getMorphClass(),
                'inventoryable_id' => $this->product->id,
                'period_type' => DemandPeriodType::Daily,
                'period_date' => now()->subDays($i),
                'quantity_demanded' => 100 + ($i * 2),
            ]);
        }

        $forecast = $this->service->forecastDemand($this->product, 7);

        expect($forecast)->toHaveKey('forecast');
        expect($forecast)->toHaveKey('confidence_low');
        expect($forecast)->toHaveKey('confidence_high');
        expect($forecast['confidence_low'])->toBeLessThanOrEqual($forecast['forecast']);
        expect($forecast['confidence_high'])->toBeGreaterThanOrEqual($forecast['forecast']);
    });

    it('handles zero demand history', function (): void {
        $forecast = $this->service->forecastDemand($this->product, 7);

        expect($forecast['forecast'])->toBe(0.0);
        expect((float) $forecast['confidence_low'])->toBe(0.0);
    });
});

describe('calculateTrend', function (): void {
    it('returns positive trend for increasing demand', function (): void {
        for ($i = 10; $i >= 0; $i--) {
            InventoryDemandHistory::factory()->create([
                'inventoryable_type' => $this->product->getMorphClass(),
                'inventoryable_id' => $this->product->id,
                'period_type' => DemandPeriodType::Daily,
                'period_date' => now()->subDays($i),
                'quantity_demanded' => 100 + ((10 - $i) * 10),
            ]);
        }

        $trend = $this->service->calculateTrend($this->product, 30);

        expect($trend)->toBeGreaterThan(0);
    });

    it('returns negative trend for decreasing demand', function (): void {
        for ($i = 10; $i >= 0; $i--) {
            InventoryDemandHistory::factory()->create([
                'inventoryable_type' => $this->product->getMorphClass(),
                'inventoryable_id' => $this->product->id,
                'period_type' => DemandPeriodType::Daily,
                'period_date' => now()->subDays($i),
                'quantity_demanded' => 200 - ((10 - $i) * 10),
            ]);
        }

        $trend = $this->service->calculateTrend($this->product, 30);

        expect($trend)->toBeLessThan(0);
    });

    it('returns zero for flat demand', function (): void {
        for ($i = 0; $i < 5; $i++) {
            InventoryDemandHistory::factory()->create([
                'inventoryable_type' => $this->product->getMorphClass(),
                'inventoryable_id' => $this->product->id,
                'period_type' => DemandPeriodType::Daily,
                'period_date' => now()->subDays($i),
                'quantity_demanded' => 100,
            ]);
        }

        $trend = $this->service->calculateTrend($this->product, 30);

        expect($trend)->toBe(0.0);
    });

    it('returns zero for insufficient data', function (): void {
        InventoryDemandHistory::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'period_type' => DemandPeriodType::Daily,
            'period_date' => now(),
            'quantity_demanded' => 100,
        ]);

        $trend = $this->service->calculateTrend($this->product, 30);

        expect($trend)->toBe(0.0);
    });
});

describe('getDemandSummary', function (): void {
    it('returns demand summary statistics', function (): void {
        InventoryDemandHistory::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'period_type' => DemandPeriodType::Daily,
            'period_date' => now()->subDay(),
            'quantity_demanded' => 100,
            'quantity_fulfilled' => 90,
            'quantity_lost' => 10,
        ]);

        InventoryDemandHistory::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'period_type' => DemandPeriodType::Daily,
            'period_date' => now()->subDays(2),
            'quantity_demanded' => 50,
            'quantity_fulfilled' => 50,
            'quantity_lost' => 0,
        ]);

        $summary = $this->service->getDemandSummary($this->product, 30);

        expect($summary['total_demand'])->toBe(150);
        expect($summary['total_fulfilled'])->toBe(140);
        expect($summary['total_lost'])->toBe(10);
        expect($summary['fulfillment_rate'])->toBeGreaterThan(90);
        expect($summary['periods'])->toBe(2);
    });

    it('returns 100% fulfillment rate when no demand', function (): void {
        $summary = $this->service->getDemandSummary($this->product, 30);

        expect($summary['fulfillment_rate'])->toBe(100.0);
        expect($summary['periods'])->toBe(0);
    });
});
