<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryValuationSnapshot;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->location = InventoryLocation::factory()->create();
});

describe('InventoryValuationSnapshot', function (): void {
    describe('relationships', function (): void {
        it('belongs to location', function (): void {
            $snapshot = InventoryValuationSnapshot::create([
                'location_id' => $this->location->id,
                'costing_method' => CostingMethod::WeightedAverage,
                'snapshot_date' => now(),
                'total_quantity' => 100,
                'total_value_minor' => 10000,
                'average_unit_cost_minor' => 100,
                'currency' => 'USD',
                'sku_count' => 5,
            ]);

            expect($snapshot->location)->not->toBeNull();
            expect($snapshot->location->id)->toBe($this->location->id);
        });
    });

    describe('scopes', function (): void {
        beforeEach(function (): void {
            InventoryValuationSnapshot::create([
                'location_id' => $this->location->id,
                'costing_method' => CostingMethod::WeightedAverage,
                'snapshot_date' => now(),
                'total_quantity' => 100,
                'total_value_minor' => 10000,
                'average_unit_cost_minor' => 100,
                'currency' => 'USD',
                'sku_count' => 5,
            ]);

            InventoryValuationSnapshot::create([
                'location_id' => null,
                'costing_method' => CostingMethod::Fifo,
                'snapshot_date' => now()->subDays(5),
                'total_quantity' => 200,
                'total_value_minor' => 25000,
                'average_unit_cost_minor' => 125,
                'currency' => 'USD',
                'sku_count' => 10,
            ]);
        });

        it('filters by location', function (): void {
            $forLocation = InventoryValuationSnapshot::forLocation($this->location->id)->get();

            expect($forLocation)->toHaveCount(1);
        });

        it('filters all locations (global snapshots)', function (): void {
            $global = InventoryValuationSnapshot::allLocations()->get();

            expect($global)->toHaveCount(1);
        });

        it('filters by costing method', function (): void {
            $fifo = InventoryValuationSnapshot::usingMethod(CostingMethod::Fifo)->get();
            $weightedAvg = InventoryValuationSnapshot::usingMethod(CostingMethod::WeightedAverage)->get();

            expect($fifo)->toHaveCount(1);
            expect($weightedAvg)->toHaveCount(1);
        });

        it('filters by date', function (): void {
            $today = InventoryValuationSnapshot::onDate(now())->get();

            expect($today)->toHaveCount(1);
        });

        it('filters between dates', function (): void {
            $result = InventoryValuationSnapshot::betweenDates(
                now()->subDays(7),
                now()->addDay()
            )->get();

            expect($result)->toHaveCount(2);
        });

        it('orders by snapshot date descending', function (): void {
            $ordered = InventoryValuationSnapshot::latestBySnapshotDate()->get();

            expect($ordered->first()->snapshot_date->gte($ordered->last()->snapshot_date))->toBeTrue();
        });
    });

    describe('hasVariance', function (): void {
        it('returns true when variance is non-zero', function (): void {
            $snapshot = InventoryValuationSnapshot::create([
                'costing_method' => CostingMethod::WeightedAverage,
                'snapshot_date' => now(),
                'total_quantity' => 100,
                'total_value_minor' => 10000,
                'average_unit_cost_minor' => 100,
                'currency' => 'USD',
                'sku_count' => 5,
                'variance_from_previous_minor' => 500,
            ]);

            expect($snapshot->hasVariance())->toBeTrue();
        });

        it('returns false when variance is null', function (): void {
            $snapshot = InventoryValuationSnapshot::create([
                'costing_method' => CostingMethod::WeightedAverage,
                'snapshot_date' => now(),
                'total_quantity' => 100,
                'total_value_minor' => 10000,
                'average_unit_cost_minor' => 100,
                'currency' => 'USD',
                'sku_count' => 5,
                'variance_from_previous_minor' => null,
            ]);

            expect($snapshot->hasVariance())->toBeFalse();
        });

        it('returns false when variance is zero', function (): void {
            $snapshot = InventoryValuationSnapshot::create([
                'costing_method' => CostingMethod::WeightedAverage,
                'snapshot_date' => now(),
                'total_quantity' => 100,
                'total_value_minor' => 10000,
                'average_unit_cost_minor' => 100,
                'currency' => 'USD',
                'sku_count' => 5,
                'variance_from_previous_minor' => 0,
            ]);

            expect($snapshot->hasVariance())->toBeFalse();
        });
    });

    describe('variancePercentage', function (): void {
        it('calculates variance percentage correctly', function (): void {
            $snapshot = InventoryValuationSnapshot::create([
                'costing_method' => CostingMethod::WeightedAverage,
                'snapshot_date' => now(),
                'total_quantity' => 100,
                'total_value_minor' => 11000, // Current value
                'average_unit_cost_minor' => 110,
                'currency' => 'USD',
                'sku_count' => 5,
                'variance_from_previous_minor' => 1000, // Variance
            ]);

            // Previous value = 11000 - 1000 = 10000
            // Percentage = (1000 / 10000) * 100 = 10%
            expect($snapshot->variancePercentage())->toBe(10.0);
        });

        it('returns null when variance is null', function (): void {
            $snapshot = InventoryValuationSnapshot::create([
                'costing_method' => CostingMethod::WeightedAverage,
                'snapshot_date' => now(),
                'total_quantity' => 100,
                'total_value_minor' => 10000,
                'average_unit_cost_minor' => 100,
                'currency' => 'USD',
                'sku_count' => 5,
                'variance_from_previous_minor' => null,
            ]);

            expect($snapshot->variancePercentage())->toBeNull();
        });

        it('returns null when previous value would be zero', function (): void {
            $snapshot = InventoryValuationSnapshot::create([
                'costing_method' => CostingMethod::WeightedAverage,
                'snapshot_date' => now(),
                'total_quantity' => 100,
                'total_value_minor' => 5000,
                'average_unit_cost_minor' => 50,
                'currency' => 'USD',
                'sku_count' => 5,
                'variance_from_previous_minor' => 5000, // Previous would be 0
            ]);

            expect($snapshot->variancePercentage())->toBeNull();
        });
    });

    describe('isPositiveVariance', function (): void {
        it('returns true for positive variance', function (): void {
            $snapshot = InventoryValuationSnapshot::create([
                'costing_method' => CostingMethod::WeightedAverage,
                'snapshot_date' => now(),
                'total_quantity' => 100,
                'total_value_minor' => 10000,
                'average_unit_cost_minor' => 100,
                'currency' => 'USD',
                'sku_count' => 5,
                'variance_from_previous_minor' => 500,
            ]);

            expect($snapshot->isPositiveVariance())->toBeTrue();
        });

        it('returns false for negative variance', function (): void {
            $snapshot = InventoryValuationSnapshot::create([
                'costing_method' => CostingMethod::WeightedAverage,
                'snapshot_date' => now(),
                'total_quantity' => 100,
                'total_value_minor' => 10000,
                'average_unit_cost_minor' => 100,
                'currency' => 'USD',
                'sku_count' => 5,
                'variance_from_previous_minor' => -500,
            ]);

            expect($snapshot->isPositiveVariance())->toBeFalse();
        });

        it('returns false for null variance', function (): void {
            $snapshot = InventoryValuationSnapshot::create([
                'costing_method' => CostingMethod::WeightedAverage,
                'snapshot_date' => now(),
                'total_quantity' => 100,
                'total_value_minor' => 10000,
                'average_unit_cost_minor' => 100,
                'currency' => 'USD',
                'sku_count' => 5,
                'variance_from_previous_minor' => null,
            ]);

            expect($snapshot->isPositiveVariance())->toBeFalse();
        });
    });

    describe('isNegativeVariance', function (): void {
        it('returns true for negative variance', function (): void {
            $snapshot = InventoryValuationSnapshot::create([
                'costing_method' => CostingMethod::WeightedAverage,
                'snapshot_date' => now(),
                'total_quantity' => 100,
                'total_value_minor' => 10000,
                'average_unit_cost_minor' => 100,
                'currency' => 'USD',
                'sku_count' => 5,
                'variance_from_previous_minor' => -500,
            ]);

            expect($snapshot->isNegativeVariance())->toBeTrue();
        });

        it('returns false for positive variance', function (): void {
            $snapshot = InventoryValuationSnapshot::create([
                'costing_method' => CostingMethod::WeightedAverage,
                'snapshot_date' => now(),
                'total_quantity' => 100,
                'total_value_minor' => 10000,
                'average_unit_cost_minor' => 100,
                'currency' => 'USD',
                'sku_count' => 5,
                'variance_from_previous_minor' => 500,
            ]);

            expect($snapshot->isNegativeVariance())->toBeFalse();
        });

        it('returns false for null variance', function (): void {
            $snapshot = InventoryValuationSnapshot::create([
                'costing_method' => CostingMethod::WeightedAverage,
                'snapshot_date' => now(),
                'total_quantity' => 100,
                'total_value_minor' => 10000,
                'average_unit_cost_minor' => 100,
                'currency' => 'USD',
                'sku_count' => 5,
                'variance_from_previous_minor' => null,
            ]);

            expect($snapshot->isNegativeVariance())->toBeFalse();
        });
    });

    describe('getBreakdownByType', function (): void {
        it('returns breakdown data for a specific type', function (): void {
            $snapshot = InventoryValuationSnapshot::create([
                'costing_method' => CostingMethod::WeightedAverage,
                'snapshot_date' => now(),
                'total_quantity' => 100,
                'total_value_minor' => 10000,
                'average_unit_cost_minor' => 100,
                'currency' => 'USD',
                'sku_count' => 5,
                'breakdown' => [
                    'raw_materials' => ['type' => 'raw_materials', 'units' => 50, 'value' => 5000],
                    'finished_goods' => ['type' => 'finished_goods', 'units' => 50, 'value' => 5000],
                ],
            ]);

            $rawMaterials = $snapshot->getBreakdownByType('raw_materials');

            expect($rawMaterials)->toBeArray();
            expect($rawMaterials['units'])->toBe(50);
            expect($rawMaterials['value'])->toBe(5000);
        });

        it('returns null for non-existent type', function (): void {
            $snapshot = InventoryValuationSnapshot::create([
                'costing_method' => CostingMethod::WeightedAverage,
                'snapshot_date' => now(),
                'total_quantity' => 100,
                'total_value_minor' => 10000,
                'average_unit_cost_minor' => 100,
                'currency' => 'USD',
                'sku_count' => 5,
                'breakdown' => [
                    'raw_materials' => ['type' => 'raw_materials', 'units' => 50, 'value' => 5000],
                ],
            ]);

            expect($snapshot->getBreakdownByType('work_in_progress'))->toBeNull();
        });

        it('returns null when breakdown is null', function (): void {
            $snapshot = InventoryValuationSnapshot::create([
                'costing_method' => CostingMethod::WeightedAverage,
                'snapshot_date' => now(),
                'total_quantity' => 100,
                'total_value_minor' => 10000,
                'average_unit_cost_minor' => 100,
                'currency' => 'USD',
                'sku_count' => 5,
                'breakdown' => null,
            ]);

            expect($snapshot->getBreakdownByType('raw_materials'))->toBeNull();
        });
    });

    describe('casts', function (): void {
        it('casts costing_method to enum', function (): void {
            $snapshot = InventoryValuationSnapshot::create([
                'costing_method' => CostingMethod::Fifo->value,
                'snapshot_date' => now(),
                'total_quantity' => 100,
                'total_value_minor' => 10000,
                'average_unit_cost_minor' => 100,
                'currency' => 'USD',
                'sku_count' => 5,
            ]);

            expect($snapshot->costing_method)->toBe(CostingMethod::Fifo);
        });

        it('casts snapshot_date to date', function (): void {
            $snapshot = InventoryValuationSnapshot::create([
                'costing_method' => CostingMethod::WeightedAverage,
                'snapshot_date' => '2024-06-15',
                'total_quantity' => 100,
                'total_value_minor' => 10000,
                'average_unit_cost_minor' => 100,
                'currency' => 'USD',
                'sku_count' => 5,
            ]);

            expect($snapshot->snapshot_date)->toBeInstanceOf(Carbon::class);
        });

        it('casts integer fields correctly', function (): void {
            $snapshot = InventoryValuationSnapshot::create([
                'costing_method' => CostingMethod::WeightedAverage,
                'snapshot_date' => now(),
                'total_quantity' => '100',
                'total_value_minor' => '10000',
                'average_unit_cost_minor' => '100',
                'currency' => 'USD',
                'sku_count' => '5',
                'variance_from_previous_minor' => '500',
            ]);

            expect($snapshot->total_quantity)->toBeInt();
            expect($snapshot->total_value_minor)->toBeInt();
            expect($snapshot->average_unit_cost_minor)->toBeInt();
            expect($snapshot->sku_count)->toBeInt();
            expect($snapshot->variance_from_previous_minor)->toBeInt();
        });

        it('casts breakdown to array', function (): void {
            $snapshot = InventoryValuationSnapshot::create([
                'costing_method' => CostingMethod::WeightedAverage,
                'snapshot_date' => now(),
                'total_quantity' => 100,
                'total_value_minor' => 10000,
                'average_unit_cost_minor' => 100,
                'currency' => 'USD',
                'sku_count' => 5,
                'breakdown' => ['test' => 'data'],
            ]);

            expect($snapshot->breakdown)->toBeArray();
        });

        it('casts metadata to array', function (): void {
            $snapshot = InventoryValuationSnapshot::create([
                'costing_method' => CostingMethod::WeightedAverage,
                'snapshot_date' => now(),
                'total_quantity' => 100,
                'total_value_minor' => 10000,
                'average_unit_cost_minor' => 100,
                'currency' => 'USD',
                'sku_count' => 5,
                'metadata' => ['generated_by' => 'system'],
            ]);

            expect($snapshot->metadata)->toBeArray();
        });
    });
});
