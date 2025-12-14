<?php

declare(strict_types=1);

use AIArmada\Inventory\Enums\TemperatureZone;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\LocationTreeService;

beforeEach(function (): void {
    $this->service = new LocationTreeService();
});

describe('getTree', function (): void {
    it('returns hierarchical tree structure', function (): void {
        $parent = InventoryLocation::factory()->create([
            'name' => 'Warehouse',
            'parent_id' => null,
            'depth' => 0,
        ]);

        $child = InventoryLocation::factory()->create([
            'name' => 'Aisle A',
            'parent_id' => $parent->id,
            'depth' => 1,
            'path' => $parent->id . '/' . fake()->uuid(),
        ]);

        $tree = $this->service->getTree();

        expect($tree)->toHaveCount(1);
        expect($tree->first()->name)->toBe('Warehouse');
        expect($tree->first()->children)->toHaveCount(1);
    });

    it('orders by depth and name', function (): void {
        $parent1 = InventoryLocation::factory()->create(['name' => 'B Warehouse', 'depth' => 0, 'parent_id' => null]);
        $parent2 = InventoryLocation::factory()->create(['name' => 'A Warehouse', 'depth' => 0, 'parent_id' => null]);

        $tree = $this->service->getTree();

        expect($tree->first()->name)->toBe('A Warehouse');
    });
});

describe('getActiveTree', function (): void {
    it('returns only active locations', function (): void {
        $active = InventoryLocation::factory()->create(['is_active' => true, 'parent_id' => null, 'depth' => 0]);
        $inactive = InventoryLocation::factory()->create(['is_active' => false, 'parent_id' => null, 'depth' => 0]);

        $tree = $this->service->getActiveTree();

        expect($tree)->toHaveCount(1);
        expect($tree->first()->id)->toBe($active->id);
    });
});

describe('getSubtree', function (): void {
    it('returns subtree starting from location', function (): void {
        $root = InventoryLocation::factory()->create([
            'name' => 'Root',
            'parent_id' => null,
            'depth' => 0,
        ]);
        $root->update(['path' => $root->id]);

        $child = InventoryLocation::factory()->create([
            'name' => 'Child',
            'parent_id' => $root->id,
            'depth' => 1,
            'path' => $root->id . '/' . fake()->uuid(),
        ]);

        $grandchild = InventoryLocation::factory()->create([
            'name' => 'Grandchild',
            'parent_id' => $child->id,
            'depth' => 2,
            'path' => $child->path . '/' . fake()->uuid(),
        ]);

        $subtree = $this->service->getSubtree($root);

        expect($subtree)->toHaveCount(1);
        expect($subtree->first()->id)->toBe($root->id);
    });
});

describe('getFlatTree', function (): void {
    it('returns flattened tree with depth info', function (): void {
        $parent = InventoryLocation::factory()->create([
            'name' => 'Warehouse',
            'code' => 'WH-001',
            'parent_id' => null,
            'depth' => 0,
            'is_active' => true,
        ]);

        $child = InventoryLocation::factory()->create([
            'name' => 'Aisle A',
            'code' => 'WH-001-A',
            'parent_id' => $parent->id,
            'depth' => 1,
            'is_active' => true,
            'path' => $parent->id,
        ]);

        $flat = $this->service->getFlatTree();

        expect($flat)->toHaveCount(2);
        expect($flat[0]['depth'])->toBe(0);
        expect($flat[0]['code'])->toBe('WH-001');
    });
});

describe('getSelectOptions', function (): void {
    it('returns formatted options with hierarchy indication', function (): void {
        $parent = InventoryLocation::factory()->create([
            'name' => 'Warehouse',
            'code' => 'WH',
            'parent_id' => null,
            'depth' => 0,
            'is_active' => true,
        ]);

        $child = InventoryLocation::factory()->create([
            'name' => 'Aisle A',
            'code' => 'A1',
            'parent_id' => $parent->id,
            'depth' => 1,
            'is_active' => true,
            'path' => $parent->id,
        ]);

        $options = $this->service->getSelectOptions();

        expect($options)->toHaveKey($parent->id);
        expect($options)->toHaveKey($child->id);
        expect($options[$parent->id])->toBe('Warehouse (WH)');
        expect($options[$child->id])->toContain('Aisle A');
    });
});

describe('createLocation', function (): void {
    it('creates root location', function (): void {
        $location = $this->service->createLocation('Main Warehouse', 'MW-001');

        expect($location->name)->toBe('Main Warehouse');
        expect($location->code)->toBe('MW-001');
        expect($location->parent_id)->toBeNull();
        expect($location->depth)->toBe(0);
        expect($location->is_active)->toBeTrue();
    });

    it('creates child location with proper hierarchy', function (): void {
        $parent = InventoryLocation::factory()->create([
            'name' => 'Warehouse',
            'parent_id' => null,
            'depth' => 0,
        ]);
        $parent->update(['path' => $parent->id]);

        $child = $this->service->createLocation('Aisle A', 'AA-001', $parent);

        expect($child->parent_id)->toBe($parent->id);
        expect($child->depth)->toBe(1);
        expect($child->path)->toContain($parent->id);
    });

    it('creates location with temperature zone', function (): void {
        $location = $this->service->createLocation(
            'Cold Storage',
            'CS-001',
            null,
            TemperatureZone::Chilled
        );

        expect($location->temperature_zone)->toBe(TemperatureZone::Chilled->value);
    });

    it('creates hazmat certified location', function (): void {
        $location = $this->service->createLocation(
            'Chemical Storage',
            'CHEM-001',
            null,
            null,
            true
        );

        expect($location->is_hazmat_certified)->toBeTrue();
    });
});

describe('moveLocation', function (): void {
    it('moves location to new parent', function (): void {
        $parent1 = InventoryLocation::factory()->create(['parent_id' => null, 'depth' => 0]);
        $parent1->update(['path' => $parent1->id]);

        $parent2 = InventoryLocation::factory()->create(['parent_id' => null, 'depth' => 0]);
        $parent2->update(['path' => $parent2->id]);

        $child = InventoryLocation::factory()->create([
            'parent_id' => $parent1->id,
            'depth' => 1,
            'path' => $parent1->id . '/' . fake()->uuid(),
        ]);

        $moved = $this->service->moveLocation($child, $parent2);

        expect($moved->parent_id)->toBe($parent2->id);
    });

    it('throws exception when moving to own descendant', function (): void {
        $parent = InventoryLocation::factory()->create(['parent_id' => null, 'depth' => 0]);
        $parent->update(['path' => $parent->id]);

        $child = InventoryLocation::factory()->create([
            'parent_id' => $parent->id,
            'depth' => 1,
            'path' => $parent->id . '/' . fake()->uuid(),
        ]);

        $this->service->moveLocation($parent, $child);
    })->throws(InvalidArgumentException::class);

    it('moves to root when parent is null', function (): void {
        $parent = InventoryLocation::factory()->create(['parent_id' => null, 'depth' => 0]);
        $child = InventoryLocation::factory()->create([
            'parent_id' => $parent->id,
            'depth' => 1,
        ]);

        $moved = $this->service->moveLocation($child, null);

        expect($moved->parent_id)->toBeNull();
    });
});

describe('rebuildAllPaths', function (): void {
    it('rebuilds paths for all locations', function (): void {
        $root = InventoryLocation::factory()->create([
            'parent_id' => null,
            'depth' => 0,
            'path' => 'corrupted',
        ]);

        $child = InventoryLocation::factory()->create([
            'parent_id' => $root->id,
            'depth' => 0,
            'path' => 'also-corrupted',
        ]);

        $count = $this->service->rebuildAllPaths();

        expect($count)->toBe(2);

        $root->refresh();
        expect($root->path)->toBe($root->id);
        expect($root->depth)->toBe(0);

        $child->refresh();
        expect($child->depth)->toBe(1);
    });
});

describe('getLeafLocations', function (): void {
    it('returns locations without children', function (): void {
        $parent = InventoryLocation::factory()->create([
            'parent_id' => null,
            'depth' => 0,
            'is_active' => true,
        ]);

        $leaf1 = InventoryLocation::factory()->create([
            'parent_id' => $parent->id,
            'depth' => 1,
            'is_active' => true,
        ]);

        $leaf2 = InventoryLocation::factory()->create([
            'parent_id' => $parent->id,
            'depth' => 1,
            'is_active' => true,
        ]);

        $leaves = $this->service->getLeafLocations();

        expect($leaves)->toHaveCount(2);
        expect($leaves->pluck('id')->toArray())->toContain($leaf1->id, $leaf2->id);
    });
});

describe('getLocationsAtDepth', function (): void {
    it('returns locations at specific depth', function (): void {
        $root = InventoryLocation::factory()->create(['depth' => 0, 'parent_id' => null, 'is_active' => true]);
        $child = InventoryLocation::factory()->create(['depth' => 1, 'parent_id' => $root->id, 'is_active' => true]);
        $grandchild = InventoryLocation::factory()->create(['depth' => 2, 'parent_id' => $child->id, 'is_active' => true]);

        $atDepth1 = $this->service->getLocationsAtDepth(1);

        expect($atDepth1)->toHaveCount(1);
        expect($atDepth1->first()->id)->toBe($child->id);
    });
});

describe('getMaxDepth', function (): void {
    it('returns maximum depth in hierarchy', function (): void {
        // Create actual hierarchy - depth is computed from parent-child relationships
        $root = InventoryLocation::factory()->create(['parent_id' => null]); // depth 0
        $level1 = InventoryLocation::factory()->create(['parent_id' => $root->id]); // depth 1
        $level2 = InventoryLocation::factory()->create(['parent_id' => $level1->id]); // depth 2
        $level3 = InventoryLocation::factory()->create(['parent_id' => $level2->id]); // depth 3
        $level4 = InventoryLocation::factory()->create(['parent_id' => $level3->id]); // depth 4
        InventoryLocation::factory()->create(['parent_id' => $level4->id]); // depth 5

        expect($this->service->getMaxDepth())->toBe(5);
    });

    it('returns zero when no locations', function (): void {
        expect($this->service->getMaxDepth())->toBe(0);
    });
});

describe('validateMove', function (): void {
    it('returns valid for move to root', function (): void {
        $location = InventoryLocation::factory()->create(['parent_id' => null, 'depth' => 0]);

        $result = $this->service->validateMove($location, null);

        expect($result['valid'])->toBeTrue();
        expect($result['reason'])->toBeNull();
    });

    it('returns invalid for move to self', function (): void {
        $location = InventoryLocation::factory()->create(['parent_id' => null, 'depth' => 0]);

        $result = $this->service->validateMove($location, $location);

        expect($result['valid'])->toBeFalse();
        expect($result['reason'])->toContain('itself');
    });

    it('returns invalid for move to descendant', function (): void {
        $parent = InventoryLocation::factory()->create(['parent_id' => null, 'depth' => 0]);
        $parent->update(['path' => $parent->id]);

        $child = InventoryLocation::factory()->create([
            'parent_id' => $parent->id,
            'depth' => 1,
            'path' => $parent->id . '/' . fake()->uuid(),
        ]);

        $result = $this->service->validateMove($parent, $child);

        expect($result['valid'])->toBeFalse();
        expect($result['reason'])->toContain('descendant');
    });

    it('checks temperature zone compatibility', function (): void {
        $frozen = InventoryLocation::factory()->create([
            'parent_id' => null,
            'depth' => 0,
            'temperature_zone' => TemperatureZone::Frozen->value,
        ]);

        $ambient = InventoryLocation::factory()->create([
            'parent_id' => null,
            'depth' => 0,
            'temperature_zone' => TemperatureZone::Ambient->value,
        ]);

        $result = $this->service->validateMove($frozen, $ambient);

        expect($result['valid'])->toBeFalse();
        expect($result['reason'])->toContain('Temperature');
    });
});
