<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\AllocationStrategy;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;

beforeEach(function (): void {
    $this->item = InventoryItem::create(['name' => 'Test Product']);
    $this->location = InventoryLocation::factory()->create();
    $this->level = InventoryLevel::factory()->create([
        'inventoryable_type' => $this->item->getMorphClass(),
        'inventoryable_id' => $this->item->getKey(),
        'location_id' => $this->location->id,
        'quantity_on_hand' => 100,
        'quantity_reserved' => 10,
    ]);
});

describe('HasInventory trait', function (): void {
    describe('inventoryLevels relationship', function (): void {
        it('returns morph many relationship', function (): void {
            expect($this->item->inventoryLevels())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class);
        });

        it('returns inventory levels for the model', function (): void {
            $levels = $this->item->inventoryLevels;

            expect($levels)->toHaveCount(1);
            expect($levels->first()->id)->toBe($this->level->id);
        });

        it('orders by quantity_on_hand descending', function (): void {
            $location2 = InventoryLocation::factory()->create();
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $location2->id,
                'quantity_on_hand' => 200,
            ]);

            $levels = $this->item->inventoryLevels;

            expect($levels->first()->quantity_on_hand)->toBe(200);
            expect($levels->last()->quantity_on_hand)->toBe(100);
        });
    });

    describe('inventoryMovements relationship', function (): void {
        it('returns morph many relationship', function (): void {
            expect($this->item->inventoryMovements())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class);
        });

        it('returns movements ordered by occurred_at desc', function (): void {
            $movement1 = InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => null,
                'to_location_id' => $this->location->id,
                'occurred_at' => now()->subHour(),
            ]);

            $movement2 = InventoryMovement::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'from_location_id' => null,
                'to_location_id' => $this->location->id,
                'occurred_at' => now(),
            ]);

            $movements = $this->item->inventoryMovements;

            expect($movements->first()->id)->toBe($movement2->id);
        });
    });

    describe('inventoryAllocations relationship', function (): void {
        it('returns morph many relationship', function (): void {
            expect($this->item->inventoryAllocations())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class);
        });

        it('returns allocations for the model', function (): void {
            $allocation = InventoryAllocation::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
            ]);

            $allocations = $this->item->inventoryAllocations;

            expect($allocations)->toHaveCount(1);
            expect($allocations->first()->id)->toBe($allocation->id);
        });
    });

    describe('getTotalOnHand', function (): void {
        it('returns total quantity on hand', function (): void {
            expect($this->item->getTotalOnHand())->toBe(100);
        });

        it('sums across multiple locations', function (): void {
            $location2 = InventoryLocation::factory()->create();
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $location2->id,
                'quantity_on_hand' => 50,
            ]);

            expect($this->item->getTotalOnHand())->toBe(150);
        });
    });

    describe('getTotalAvailable', function (): void {
        it('returns available quantity minus reserved', function (): void {
            expect($this->item->getTotalAvailable())->toBe(90); // 100 - 10 reserved
        });
    });

    describe('hasInventory', function (): void {
        it('returns true when sufficient inventory', function (): void {
            expect($this->item->hasInventory(50))->toBeTrue();
        });

        it('returns false when insufficient inventory', function (): void {
            expect($this->item->hasInventory(200))->toBeFalse();
        });

        it('defaults to checking for 1', function (): void {
            expect($this->item->hasInventory())->toBeTrue();
        });
    });

    describe('getInventoryAtLocation', function (): void {
        it('returns level for specific location', function (): void {
            $level = $this->item->getInventoryAtLocation($this->location->id);

            expect($level)->not->toBeNull();
            expect($level->id)->toBe($this->level->id);
        });

        it('returns null for nonexistent location', function (): void {
            expect($this->item->getInventoryAtLocation('nonexistent'))->toBeNull();
        });
    });

    describe('getAvailability', function (): void {
        it('returns availability by location', function (): void {
            $availability = $this->item->getAvailability();

            expect($availability)->toBeArray();
            expect($availability[$this->location->id])->toBe(90);
        });
    });

    describe('getAllocationStrategy', function (): void {
        it('returns null by default', function (): void {
            expect($this->item->getAllocationStrategy())->toBeNull();
        });
    });

    describe('receive', function (): void {
        it('creates a movement for receiving inventory', function (): void {
            $movement = $this->item->receive(
                $this->location->id,
                25,
                'purchase',
                'Restocking',
                'user-123'
            );

            expect($movement)->toBeInstanceOf(InventoryMovement::class);
            expect($movement->quantity)->toBe(25);
        });
    });

    describe('ship', function (): void {
        it('creates a movement for shipping inventory', function (): void {
            $movement = $this->item->ship(
                $this->location->id,
                10,
                'sale',
                'order-123',
                'Shipping to customer',
                'user-123'
            );

            expect($movement)->toBeInstanceOf(InventoryMovement::class);
            expect(abs($movement->quantity))->toBe(10);
        });
    });

    describe('transfer', function (): void {
        it('creates transfer movement', function (): void {
            $location2 = InventoryLocation::factory()->create();
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $location2->id,
                'quantity_on_hand' => 0,
            ]);

            $movement = $this->item->transfer(
                $this->location->id,
                $location2->id,
                20,
                'Moving to new warehouse',
                'user-123'
            );

            expect($movement)->toBeInstanceOf(InventoryMovement::class);
        });
    });

    describe('adjustInventory', function (): void {
        it('creates adjustment movement', function (): void {
            $movement = $this->item->adjustInventory(
                $this->location->id,
                95,
                'count',
                'Physical count adjustment',
                'user-123'
            );

            expect($movement)->toBeInstanceOf(InventoryMovement::class);
        });
    });

    describe('allocate', function (): void {
        it('creates allocations for cart', function (): void {
            $allocations = $this->item->allocate(10, 'cart-123', 30);

            expect($allocations)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
            expect($allocations->sum('quantity'))->toBe(10);
        });
    });

    describe('release', function (): void {
        it('releases allocations for cart', function (): void {
            $this->item->allocate(10, 'cart-123', 30);

            $released = $this->item->release('cart-123');

            expect($released)->toBe(10);
        });

        it('returns 0 when no allocations', function (): void {
            expect($this->item->release('nonexistent-cart'))->toBe(0);
        });
    });

    describe('getAllocations', function (): void {
        it('returns allocations for cart', function (): void {
            $this->item->allocate(10, 'cart-456', 30);

            $allocations = $this->item->getAllocations('cart-456');

            expect($allocations)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
            expect($allocations->sum('quantity'))->toBe(10);
        });

        it('returns empty collection when no allocations', function (): void {
            $allocations = $this->item->getAllocations('no-cart');

            expect($allocations)->toBeEmpty();
        });
    });

    describe('getInventoryHistory', function (): void {
        it('returns movement history', function (): void {
            $this->item->receive($this->location->id, 10, 'test');
            $this->item->ship($this->location->id, 5, 'test');

            $history = $this->item->getInventoryHistory();

            expect($history)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
            expect($history->count())->toBeGreaterThanOrEqual(2);
        });

        it('respects limit parameter', function (): void {
            for ($i = 0; $i < 5; $i++) {
                $this->item->receive($this->location->id, 1, 'test');
            }

            $history = $this->item->getInventoryHistory(2);

            expect($history)->toHaveCount(2);
        });
    });

    describe('isLowInventory', function (): void {
        it('returns false when above threshold', function (): void {
            expect($this->item->isLowInventory(50))->toBeFalse();
        });

        it('returns true when below threshold', function (): void {
            $this->level->update(['quantity_on_hand' => 5]);

            expect($this->item->isLowInventory(10))->toBeTrue();
        });

        it('uses default threshold from config', function (): void {
            config(['inventory.default_reorder_point' => 200]);

            expect($this->item->isLowInventory())->toBeTrue();
        });
    });

    describe('receiveAtDefault', function (): void {
        it('creates a movement for receiving inventory at default location', function (): void {
            $defaultLocation = InventoryLocation::getOrCreateDefault();
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $defaultLocation->id,
                'quantity_on_hand' => 0,
            ]);

            $movement = $this->item->receiveAtDefault(
                50,
                'restocking',
                'Initial stock',
                'user-123'
            );

            expect($movement)->toBeInstanceOf(InventoryMovement::class);
            expect($movement->quantity)->toBe(50);
            expect($movement->to_location_id)->toBe($defaultLocation->id);
        });
    });

    describe('shipFromDefault', function (): void {
        it('creates a movement for shipping from default location', function (): void {
            $defaultLocation = InventoryLocation::getOrCreateDefault();
            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $defaultLocation->id,
                'quantity_on_hand' => 100,
            ]);

            $movement = $this->item->shipFromDefault(
                25,
                'sale',
                'ORDER-123',
                'Shipped to customer',
                'user-123'
            );

            expect($movement)->toBeInstanceOf(InventoryMovement::class);
            expect(abs($movement->quantity))->toBe(25);
            expect($movement->from_location_id)->toBe($defaultLocation->id);
        });
    });
});
