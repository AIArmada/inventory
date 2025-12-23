<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Strategies\AllocationContext;
use AIArmada\Inventory\Strategies\AllocationStrategyInterface;
use AIArmada\Inventory\Strategies\NearestLocationStrategy;

beforeEach(function (): void {
    $this->item = InventoryItem::create(['name' => 'Test Product']);
    $this->strategy = new NearestLocationStrategy;
});

describe('NearestLocationStrategy', function (): void {
    it('implements AllocationStrategyInterface', function (): void {
        expect($this->strategy)->toBeInstanceOf(AllocationStrategyInterface::class);
    });

    it('has correct name', function (): void {
        expect($this->strategy->name())->toBe('nearest_location');
    });

    it('has correct label', function (): void {
        expect($this->strategy->label())->toBe('Nearest Location');
    });

    it('has description', function (): void {
        expect($this->strategy->description())->toContain('closest');
    });

    it('allocates from single location', function (): void {
        $location = InventoryLocation::factory()->create([
            'coordinate_x' => 0,
            'coordinate_y' => 0,
        ]);

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
        ]);

        $allocations = $this->strategy->allocate($this->item, 50);

        expect($allocations)->toHaveCount(1);
        expect($allocations[0]['location_id'])->toBe($location->id);
        expect($allocations[0]['quantity'])->toBe(50);
    });

    it('allocates from nearest location first with coordinates', function (): void {
        $nearLocation = InventoryLocation::factory()->create([
            'coordinate_x' => 1,
            'coordinate_y' => 1,
        ]);

        $farLocation = InventoryLocation::factory()->create([
            'coordinate_x' => 10,
            'coordinate_y' => 10,
        ]);

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $nearLocation->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $farLocation->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
        ]);

        $context = new AllocationContext(
            originX: 0,
            originY: 0,
        );

        $allocations = $this->strategy->allocate($this->item, 30, $context);

        expect($allocations)->toHaveCount(1);
        expect($allocations[0]['location_id'])->toBe($nearLocation->id);
    });

    it('allocates from multiple locations when single is insufficient', function (): void {
        $location1 = InventoryLocation::factory()->create([
            'coordinate_x' => 1,
            'coordinate_y' => 1,
        ]);

        $location2 = InventoryLocation::factory()->create([
            'coordinate_x' => 5,
            'coordinate_y' => 5,
        ]);

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location1->id,
            'quantity_on_hand' => 30,
            'quantity_reserved' => 0,
        ]);

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location2->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);

        $context = new AllocationContext(
            originX: 0,
            originY: 0,
        );

        $allocations = $this->strategy->allocate($this->item, 60, $context);

        expect($allocations)->toHaveCount(2);
        expect($allocations[0]['quantity'] + $allocations[1]['quantity'])->toBe(60);
    });

    it('excludes specified locations', function (): void {
        $location1 = InventoryLocation::factory()->create();
        $location2 = InventoryLocation::factory()->create();

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location1->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
        ]);

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location2->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);

        $context = new AllocationContext(
            excludeLocationIds: [$location1->id],
        );

        $allocations = $this->strategy->allocate($this->item, 30, $context);

        expect($allocations)->toHaveCount(1);
        expect($allocations[0]['location_id'])->toBe($location2->id);
    });

    it('respects max locations limit', function (): void {
        $locations = [];
        for ($i = 0; $i < 5; $i++) {
            $locations[] = InventoryLocation::factory()->create([
                'coordinate_x' => $i,
                'coordinate_y' => $i,
            ]);

            InventoryLevel::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $locations[$i]->id,
                'quantity_on_hand' => 10,
                'quantity_reserved' => 0,
            ]);
        }

        $context = new AllocationContext(
            originX: 0,
            originY: 0,
            maxLocations: 2,
        );

        $allocations = $this->strategy->allocate($this->item, 50, $context);

        expect($allocations)->toHaveCount(2);
    });

    it('checks if can fulfill quantity', function (): void {
        $location = InventoryLocation::factory()->create();

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 10,
        ]);

        expect($this->strategy->canFulfill($this->item, 80))->toBeTrue();
        expect($this->strategy->canFulfill($this->item, 100))->toBeFalse();
    });

    it('checks can fulfill excludes specified locations', function (): void {
        $location1 = InventoryLocation::factory()->create();
        $location2 = InventoryLocation::factory()->create();

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location1->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
        ]);

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location2->id,
            'quantity_on_hand' => 20,
            'quantity_reserved' => 0,
        ]);

        $context = new AllocationContext(
            excludeLocationIds: [$location1->id],
        );

        expect($this->strategy->canFulfill($this->item, 100, $context))->toBeFalse();
        expect($this->strategy->canFulfill($this->item, 20, $context))->toBeTrue();
    });

    it('gets recommended order', function (): void {
        $location1 = InventoryLocation::factory()->create([
            'coordinate_x' => 10,
            'coordinate_y' => 10,
        ]);

        $location2 = InventoryLocation::factory()->create([
            'coordinate_x' => 1,
            'coordinate_y' => 1,
        ]);

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location1->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location2->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);

        $context = new AllocationContext(
            originX: 0,
            originY: 0,
        );

        $order = $this->strategy->getRecommendedOrder($this->item, $context);

        expect($order)->toHaveCount(2);
        expect($order->first()->id)->toBe($location2->id); // Nearest first
    });

    it('gets locations by distance', function (): void {
        $near = InventoryLocation::factory()->create([
            'name' => 'Near',
            'is_active' => true,
            'coordinate_x' => 1,
            'coordinate_y' => 1,
        ]);

        $far = InventoryLocation::factory()->create([
            'name' => 'Far',
            'is_active' => true,
            'coordinate_x' => 10,
            'coordinate_y' => 10,
        ]);

        $locations = $this->strategy->getLocationsByDistance(0, 0);

        expect($locations->first()->id)->toBe($near->id);
    });

    it('calculates distance correctly with z coordinate', function (): void {
        $location1 = InventoryLocation::factory()->create([
            'coordinate_x' => 3,
            'coordinate_y' => 0,
            'coordinate_z' => 4,
        ]);

        $location2 = InventoryLocation::factory()->create([
            'coordinate_x' => 10,
            'coordinate_y' => 0,
            'coordinate_z' => 0,
        ]);

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location1->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location2->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);

        $context = new AllocationContext(
            originX: 0,
            originY: 0,
            originZ: 0,
        );

        $allocations = $this->strategy->allocate($this->item, 30, $context);

        // Distance to location1: sqrt(3^2 + 0^2 + 4^2) = 5
        // Distance to location2: sqrt(10^2 + 0^2 + 0^2) = 10
        expect($allocations[0]['location_id'])->toBe($location1->id);
        expect($allocations[0]['distance'])->toBe(5.0);
    });

    it('returns allocations with distance in result', function (): void {
        $location = InventoryLocation::factory()->create([
            'coordinate_x' => 3,
            'coordinate_y' => 4,
        ]);

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);

        $context = new AllocationContext(
            originX: 0,
            originY: 0,
        );

        $allocations = $this->strategy->allocate($this->item, 30, $context);

        expect($allocations[0])->toHaveKey('distance');
        expect($allocations[0]['distance'])->toBe(5.0); // sqrt(3^2 + 4^2) = 5
    });

    it('falls back to pick sequence when no origin coordinates', function (): void {
        $location1 = InventoryLocation::factory()->create([
            'pick_sequence' => 2,
        ]);

        $location2 = InventoryLocation::factory()->create([
            'pick_sequence' => 1,
        ]);

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location1->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location2->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);

        $allocations = $this->strategy->allocate($this->item, 30);

        expect($allocations[0]['location_id'])->toBe($location2->id);
    });

    it('handles locations without coordinates', function (): void {
        $locationWithCoords = InventoryLocation::factory()->create([
            'coordinate_x' => 1,
            'coordinate_y' => 1,
        ]);

        $locationWithoutCoords = InventoryLocation::factory()->create([
            'coordinate_x' => null,
            'coordinate_y' => null,
        ]);

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $locationWithCoords->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $locationWithoutCoords->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);

        $context = new AllocationContext(
            originX: 0,
            originY: 0,
        );

        $allocations = $this->strategy->allocate($this->item, 30, $context);

        // Location with coordinates should come first
        expect($allocations[0]['location_id'])->toBe($locationWithCoords->id);
    });

    it('does not allow SQL injection via preferLocationIds', function (): void {
        $location = InventoryLocation::factory()->create([
            'coordinate_x' => 0,
            'coordinate_y' => 0,
        ]);

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);

        $context = new AllocationContext(
            preferLocationIds: [$location->id, "x' OR 1=1 --"],
        );

        $allocations = $this->strategy->allocate($this->item, 1, $context);

        expect($allocations)->toHaveCount(1);
        expect($allocations[0]['location_id'])->toBe($location->id);
        expect($allocations[0]['quantity'])->toBe(1);
    });

    it('returns empty allocations when no inventory available', function (): void {
        $allocations = $this->strategy->allocate($this->item, 50);

        expect($allocations)->toBeEmpty();
    });
});
