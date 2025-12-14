<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Console\CreateValuationSnapshotCommand;
use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryValuationSnapshot;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $this->item = InventoryItem::create(['name' => 'Test Product']);
    $this->location = InventoryLocation::factory()->create();
    InventoryBatch::factory()
        ->forInventoryable($this->item->getMorphClass(), $this->item->getKey())
        ->create([
            'location_id' => $this->location->id,
            'quantity_on_hand' => 100,
            'unit_cost_minor' => 1000,
        ]);
});

describe('CreateValuationSnapshotCommand', function (): void {
    it('creates valuation snapshot with default options', function (): void {
        $exitCode = Artisan::call('inventory:create-valuation-snapshot');

        expect($exitCode)->toBe(CreateValuationSnapshotCommand::SUCCESS);
        expect(InventoryValuationSnapshot::count())->toBe(1);

        $snapshot = InventoryValuationSnapshot::first();
        expect($snapshot->costing_method)->toBe(CostingMethod::Fifo);
    });

    it('creates valuation snapshot with fifo method', function (): void {
        $exitCode = Artisan::call('inventory:create-valuation-snapshot', [
            '--method' => 'fifo',
        ]);

        expect($exitCode)->toBe(CreateValuationSnapshotCommand::SUCCESS);

        $snapshot = InventoryValuationSnapshot::first();
        expect($snapshot->costing_method)->toBe(CostingMethod::Fifo);
    });

    it('creates valuation snapshot with weighted_average method', function (): void {
        $exitCode = Artisan::call('inventory:create-valuation-snapshot', [
            '--method' => 'weighted_average',
        ]);

        expect($exitCode)->toBe(CreateValuationSnapshotCommand::SUCCESS);

        $snapshot = InventoryValuationSnapshot::first();
        expect($snapshot->costing_method)->toBe(CostingMethod::WeightedAverage);
    });

    it('creates valuation snapshot with standard method', function (): void {
        $exitCode = Artisan::call('inventory:create-valuation-snapshot', [
            '--method' => 'standard',
        ]);

        expect($exitCode)->toBe(CreateValuationSnapshotCommand::SUCCESS);

        $snapshot = InventoryValuationSnapshot::first();
        expect($snapshot->costing_method)->toBe(CostingMethod::Standard);
    });

    it('fails with invalid costing method', function (): void {
        $exitCode = Artisan::call('inventory:create-valuation-snapshot', [
            '--method' => 'invalid_method',
        ]);

        expect($exitCode)->toBe(CreateValuationSnapshotCommand::FAILURE);
        expect(InventoryValuationSnapshot::count())->toBe(0);

        $output = Artisan::output();
        expect($output)->toContain('Invalid costing method');
        expect($output)->toContain('Valid methods: fifo, weighted_average, standard');
    });

    it('creates snapshot for specific location', function (): void {
        $exitCode = Artisan::call('inventory:create-valuation-snapshot', [
            '--location' => $this->location->id,
        ]);

        expect($exitCode)->toBe(CreateValuationSnapshotCommand::SUCCESS);

        $snapshot = InventoryValuationSnapshot::first();
        expect($snapshot->location_id)->toBe($this->location->id);
    });

    it('creates snapshot for all locations when no location specified', function (): void {
        $exitCode = Artisan::call('inventory:create-valuation-snapshot');

        expect($exitCode)->toBe(CreateValuationSnapshotCommand::SUCCESS);

        $snapshot = InventoryValuationSnapshot::first();
        expect($snapshot->location_id)->toBeNull();
    });

    it('creates snapshot for specific date', function (): void {
        $specificDate = '2024-01-15';

        $exitCode = Artisan::call('inventory:create-valuation-snapshot', [
            '--date' => $specificDate,
        ]);

        expect($exitCode)->toBe(CreateValuationSnapshotCommand::SUCCESS);

        $snapshot = InventoryValuationSnapshot::first();
        expect($snapshot->snapshot_date->format('Y-m-d'))->toBe($specificDate);
    });

    it('outputs snapshot details on success', function (): void {
        Artisan::call('inventory:create-valuation-snapshot');

        $output = Artisan::output();
        expect($output)->toContain('Valuation snapshot created successfully');
        expect($output)->toContain('Snapshot ID');
        expect($output)->toContain('Date');
        expect($output)->toContain('Costing Method');
        expect($output)->toContain('SKU Count');
        expect($output)->toContain('Total Quantity');
        expect($output)->toContain('Total Value');
    });

    it('shows variance from previous snapshot when available', function (): void {
        // Create first snapshot
        Artisan::call('inventory:create-valuation-snapshot', [
            '--date' => '2024-01-01',
        ]);

        // Add more inventory to create variance
        InventoryBatch::factory()
            ->forInventoryable($this->item->getMorphClass(), $this->item->getKey())
            ->create([
                'location_id' => $this->location->id,
                'quantity_on_hand' => 50,
                'unit_cost_minor' => 1200,
            ]);

        // Create second snapshot
        Artisan::call('inventory:create-valuation-snapshot', [
            '--date' => '2024-01-02',
        ]);

        expect(InventoryValuationSnapshot::count())->toBe(2);
    });

    it('displays costing method label in output', function (): void {
        Artisan::call('inventory:create-valuation-snapshot', [
            '--method' => 'weighted_average',
        ]);

        $output = Artisan::output();
        expect($output)->toContain('Weighted Average');
    });
});
