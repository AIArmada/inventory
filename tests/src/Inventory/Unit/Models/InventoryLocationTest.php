<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Enums\TemperatureZone;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;

class InventoryLocationTest extends InventoryTestCase
{
    public function test_can_create_inventory_location(): void
    {
        $location = InventoryLocation::factory()->create([
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'is_active' => true,
        ]);

        expect($location)->toBeInstanceOf(InventoryLocation::class);
        expect($location->name)->toBe('Main Warehouse');
        expect($location->code)->toBe('MAIN');
        expect($location->is_active)->toBeTrue();
    }

    public function test_can_have_parent_location(): void
    {
        $parent = InventoryLocation::factory()->create([
            'name' => 'Main Building',
        ]);

        $child = InventoryLocation::factory()->create([
            'name' => 'Section A',
            'parent_id' => $parent->id,
        ]);

        expect($child->parent)->not->toBeNull();
        expect($child->parent->id)->toBe($parent->id);
    }

    public function test_children_relationship(): void
    {
        $parent = InventoryLocation::factory()->create([
            'name' => 'Main Building',
        ]);

        InventoryLocation::factory()->create([
            'name' => 'Section A',
            'parent_id' => $parent->id,
        ]);
        InventoryLocation::factory()->create([
            'name' => 'Section B',
            'parent_id' => $parent->id,
        ]);

        expect($parent->children)->toHaveCount(2);
    }

    public function test_inventory_levels_relationship(): void
    {
        $location = InventoryLocation::factory()->create();
        $item = InventoryItem::create(['name' => 'Test Item']);

        InventoryLevel::factory()->create([
            'location_id' => $location->id,
            'inventoryable_type' => $item->getMorphClass(),
            'inventoryable_id' => $item->getKey(),
        ]);

        expect($location->inventoryLevels)->toHaveCount(1);
    }

    public function test_movements_to_relationship(): void
    {
        $location = InventoryLocation::factory()->create();
        $fromLocation = InventoryLocation::factory()->create();
        $item = InventoryItem::create(['name' => 'Test Item']);

        InventoryMovement::factory()->create([
            'to_location_id' => $location->id,
            'from_location_id' => $fromLocation->id,
            'inventoryable_type' => $item->getMorphClass(),
            'inventoryable_id' => $item->getKey(),
        ]);

        expect($location->movementsTo)->toHaveCount(1);
    }

    public function test_movements_from_relationship(): void
    {
        $location = InventoryLocation::factory()->create();
        $toLocation = InventoryLocation::factory()->create();
        $item = InventoryItem::create(['name' => 'Test Item']);

        InventoryMovement::factory()->create([
            'from_location_id' => $location->id,
            'to_location_id' => $toLocation->id,
            'inventoryable_type' => $item->getMorphClass(),
            'inventoryable_id' => $item->getKey(),
        ]);

        expect($location->movementsFrom)->toHaveCount(1);
    }

    public function test_scope_active(): void
    {
        InventoryLocation::factory()->create(['is_active' => true]);
        InventoryLocation::factory()->create(['is_active' => true]);
        InventoryLocation::factory()->create(['is_active' => false]);

        $activeLocations = InventoryLocation::active()->get();

        expect($activeLocations)->toHaveCount(2);
    }

    public function test_is_default(): void
    {
        $defaultLocation = InventoryLocation::getOrCreateDefault();
        $regularLocation = InventoryLocation::factory()->create(['code' => 'REGULAR']);

        expect($defaultLocation->isDefault())->toBeTrue();
        expect($regularLocation->isDefault())->toBeFalse();
    }

    public function test_is_root_returns_true_for_top_level(): void
    {
        $location = InventoryLocation::factory()->create(['parent_id' => null]);

        expect($location->isRoot())->toBeTrue();
    }

    public function test_is_root_returns_false_for_children(): void
    {
        $parent = InventoryLocation::factory()->create();
        $child = InventoryLocation::factory()->create(['parent_id' => $parent->id]);

        expect($child->isRoot())->toBeFalse();
    }

    public function test_is_leaf_returns_true_for_childless(): void
    {
        $location = InventoryLocation::factory()->create();

        expect($location->isLeaf())->toBeTrue();
    }

    public function test_is_leaf_returns_false_for_parents(): void
    {
        $parent = InventoryLocation::factory()->create();
        InventoryLocation::factory()->create(['parent_id' => $parent->id]);

        expect($parent->isLeaf())->toBeFalse();
    }

    public function test_has_no_children_for_empty(): void
    {
        $location = InventoryLocation::factory()->create();

        expect($location->children)->toHaveCount(0);
    }

    public function test_has_children_when_children_exist(): void
    {
        $parent = InventoryLocation::factory()->create();
        InventoryLocation::factory()->create(['parent_id' => $parent->id]);

        expect($parent->children)->toHaveCount(1);
    }

    public function test_deleting_location_cascades_to_inventory_levels(): void
    {
        $location = InventoryLocation::factory()->create();
        $item = InventoryItem::create(['name' => 'Test Item']);

        $level = InventoryLevel::factory()->create([
            'location_id' => $location->id,
            'inventoryable_type' => $item->getMorphClass(),
            'inventoryable_id' => $item->getKey(),
        ]);
        $levelId = $level->id;

        $location->delete();

        expect(InventoryLevel::find($levelId))->toBeNull();
    }

    public function test_get_descendants(): void
    {
        $root = InventoryLocation::factory()->create(['name' => 'Root']);
        $child1 = InventoryLocation::factory()->create(['name' => 'Child 1', 'parent_id' => $root->id]);
        $child2 = InventoryLocation::factory()->create(['name' => 'Child 2', 'parent_id' => $root->id]);
        $grandchild = InventoryLocation::factory()->create(['name' => 'Grandchild', 'parent_id' => $child1->id]);

        $descendants = $root->descendants;

        expect($descendants)->toHaveCount(3);
        expect($descendants->pluck('id')->toArray())->toContain($child1->id, $child2->id, $grandchild->id);
    }

    public function test_get_ancestors(): void
    {
        $root = InventoryLocation::factory()->create(['name' => 'Root']);
        $child = InventoryLocation::factory()->create(['name' => 'Child', 'parent_id' => $root->id]);
        $grandchild = InventoryLocation::factory()->create(['name' => 'Grandchild', 'parent_id' => $child->id]);

        $ancestors = $grandchild->ancestors;

        expect($ancestors)->toHaveCount(2);
    }

    public function test_get_breadcrumbs(): void
    {
        $root = InventoryLocation::factory()->create(['name' => 'Root']);
        $child = InventoryLocation::factory()->create(['name' => 'Child', 'parent_id' => $root->id]);
        $grandchild = InventoryLocation::factory()->create(['name' => 'Grandchild', 'parent_id' => $child->id]);

        $breadcrumbs = $grandchild->getBreadcrumbs();

        expect($breadcrumbs)->toHaveCount(3);
        expect($breadcrumbs->last()->id)->toBe($grandchild->id);
    }

    public function test_depth_property(): void
    {
        $root = InventoryLocation::factory()->create();
        $child = InventoryLocation::factory()->create(['parent_id' => $root->id]);
        $grandchild = InventoryLocation::factory()->create(['parent_id' => $child->id]);

        expect($root->depth)->toBe(0);
        expect($child->depth)->toBe(1);
        expect($grandchild->depth)->toBe(2);
    }

    public function test_scope_by_priority(): void
    {
        $low = InventoryLocation::factory()->create(['priority' => 10]);
        $high = InventoryLocation::factory()->create(['priority' => 100]);
        $mid = InventoryLocation::factory()->create(['priority' => 50]);

        $sorted = InventoryLocation::byPriority()->get();

        expect($sorted->first()->id)->toBe($high->id);
        expect($sorted->last()->id)->toBe($low->id);
    }

    public function test_get_or_create_default(): void
    {
        $default = InventoryLocation::getOrCreateDefault();

        expect($default)->toBeInstanceOf(InventoryLocation::class);
        expect($default->code)->toBe(InventoryLocation::DEFAULT_LOCATION_CODE);
        expect($default->is_active)->toBeTrue();

        // Calling again should return same location
        $default2 = InventoryLocation::getOrCreateDefault();
        expect($default2->id)->toBe($default->id);
    }

    public function test_has_available_capacity(): void
    {
        $withCapacity = InventoryLocation::factory()->create([
            'capacity' => 100,
            'current_utilization' => 80,
        ]);
        $noCapacity = InventoryLocation::factory()->create([
            'capacity' => null,
        ]);

        expect($withCapacity->hasAvailableCapacity(15))->toBeTrue();
        expect($withCapacity->hasAvailableCapacity(25))->toBeFalse();
        expect($noCapacity->hasAvailableCapacity(1000))->toBeTrue();
    }

    public function test_get_utilization_percentage(): void
    {
        $location = InventoryLocation::factory()->create([
            'capacity' => 100,
            'current_utilization' => 75,
        ]);
        $noCapacity = InventoryLocation::factory()->create([
            'capacity' => null,
        ]);

        expect($location->getUtilizationPercentage())->toBe(75.0);
        expect($noCapacity->getUtilizationPercentage())->toBeNull();
    }

    public function test_coordinates(): void
    {
        $location = InventoryLocation::factory()->create();
        $location->setCoordinates(10.5, 20.3, 5.0);
        $location->save();

        $coords = $location->getCoordinates();

        expect($coords['x'])->toBe('10.50');
        expect($coords['y'])->toBe('20.30');
        expect($coords['z'])->toBe('5.00');
    }

    public function test_distance_to(): void
    {
        $location1 = InventoryLocation::factory()->create([
            'coordinate_x' => 0,
            'coordinate_y' => 0,
            'coordinate_z' => 0,
        ]);
        $location2 = InventoryLocation::factory()->create([
            'coordinate_x' => 3,
            'coordinate_y' => 4,
            'coordinate_z' => 0,
        ]);

        $distance = $location1->distanceTo($location2);

        expect($distance)->toBe(5.0);
    }

    public function test_distance_to_returns_null_when_no_coordinates(): void
    {
        $location1 = InventoryLocation::factory()->create([
            'coordinate_x' => null,
        ]);
        $location2 = InventoryLocation::factory()->create([
            'coordinate_x' => 10,
        ]);

        expect($location1->distanceTo($location2))->toBeNull();
    }

    public function test_scope_with_temperature_zone(): void
    {
        InventoryLocation::factory()->create(['temperature_zone' => TemperatureZone::Chilled->value]);
        InventoryLocation::factory()->create(['temperature_zone' => TemperatureZone::Frozen->value]);
        InventoryLocation::factory()->create(['temperature_zone' => TemperatureZone::Chilled->value]);

        $chilled = InventoryLocation::withTemperatureZone(TemperatureZone::Chilled)->get();
        $frozen = InventoryLocation::withTemperatureZone(TemperatureZone::Frozen)->get();

        expect($chilled)->toHaveCount(2);
        expect($frozen)->toHaveCount(1);
    }

    public function test_scope_hazmat_certified(): void
    {
        InventoryLocation::factory()->create(['is_hazmat_certified' => true]);
        InventoryLocation::factory()->create(['is_hazmat_certified' => false]);
        InventoryLocation::factory()->create(['is_hazmat_certified' => true]);

        $hazmat = InventoryLocation::hazmatCertified()->get();

        expect($hazmat)->toHaveCount(2);
    }

    public function test_scope_with_available_capacity(): void
    {
        // Location with capacity but full
        InventoryLocation::factory()->create([
            'capacity' => 100,
            'current_utilization' => 100,
        ]);
        // Location with capacity and space
        InventoryLocation::factory()->create([
            'capacity' => 100,
            'current_utilization' => 50,
        ]);
        // Location with unlimited capacity
        InventoryLocation::factory()->create([
            'capacity' => null,
        ]);

        $available = InventoryLocation::withAvailableCapacity(10)->get();

        expect($available)->toHaveCount(2); // One with space + one unlimited
    }

    public function test_scope_by_pick_sequence(): void
    {
        $third = InventoryLocation::factory()->create(['pick_sequence' => 30]);
        $first = InventoryLocation::factory()->create(['pick_sequence' => 10]);
        $second = InventoryLocation::factory()->create(['pick_sequence' => 20]);

        $sorted = InventoryLocation::byPickSequence()->get();

        expect($sorted->first()->id)->toBe($first->id);
        expect($sorted->last()->id)->toBe($third->id);
    }

    public function test_get_temperature_zone_enum(): void
    {
        $withZone = InventoryLocation::factory()->create([
            'temperature_zone' => TemperatureZone::Frozen->value,
        ]);
        $withoutZone = InventoryLocation::factory()->create([
            'temperature_zone' => null,
        ]);

        expect($withZone->getTemperatureZoneEnum())->toBe(TemperatureZone::Frozen);
        expect($withoutZone->getTemperatureZoneEnum())->toBeNull();
    }

    public function test_can_store_temperature_zone(): void
    {
        $chilledLocation = InventoryLocation::factory()->create([
            'temperature_zone' => TemperatureZone::Chilled->value,
        ]);
        $noZoneLocation = InventoryLocation::factory()->create([
            'temperature_zone' => null,
        ]);

        // Same zone should be compatible
        expect($chilledLocation->canStoreTemperatureZone(TemperatureZone::Chilled))->toBeTrue();
        // Chilled is not compatible with Frozen
        expect($chilledLocation->canStoreTemperatureZone(TemperatureZone::Frozen))->toBeFalse();
        // No zone = ambient, only ambient is compatible
        expect($noZoneLocation->canStoreTemperatureZone(TemperatureZone::Ambient))->toBeTrue();
        expect($noZoneLocation->canStoreTemperatureZone(TemperatureZone::Chilled))->toBeFalse();
    }

    public function test_can_store_hazmat(): void
    {
        $certified = InventoryLocation::factory()->create(['is_hazmat_certified' => true]);
        $notCertified = InventoryLocation::factory()->create(['is_hazmat_certified' => false]);

        expect($certified->canStoreHazmat())->toBeTrue();
        expect($notCertified->canStoreHazmat())->toBeFalse();
    }

    public function test_allocations_relationship(): void
    {
        $location = InventoryLocation::factory()->create();
        $item = InventoryItem::create(['name' => 'Test Item']);

        $level = InventoryLevel::factory()->create([
            'location_id' => $location->id,
            'inventoryable_type' => $item->getMorphClass(),
            'inventoryable_id' => $item->getKey(),
        ]);

        InventoryAllocation::factory()->create([
            'location_id' => $location->id,
            'level_id' => $level->id,
            'inventoryable_type' => $item->getMorphClass(),
            'inventoryable_id' => $item->getKey(),
            'quantity' => 10,
        ]);

        expect($location->allocations)->toHaveCount(1);
    }

    public function test_deleting_location_cascades_to_allocations(): void
    {
        $location = InventoryLocation::factory()->create();
        $item = InventoryItem::create(['name' => 'Test Item']);

        $level = InventoryLevel::factory()->create([
            'location_id' => $location->id,
            'inventoryable_type' => $item->getMorphClass(),
            'inventoryable_id' => $item->getKey(),
        ]);

        $allocation = InventoryAllocation::factory()->create([
            'location_id' => $location->id,
            'level_id' => $level->id,
            'inventoryable_type' => $item->getMorphClass(),
            'inventoryable_id' => $item->getKey(),
            'quantity' => 10,
        ]);
        $allocationId = $allocation->id;

        $location->delete();

        expect(InventoryAllocation::find($allocationId))->toBeNull();
    }

    public function test_scope_for_owner_when_disabled(): void
    {
        config(['inventory.owner.enabled' => false]);

        $location1 = InventoryLocation::factory()->create();
        $location2 = InventoryLocation::factory()->create();

        // When disabled, forOwner should return all records
        $result = InventoryLocation::forOwner(null)->get();

        expect($result)->toHaveCount(2);
    }

    public function test_scope_for_owner_with_owner_enabled_and_null(): void
    {
        config(['inventory.owner.enabled' => true]);

        // Create global location (no owner)
        $globalLocation = InventoryLocation::factory()->create([
            'owner_type' => null,
            'owner_id' => null,
        ]);

        // When owner is null and includeGlobal is true (default), return ownerless
        $result = InventoryLocation::forOwner(null, true)->get();

        expect($result->pluck('id')->toArray())->toContain($globalLocation->id);
    }

    public function test_scope_for_owner_with_owner_model(): void
    {
        config(['inventory.owner.enabled' => true]);

        $owner = InventoryItem::create(['name' => 'Owner Item']);

        // Create owned location
        $ownedLocation = InventoryLocation::factory()->create([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]);

        // Create global location
        $globalLocation = InventoryLocation::factory()->create([
            'owner_type' => null,
            'owner_id' => null,
        ]);

        // Create location owned by different owner
        $otherOwner = InventoryItem::create(['name' => 'Other Owner']);
        $otherLocation = InventoryLocation::factory()->create([
            'owner_type' => $otherOwner->getMorphClass(),
            'owner_id' => $otherOwner->getKey(),
        ]);

        // With includeGlobal true, should get owner's + global
        $result = InventoryLocation::forOwner($owner, true)->get();

        expect($result->pluck('id')->toArray())->toContain($ownedLocation->id);
        expect($result->pluck('id')->toArray())->toContain($globalLocation->id);
        expect($result->pluck('id')->toArray())->not->toContain($otherLocation->id);
    }

    public function test_scope_for_owner_exclude_global(): void
    {
        config(['inventory.owner.enabled' => true]);

        $owner = InventoryItem::create(['name' => 'Owner Item']);

        // Create owned location
        $ownedLocation = InventoryLocation::factory()->create([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]);

        // Create global location
        $globalLocation = InventoryLocation::factory()->create([
            'owner_type' => null,
            'owner_id' => null,
        ]);

        // With includeGlobal false, should only get owner's
        $result = InventoryLocation::forOwner($owner, false)->get();

        expect($result->pluck('id')->toArray())->toContain($ownedLocation->id);
        expect($result->pluck('id')->toArray())->not->toContain($globalLocation->id);
    }

    public function test_get_utilization_percentage_with_zero_capacity(): void
    {
        $location = InventoryLocation::factory()->create([
            'capacity' => 0,
            'current_utilization' => 0,
        ]);

        expect($location->getUtilizationPercentage())->toBeNull();
    }

    public function test_set_coordinates_returns_self(): void
    {
        $location = InventoryLocation::factory()->create();

        $result = $location->setCoordinates(5.0, 10.0, 15.0);

        expect($result)->toBe($location);
        expect($location->coordinate_x)->toBe('5.00');
        expect($location->coordinate_y)->toBe('10.00');
        expect($location->coordinate_z)->toBe('15.00');
    }

    public function test_distance_with_null_y_and_z(): void
    {
        $location1 = InventoryLocation::factory()->create([
            'coordinate_x' => 0,
            'coordinate_y' => null,
            'coordinate_z' => null,
        ]);
        $location2 = InventoryLocation::factory()->create([
            'coordinate_x' => 10,
            'coordinate_y' => null,
            'coordinate_z' => null,
        ]);

        $distance = $location1->distanceTo($location2);

        expect($distance)->toBe(10.0);
    }
}
