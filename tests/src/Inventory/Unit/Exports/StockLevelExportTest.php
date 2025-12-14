<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Exports\ExportableInterface;
use AIArmada\Inventory\Exports\StockLevelExport;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->item = InventoryItem::create(['name' => 'Test Product']);
    $this->location = InventoryLocation::factory()->create(['name' => 'Test Location']);
});

describe('StockLevelExport', function (): void {
    it('implements ExportableInterface', function (): void {
        $export = new StockLevelExport();
        expect($export)->toBeInstanceOf(ExportableInterface::class);
    });

    it('returns correct headers', function (): void {
        $export = new StockLevelExport();
        $headers = $export->getHeaders();

        expect($headers)->toBeArray();
        expect($headers)->toContain('SKU Type');
        expect($headers)->toContain('Quantity On Hand');
        expect($headers)->toContain('Reserved');
        expect($headers)->toContain('Available');
        expect($headers)->toContain('Status');
    });

    it('generates filename with date', function (): void {
        $export = new StockLevelExport();
        $filename = $export->getFilename();

        expect($filename)->toStartWith('stock-levels-');
        expect($filename)->toContain(CarbonImmutable::now()->format('Y-m-d'));
    });

    it('exports all stock levels', function (): void {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 10,
        ]);

        $export = new StockLevelExport();
        $rows = iterator_to_array($export->getRows());

        expect($rows)->toHaveCount(1);
        expect($rows[0][3])->toBe(100); // Quantity On Hand
        expect($rows[0][4])->toBe(10); // Reserved
        expect($rows[0][5])->toBe(90); // Available
    });

    it('filters by location', function (): void {
        $location2 = InventoryLocation::factory()->create(['name' => 'Location 2']);

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

        $export = new StockLevelExport([
            'location_id' => $this->location->id,
        ]);

        $rows = iterator_to_array($export->getRows());

        expect($rows)->toHaveCount(1);
        expect($rows[0][2])->toBe($this->location->name);
    });

    it('filters low stock only', function (): void {
        // In stock item
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 100,
            'safety_stock' => 10,
        ]);

        $item2 = InventoryItem::create(['name' => 'Item 2']);
        $location2 = InventoryLocation::factory()->create(['name' => 'Location 2']);
        // Low stock item
        InventoryLevel::factory()->create([
            'inventoryable_type' => $item2->getMorphClass(),
            'inventoryable_id' => $item2->getKey(),
            'location_id' => $location2->id,
            'quantity_on_hand' => 5,
            'safety_stock' => 20,
        ]);

        $export = new StockLevelExport([
            'low_stock_only' => true,
        ]);

        $rows = iterator_to_array($export->getRows());

        expect($rows)->toHaveCount(1);
        expect($rows[0][3])->toBe(5); // Low stock quantity
    });

    it('filters out of stock only', function (): void {
        // In stock item
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 100,
        ]);

        $item2 = InventoryItem::create(['name' => 'Item 2']);
        $location2 = InventoryLocation::factory()->create(['name' => 'Location 2']);
        // Out of stock item
        InventoryLevel::factory()->create([
            'inventoryable_type' => $item2->getMorphClass(),
            'inventoryable_id' => $item2->getKey(),
            'location_id' => $location2->id,
            'quantity_on_hand' => 0,
        ]);

        $export = new StockLevelExport([
            'out_of_stock_only' => true,
        ]);

        $rows = iterator_to_array($export->getRows());

        expect($rows)->toHaveCount(1);
        expect($rows[0][3])->toBe(0); // Out of stock quantity
    });

    it('determines correct status for in stock items', function (): void {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 100,
            'safety_stock' => 10,
            'reorder_point' => 20,
        ]);

        $export = new StockLevelExport();
        $rows = iterator_to_array($export->getRows());

        expect($rows[0][9])->toBe('In Stock');
    });

    it('determines correct status for out of stock items', function (): void {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 0,
        ]);

        $export = new StockLevelExport();
        $rows = iterator_to_array($export->getRows());

        expect($rows[0][9])->toBe('Out of Stock');
    });

    it('determines correct status for low stock items', function (): void {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 5,
            'safety_stock' => 20,
        ]);

        $export = new StockLevelExport();
        $rows = iterator_to_array($export->getRows());

        expect($rows[0][9])->toBe('Low Stock');
    });

    it('includes all stock level details', function (): void {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 10,
            'safety_stock' => 15,
            'max_stock' => 200,
            'reorder_point' => 25,
        ]);

        $export = new StockLevelExport();
        $rows = iterator_to_array($export->getRows());

        expect($rows[0][0])->toBe($this->item->getMorphClass()); // SKU Type
        expect($rows[0][1])->toBe($this->item->getKey()); // SKU ID
        expect($rows[0][2])->toBe($this->location->name); // Location
        expect($rows[0][3])->toBe(100); // Quantity On Hand
        expect($rows[0][4])->toBe(10); // Reserved
        expect($rows[0][5])->toBe(90); // Available
        expect($rows[0][6])->toBe(15); // Safety Stock
        expect($rows[0][7])->toBe(200); // Max Stock
        expect($rows[0][8])->toBe(25); // Reorder Point
    });
});
