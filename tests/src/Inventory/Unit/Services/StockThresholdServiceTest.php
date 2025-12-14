<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\AlertStatus;
use AIArmada\Inventory\Events\LowInventoryDetected;
use AIArmada\Inventory\Events\MaxStockExceeded;
use AIArmada\Inventory\Events\OutOfInventory;
use AIArmada\Inventory\Events\SafetyStockBreached;
use AIArmada\Inventory\Events\StockRestored;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\StockThresholdService;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->item = InventoryItem::create(['name' => 'Test Product']);
    $this->location = InventoryLocation::factory()->create();
    $this->service = new StockThresholdService();
});

describe('StockThresholdService', function (): void {
    describe('calculateStatus', function (): void {
        it('returns out of stock when available is zero', function (): void {
            $level = InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
            ]);

            expect($this->service->calculateStatus($level))->toBe(AlertStatus::OutOfStock);
        });

        it('returns safety breached when below safety stock', function (): void {
            $level = InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 5,
                'quantity_reserved' => 0,
                'safety_stock' => 10,
            ]);

            expect($this->service->calculateStatus($level))->toBe(AlertStatus::SafetyBreached);
        });

        it('returns low stock when below reorder point', function (): void {
            $level = InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 15,
                'quantity_reserved' => 0,
                'reorder_point' => 20,
                'safety_stock' => 5,
            ]);

            expect($this->service->calculateStatus($level))->toBe(AlertStatus::LowStock);
        });

        it('returns over stock when above max stock', function (): void {
            $level = InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 150,
                'quantity_reserved' => 0,
                'max_stock' => 100,
            ]);

            expect($this->service->calculateStatus($level))->toBe(AlertStatus::OverStock);
        });

        it('returns none when within normal range', function (): void {
            $level = InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 50,
                'quantity_reserved' => 0,
                'reorder_point' => 20,
                'safety_stock' => 10,
                'max_stock' => 100,
            ]);

            expect($this->service->calculateStatus($level))->toBe(AlertStatus::None);
        });
    });

    describe('evaluateThresholds', function (): void {
        it('updates alert status on level', function (): void {
            $level = InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
                'alert_status' => AlertStatus::None->value,
            ]);

            Event::fake();

            $status = $this->service->evaluateThresholds($level);

            expect($status)->toBe(AlertStatus::OutOfStock);
            expect($level->fresh()->alert_status)->toBe(AlertStatus::OutOfStock->value);
        });

        it('dispatches out of inventory event when status changes to out of stock', function (): void {
            $level = InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
                'alert_status' => AlertStatus::None->value,
            ]);

            Event::fake();

            $this->service->evaluateThresholds($level);

            Event::assertDispatched(OutOfInventory::class);
        });

        it('dispatches low inventory event when status changes to low stock', function (): void {
            $level = InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 5,
                'quantity_reserved' => 0,
                'reorder_point' => 10,
                'safety_stock' => 2,
                'alert_status' => AlertStatus::None->value,
            ]);

            Event::fake();

            $this->service->evaluateThresholds($level);

            Event::assertDispatched(LowInventoryDetected::class);
        });

        it('dispatches safety stock breached event', function (): void {
            $level = InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 5,
                'quantity_reserved' => 0,
                'safety_stock' => 10,
                'alert_status' => AlertStatus::None->value,
            ]);

            Event::fake();

            $this->service->evaluateThresholds($level);

            Event::assertDispatched(SafetyStockBreached::class);
        });

        it('dispatches max stock exceeded event', function (): void {
            $level = InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 150,
                'quantity_reserved' => 0,
                'max_stock' => 100,
                'alert_status' => AlertStatus::None->value,
            ]);

            Event::fake();

            $this->service->evaluateThresholds($level);

            Event::assertDispatched(MaxStockExceeded::class);
        });

        it('dispatches stock restored event when recovering from critical', function (): void {
            $level = InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 50,
                'quantity_reserved' => 0,
                'reorder_point' => 10,
                'alert_status' => AlertStatus::OutOfStock->value,
            ]);

            Event::fake();

            $this->service->evaluateThresholds($level);

            Event::assertDispatched(StockRestored::class);
        });

        it('does not dispatch event when status unchanged', function (): void {
            $level = InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 50,
                'quantity_reserved' => 0,
                'alert_status' => AlertStatus::None->value,
            ]);

            Event::fake();

            $this->service->evaluateThresholds($level);

            Event::assertNotDispatched(OutOfInventory::class);
            Event::assertNotDispatched(LowInventoryDetected::class);
        });
    });

    describe('needsAttention', function (): void {
        it('returns true for out of stock', function (): void {
            $level = InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 0,
            ]);

            expect($this->service->needsAttention($level))->toBeTrue();
        });

        it('returns true for low stock', function (): void {
            $level = InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 5,
                'reorder_point' => 10,
            ]);

            expect($this->service->needsAttention($level))->toBeTrue();
        });

        it('returns false for over stock', function (): void {
            $level = InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 150,
                'max_stock' => 100,
            ]);

            expect($this->service->needsAttention($level))->toBeFalse();
        });

        it('returns false for normal stock', function (): void {
            $level = InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 50,
            ]);

            expect($this->service->needsAttention($level))->toBeFalse();
        });
    });

    describe('getLevelsNeedingReorder', function (): void {
        it('returns levels with alert statuses', function (): void {
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'alert_status' => AlertStatus::LowStock->value,
            ]);

            $levels = $this->service->getLevelsNeedingReorder();

            expect($levels)->toHaveCount(1);
        });
    });

    describe('getSuggestedReorderQuantity', function (): void {
        it('suggests ordering up to max stock', function (): void {
            $level = InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 20,
                'max_stock' => 100,
            ]);

            expect($this->service->getSuggestedReorderQuantity($level))->toBe(80);
        });

        it('suggests 2x reorder point when no max stock', function (): void {
            $level = InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 5,
                'reorder_point' => 20,
                'max_stock' => null,
            ]);

            expect($this->service->getSuggestedReorderQuantity($level))->toBe(35); // (20 * 2) - 5
        });
    });

    describe('wouldExceedMaxStock', function (): void {
        it('returns true when receiving would exceed max', function (): void {
            $level = InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 80,
                'max_stock' => 100,
            ]);

            expect($this->service->wouldExceedMaxStock($level, 30))->toBeTrue();
        });

        it('returns false when within limit', function (): void {
            $level = InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 80,
                'max_stock' => 100,
            ]);

            expect($this->service->wouldExceedMaxStock($level, 15))->toBeFalse();
        });

        it('returns false when no max stock defined', function (): void {
            $level = InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 80,
                'max_stock' => null,
            ]);

            expect($this->service->wouldExceedMaxStock($level, 1000))->toBeFalse();
        });
    });

    describe('getReceivableQuantity', function (): void {
        it('returns desired when no max stock', function (): void {
            $level = InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'max_stock' => null,
            ]);

            expect($this->service->getReceivableQuantity($level, 1000))->toBe(1000);
        });

        it('limits to available space', function (): void {
            $level = InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 80,
                'max_stock' => 100,
            ]);

            expect($this->service->getReceivableQuantity($level, 50))->toBe(20);
        });
    });

    describe('bulkEvaluate', function (): void {
        it('evaluates multiple levels', function (): void {
            Event::fake();

            $level1 = InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 0,
            ]);

            $item2 = InventoryItem::create(['name' => 'Item 2']);
            $location2 = InventoryLocation::factory()->create();
            $level2 = InventoryLevel::factory()->create([
                'inventoryable_type' => $item2->getMorphClass(),
                'inventoryable_id' => $item2->getKey(),
                'location_id' => $location2->id,
                'quantity_on_hand' => 100,
            ]);

            $results = $this->service->bulkEvaluate([$level1, $level2]);

            expect($results)->toHaveCount(2);
            expect($results[$level1->id])->toBe(AlertStatus::OutOfStock);
            expect($results[$level2->id])->toBe(AlertStatus::None);
        });
    });

    describe('getThresholdSummary', function (): void {
        it('returns comprehensive summary', function (): void {
            $level = InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'quantity_on_hand' => 50,
                'quantity_reserved' => 10,
                'reorder_point' => 20,
                'safety_stock' => 10,
                'max_stock' => 100,
            ]);

            $summary = $this->service->getThresholdSummary($level);

            expect($summary)->toHaveKey('available');
            expect($summary)->toHaveKey('on_hand');
            expect($summary)->toHaveKey('reserved');
            expect($summary)->toHaveKey('reorder_point');
            expect($summary)->toHaveKey('safety_stock');
            expect($summary)->toHaveKey('max_stock');
            expect($summary)->toHaveKey('status');
            expect($summary)->toHaveKey('suggested_reorder');
            expect($summary['on_hand'])->toBe(50);
            expect($summary['reserved'])->toBe(10);
        });
    });
});
