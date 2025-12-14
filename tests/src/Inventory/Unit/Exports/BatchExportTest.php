<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\BatchStatus;
use AIArmada\Inventory\Exports\BatchExport;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryLocation;

beforeEach(function (): void {
    $this->location = InventoryLocation::factory()->create(['name' => 'Warehouse A']);
    $this->product = InventoryItem::create(['name' => 'Test Product']);
});

describe('getHeaders', function (): void {
    it('returns correct headers', function (): void {
        $export = new BatchExport();

        $headers = $export->getHeaders();

        expect($headers)->toContain('Batch Number');
        expect($headers)->toContain('SKU Type');
        expect($headers)->toContain('Location');
        expect($headers)->toContain('Quantity');
        expect($headers)->toContain('Status');
        expect($headers)->toContain('Expiry Date');
        expect($headers)->toContain('Days Until Expiry');
    });
});

describe('getRows', function (): void {
    it('returns all batches when no filters', function (): void {
        InventoryBatch::factory()->count(3)->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
        ]);

        $export = new BatchExport();
        $rows = iterator_to_array($export->getRows());

        expect($rows)->toHaveCount(3);
    });

    it('filters by status', function (): void {
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'status' => BatchStatus::Active,
        ]);

        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'status' => BatchStatus::Quarantined,
        ]);

        $export = new BatchExport(status: BatchStatus::Active->value);
        $rows = iterator_to_array($export->getRows());

        expect($rows)->toHaveCount(1);
    });

    it('filters expiring batches', function (): void {
        // Expiring within 30 days
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'expires_at' => now()->addDays(15),
        ]);

        // Expiring in 60 days (outside window)
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'expires_at' => now()->addDays(60),
        ]);

        // Already expired
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'expires_at' => now()->subDays(5),
        ]);

        $export = new BatchExport(expiringOnly: true, expiringWithinDays: 30);
        $rows = iterator_to_array($export->getRows());

        expect($rows)->toHaveCount(1);
    });

    it('includes location name', function (): void {
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
        ]);

        $export = new BatchExport();
        $rows = iterator_to_array($export->getRows());

        expect($rows[0][3])->toBe('Warehouse A');
    });

    it('calculates days until expiry', function (): void {
        $expiryDate = now()->addDays(10);
        InventoryBatch::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'expires_at' => $expiryDate,
        ]);

        $export = new BatchExport();
        $rows = iterator_to_array($export->getRows());

        // Days until expiry is in column index 10
        expect($rows[0][10])->toBeBetween(9, 11);
    });
});

describe('getFilename', function (): void {
    it('generates filename with date', function (): void {
        $export = new BatchExport();
        $filename = $export->getFilename();

        expect($filename)->toStartWith('batches-');
        expect($filename)->toContain(now()->format('Y-m-d'));
    });

    it('includes expiring suffix when filtering expiring', function (): void {
        $export = new BatchExport(expiringOnly: true);
        $filename = $export->getFilename();

        expect($filename)->toStartWith('batches-expiring-');
    });
});
