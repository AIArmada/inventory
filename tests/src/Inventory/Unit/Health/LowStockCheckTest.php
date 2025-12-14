<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Health\LowStockCheck;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use Spatie\Health\Enums\Status;

beforeEach(function (): void {
    $this->item = InventoryItem::create(['name' => 'Test Product']);
    $this->location = InventoryLocation::factory()->create();
    $this->check = new LowStockCheck();
});

describe('LowStockCheck', function (): void {
    it('returns success when all inventory levels are healthy', function (): void {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 100,
        ]);

        $result = $this->check->run();

        expect($result->status->equals(Status::ok()))->toBeTrue();
        expect($result->notificationMessage)->toContain('healthy');
    });

    it('returns warning when items have low stock', function (): void {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 5,
        ]);

        $result = $this->check->run();

        expect($result->status->equals(Status::warning()))->toBeTrue();
        expect($result->notificationMessage)->toContain('low stock');
    });

    it('returns warning when items are out of stock by default', function (): void {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 0,
        ]);

        $result = $this->check->run();

        expect($result->status->equals(Status::warning()))->toBeTrue();
        expect($result->notificationMessage)->toContain('out of stock');
    });

    it('returns failure when items are out of stock and failOnLowStock is true', function (): void {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 0,
        ]);

        $result = $this->check->failOnLowStock()->run();

        expect($result->status->equals(Status::failed()))->toBeTrue();
    });

    it('respects custom threshold', function (): void {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 15,
        ]);

        // With default threshold (10), should be OK
        $result = $this->check->run();
        expect($result->status->equals(Status::ok()))->toBeTrue();

        // With higher threshold (20), should warn - need fresh check instance
        $check2 = new LowStockCheck();
        $result = $check2->threshold(20)->run();
        expect($result->status->equals(Status::warning()))->toBeTrue();
    });

    it('reports both out of stock and low stock counts', function (): void {
        $item2 = InventoryItem::create(['name' => 'Item 2']);

        // Out of stock item
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 0,
        ]);

        // Low stock item
        InventoryLevel::factory()->create([
            'inventoryable_type' => $item2->getMorphClass(),
            'inventoryable_id' => $item2->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 5,
        ]);

        $result = $this->check->run();

        expect($result->notificationMessage)->toContain('out of stock');
        expect($result->notificationMessage)->toContain('low stock');
    });

    it('has default name', function (): void {
        expect($this->check->name)->toBe('Low Stock Alert');
    });

    it('allows method chaining', function (): void {
        $result = $this->check
            ->threshold(5)
            ->failOnLowStock(true);

        expect($result)->toBeInstanceOf(LowStockCheck::class);
    });

    it('returns success when no inventory exists', function (): void {
        $result = $this->check->run();

        expect($result->status->equals(Status::ok()))->toBeTrue();
    });
});
