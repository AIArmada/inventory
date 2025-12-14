<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\DemandPeriodType;
use AIArmada\Inventory\Models\InventoryDemandHistory;
use AIArmada\Inventory\Models\InventoryLocation;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->item = InventoryItem::create(['name' => 'Demand Product']);
    $this->location = InventoryLocation::factory()->create();
});

describe('InventoryDemandHistory', function (): void {
    describe('relationships', function (): void {
        it('has inventoryable morph to relation', function (): void {
            $history = InventoryDemandHistory::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'period_date' => now(),
                'period_type' => DemandPeriodType::Daily,
                'quantity_demanded' => 100,
                'quantity_fulfilled' => 80,
                'quantity_lost' => 20,
                'order_count' => 10,
            ]);

            expect($history->inventoryable)->not->toBeNull();
            expect($history->inventoryable->id)->toBe($this->item->id);
        });

        it('belongs to location', function (): void {
            $history = InventoryDemandHistory::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'period_date' => now(),
                'period_type' => DemandPeriodType::Daily,
                'quantity_demanded' => 50,
                'quantity_fulfilled' => 50,
                'quantity_lost' => 0,
                'order_count' => 5,
            ]);

            expect($history->location)->not->toBeNull();
            expect($history->location->id)->toBe($this->location->id);
        });
    });

    describe('scopes', function (): void {
        beforeEach(function (): void {
            InventoryDemandHistory::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'period_date' => now(),
                'period_type' => DemandPeriodType::Daily,
                'quantity_demanded' => 10,
                'quantity_fulfilled' => 10,
                'quantity_lost' => 0,
                'order_count' => 1,
            ]);

            InventoryDemandHistory::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'period_date' => now()->startOfWeek(),
                'period_type' => DemandPeriodType::Weekly,
                'quantity_demanded' => 70,
                'quantity_fulfilled' => 65,
                'quantity_lost' => 5,
                'order_count' => 7,
            ]);

            InventoryDemandHistory::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'period_date' => now()->startOfMonth(),
                'period_type' => DemandPeriodType::Monthly,
                'quantity_demanded' => 300,
                'quantity_fulfilled' => 280,
                'quantity_lost' => 20,
                'order_count' => 30,
            ]);
        });

        it('filters by model', function (): void {
            $otherItem = InventoryItem::create(['name' => 'Other Product']);
            InventoryDemandHistory::create([
                'inventoryable_type' => $otherItem->getMorphClass(),
                'inventoryable_id' => $otherItem->getKey(),
                'period_date' => now(),
                'period_type' => DemandPeriodType::Daily,
                'quantity_demanded' => 5,
                'quantity_fulfilled' => 5,
                'quantity_lost' => 0,
                'order_count' => 1,
            ]);

            $forItem = InventoryDemandHistory::forModel($this->item)->get();

            expect($forItem)->toHaveCount(3);
        });

        it('filters daily records', function (): void {
            $daily = InventoryDemandHistory::daily()->get();

            expect($daily)->toHaveCount(1);
            expect($daily->first()->period_type)->toBe(DemandPeriodType::Daily);
        });

        it('filters weekly records', function (): void {
            $weekly = InventoryDemandHistory::weekly()->get();

            expect($weekly)->toHaveCount(1);
            expect($weekly->first()->period_type)->toBe(DemandPeriodType::Weekly);
        });

        it('filters monthly records', function (): void {
            $monthly = InventoryDemandHistory::monthly()->get();

            expect($monthly)->toHaveCount(1);
            expect($monthly->first()->period_type)->toBe(DemandPeriodType::Monthly);
        });

        it('filters between dates', function (): void {
            $result = InventoryDemandHistory::betweenDates(
                now()->subDays(3),
                now()->addDays(1)
            )->get();

            expect($result->count())->toBeGreaterThan(0);
        });

        it('filters last days', function (): void {
            $result = InventoryDemandHistory::lastDays(7)->get();

            expect($result->count())->toBeGreaterThan(0);
        });
    });

    describe('fulfillmentRate', function (): void {
        it('calculates fulfillment rate correctly', function (): void {
            $history = InventoryDemandHistory::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'period_date' => now(),
                'period_type' => DemandPeriodType::Daily,
                'quantity_demanded' => 100,
                'quantity_fulfilled' => 80,
                'quantity_lost' => 20,
                'order_count' => 10,
            ]);

            expect($history->fulfillmentRate())->toBe(80.0);
        });

        it('returns 100 when no demand', function (): void {
            $history = InventoryDemandHistory::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'period_date' => now(),
                'period_type' => DemandPeriodType::Daily,
                'quantity_demanded' => 0,
                'quantity_fulfilled' => 0,
                'quantity_lost' => 0,
                'order_count' => 0,
            ]);

            expect($history->fulfillmentRate())->toBe(100.0);
        });
    });

    describe('lostSalesRate', function (): void {
        it('calculates lost sales rate correctly', function (): void {
            $history = InventoryDemandHistory::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'period_date' => now(),
                'period_type' => DemandPeriodType::Daily,
                'quantity_demanded' => 100,
                'quantity_fulfilled' => 75,
                'quantity_lost' => 25,
                'order_count' => 10,
            ]);

            expect($history->lostSalesRate())->toBe(25.0);
        });

        it('returns 0 when no demand', function (): void {
            $history = InventoryDemandHistory::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'period_date' => now(),
                'period_type' => DemandPeriodType::Daily,
                'quantity_demanded' => 0,
                'quantity_fulfilled' => 0,
                'quantity_lost' => 0,
                'order_count' => 0,
            ]);

            expect($history->lostSalesRate())->toBe(0.0);
        });
    });

    describe('averageOrderSize', function (): void {
        it('calculates average order size correctly', function (): void {
            $history = InventoryDemandHistory::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'period_date' => now(),
                'period_type' => DemandPeriodType::Daily,
                'quantity_demanded' => 100,
                'quantity_fulfilled' => 100,
                'quantity_lost' => 0,
                'order_count' => 20,
            ]);

            expect($history->averageOrderSize())->toBe(5.0);
        });

        it('returns 0 when no orders', function (): void {
            $history = InventoryDemandHistory::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'period_date' => now(),
                'period_type' => DemandPeriodType::Daily,
                'quantity_demanded' => 0,
                'quantity_fulfilled' => 0,
                'quantity_lost' => 0,
                'order_count' => 0,
            ]);

            expect($history->averageOrderSize())->toBe(0.0);
        });
    });

    describe('casts', function (): void {
        it('casts period_type to enum', function (): void {
            $history = InventoryDemandHistory::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'period_date' => now(),
                'period_type' => DemandPeriodType::Weekly->value,
                'quantity_demanded' => 50,
                'quantity_fulfilled' => 50,
                'quantity_lost' => 0,
                'order_count' => 5,
            ]);

            expect($history->period_type)->toBe(DemandPeriodType::Weekly);
        });

        it('casts period_date to date', function (): void {
            $history = InventoryDemandHistory::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'period_date' => '2024-06-15',
                'period_type' => DemandPeriodType::Daily,
                'quantity_demanded' => 10,
                'quantity_fulfilled' => 10,
                'quantity_lost' => 0,
                'order_count' => 1,
            ]);

            expect($history->period_date)->toBeInstanceOf(Carbon::class);
        });

        it('casts metadata to array', function (): void {
            $history = InventoryDemandHistory::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'period_date' => now(),
                'period_type' => DemandPeriodType::Daily,
                'quantity_demanded' => 10,
                'quantity_fulfilled' => 10,
                'quantity_lost' => 0,
                'order_count' => 1,
                'metadata' => ['source' => 'web', 'channel' => 'retail'],
            ]);

            expect($history->metadata)->toBeArray();
            expect($history->metadata['source'])->toBe('web');
        });
    });
});
