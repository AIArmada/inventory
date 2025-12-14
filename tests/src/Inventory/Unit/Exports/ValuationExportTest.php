<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Exports\ExportableInterface;
use AIArmada\Inventory\Exports\ValuationExport;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryValuationSnapshot;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->location = InventoryLocation::factory()->create(['name' => 'Test Location']);
});

describe('ValuationExport', function (): void {
    it('implements ExportableInterface', function (): void {
        $export = new ValuationExport();
        expect($export)->toBeInstanceOf(ExportableInterface::class);
    });

    it('returns correct headers', function (): void {
        $export = new ValuationExport();
        $headers = $export->getHeaders();

        expect($headers)->toBeArray();
        expect($headers)->toContain('Snapshot Date');
        expect($headers)->toContain('Costing Method');
        expect($headers)->toContain('Total Value');
        expect($headers)->toContain('Average Unit Cost');
        expect($headers)->toContain('Currency');
    });

    it('generates filename with date', function (): void {
        $export = new ValuationExport();
        $filename = $export->getFilename();

        expect($filename)->toStartWith('valuation-');
        expect($filename)->toContain(CarbonImmutable::now()->format('Y-m-d'));
    });

    it('exports valuation snapshots within date range', function (): void {
        $now = CarbonImmutable::now();

        // Snapshot within range
        InventoryValuationSnapshot::factory()->create([
            'location_id' => $this->location->id,
            'costing_method' => CostingMethod::Fifo,
            'snapshot_date' => $now->subDays(5),
            'sku_count' => 10,
            'total_quantity' => 100,
            'total_value_minor' => 10000,
            'average_unit_cost_minor' => 100,
        ]);

        // Snapshot outside range
        InventoryValuationSnapshot::factory()->create([
            'location_id' => $this->location->id,
            'costing_method' => CostingMethod::Fifo,
            'snapshot_date' => $now->subMonths(15),
            'sku_count' => 5,
            'total_quantity' => 50,
            'total_value_minor' => 5000,
        ]);

        $export = new ValuationExport(
            startDate: $now->subMonth(),
            endDate: $now,
        );

        $rows = iterator_to_array($export->getRows());

        expect($rows)->toHaveCount(1);
        expect($rows[0][3])->toBe(10); // SKU Count
    });

    it('converts minor values to major currency', function (): void {
        InventoryValuationSnapshot::factory()->create([
            'location_id' => $this->location->id,
            'costing_method' => CostingMethod::WeightedAverage,
            'snapshot_date' => CarbonImmutable::now(),
            'total_value_minor' => 12345,
            'average_unit_cost_minor' => 678,
            'variance_from_previous_minor' => 100,
        ]);

        $export = new ValuationExport();
        $rows = iterator_to_array($export->getRows());

        expect($rows[0][5])->toBe(123.45); // Total Value (12345 / 100)
        expect($rows[0][6])->toBe(6.78); // Average Unit Cost (678 / 100)
        expect($rows[0][8])->toBe(1); // Variance (100 / 100) - integer division
    });

    it('handles null variance gracefully', function (): void {
        InventoryValuationSnapshot::factory()->create([
            'location_id' => $this->location->id,
            'costing_method' => CostingMethod::Lifo,
            'snapshot_date' => CarbonImmutable::now(),
            'total_value_minor' => 10000,
            'average_unit_cost_minor' => 100,
            'variance_from_previous_minor' => null,
        ]);

        $export = new ValuationExport();
        $rows = iterator_to_array($export->getRows());

        expect($rows[0][8])->toBeNull();
    });

    it('displays costing method label', function (): void {
        InventoryValuationSnapshot::factory()->create([
            'location_id' => $this->location->id,
            'costing_method' => CostingMethod::Fifo,
            'snapshot_date' => CarbonImmutable::now(),
        ]);

        $export = new ValuationExport();
        $rows = iterator_to_array($export->getRows());

        expect($rows[0][1])->toBe(CostingMethod::Fifo->label());
    });

    it('displays location name', function (): void {
        InventoryValuationSnapshot::factory()->create([
            'location_id' => $this->location->id,
            'costing_method' => CostingMethod::WeightedAverage,
            'snapshot_date' => CarbonImmutable::now(),
        ]);

        $export = new ValuationExport();
        $rows = iterator_to_array($export->getRows());

        expect($rows[0][2])->toBe($this->location->name);
    });

    it('uses default date range of 12 months when not specified', function (): void {
        // Snapshot from 6 months ago - should be included
        InventoryValuationSnapshot::factory()->create([
            'location_id' => $this->location->id,
            'costing_method' => CostingMethod::Standard,
            'snapshot_date' => CarbonImmutable::now()->subMonths(6),
        ]);

        // Snapshot from 18 months ago - should be excluded
        InventoryValuationSnapshot::factory()->create([
            'location_id' => $this->location->id,
            'costing_method' => CostingMethod::Standard,
            'snapshot_date' => CarbonImmutable::now()->subMonths(18),
        ]);

        $export = new ValuationExport();
        $rows = iterator_to_array($export->getRows());

        expect($rows)->toHaveCount(1);
    });

    it('includes all snapshot details', function (): void {
        $snapshotDate = CarbonImmutable::now()->startOfDay();

        InventoryValuationSnapshot::factory()->create([
            'location_id' => $this->location->id,
            'costing_method' => CostingMethod::Fifo,
            'snapshot_date' => $snapshotDate,
            'sku_count' => 15,
            'total_quantity' => 500,
            'total_value_minor' => 75000,
            'average_unit_cost_minor' => 150,
            'currency' => 'USD',
            'variance_from_previous_minor' => -500,
        ]);

        $export = new ValuationExport();
        $rows = iterator_to_array($export->getRows());

        expect($rows[0][0])->toBe($snapshotDate->format('Y-m-d')); // Snapshot Date
        expect($rows[0][1])->toBe(CostingMethod::Fifo->label()); // Costing Method
        expect($rows[0][2])->toBe($this->location->name); // Location
        expect($rows[0][3])->toBe(15); // SKU Count
        expect($rows[0][4])->toBe(500); // Total Quantity
        expect($rows[0][5])->toBe(750); // Total Value (75000 / 100)
        expect($rows[0][6])->toBeFloat(); // Average Unit Cost
        expect($rows[0][7])->toBe('USD'); // Currency
        expect($rows[0][8])->toBe(-5); // Variance (-500 / 100)
    });

    it('orders snapshots by date descending', function (): void {
        $now = CarbonImmutable::now();

        InventoryValuationSnapshot::factory()->create([
            'location_id' => $this->location->id,
            'costing_method' => CostingMethod::Fifo,
            'snapshot_date' => $now->subDays(10),
            'sku_count' => 5,
        ]);

        InventoryValuationSnapshot::factory()->create([
            'location_id' => $this->location->id,
            'costing_method' => CostingMethod::Fifo,
            'snapshot_date' => $now->subDays(5),
            'sku_count' => 10,
        ]);

        InventoryValuationSnapshot::factory()->create([
            'location_id' => $this->location->id,
            'costing_method' => CostingMethod::Fifo,
            'snapshot_date' => $now->subDays(15),
            'sku_count' => 3,
        ]);

        $export = new ValuationExport();
        $rows = iterator_to_array($export->getRows());

        expect($rows)->toHaveCount(3);
        expect($rows[0][3])->toBe(10); // Most recent first (5 days ago)
        expect($rows[1][3])->toBe(5); // 10 days ago
        expect($rows[2][3])->toBe(3); // 15 days ago
    });
});
