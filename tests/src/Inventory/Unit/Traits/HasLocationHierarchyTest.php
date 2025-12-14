<?php

declare(strict_types=1);

use AIArmada\Inventory\Models\InventoryLocation;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

describe('HasLocationHierarchy', function (): void {
    describe('parent relationship', function (): void {
        it('has parent relationship', function (): void {
            $parent = InventoryLocation::factory()->create(['name' => 'Parent']);
            $child = InventoryLocation::factory()->create([
                'name' => 'Child',
                'parent_id' => $parent->id,
            ]);

            expect($child->parent)->not->toBeNull();
            expect($child->parent->id)->toBe($parent->id);
        });
    });

    describe('children relationship', function (): void {
        it('has children relationship', function (): void {
            $parent = InventoryLocation::factory()->create(['name' => 'Parent']);
            $child1 = InventoryLocation::factory()->create(['name' => 'Child 1', 'parent_id' => $parent->id]);
            $child2 = InventoryLocation::factory()->create(['name' => 'Child 2', 'parent_id' => $parent->id]);

            expect($parent->children)->toHaveCount(2);
        });
    });

    describe('descendants attribute', function (): void {
        it('gets all descendants', function (): void {
            $root = InventoryLocation::factory()->create(['name' => 'Root']);
            $child = InventoryLocation::factory()->create(['name' => 'Child', 'parent_id' => $root->id]);
            $grandchild = InventoryLocation::factory()->create(['name' => 'Grandchild', 'parent_id' => $child->id]);

            $descendants = $root->descendants;

            expect($descendants)->toHaveCount(2);
            expect($descendants->pluck('id')->toArray())->toContain($child->id, $grandchild->id);
        });

        it('returns empty collection when no path', function (): void {
            $location = new InventoryLocation(['name' => 'No Path']);
            $location->path = null;

            expect($location->descendants)->toBeInstanceOf(Collection::class);
            expect($location->descendants)->toBeEmpty();
        });
    });

    describe('ancestors attribute', function (): void {
        it('gets all ancestors', function (): void {
            $root = InventoryLocation::factory()->create(['name' => 'Root']);
            $child = InventoryLocation::factory()->create(['name' => 'Child', 'parent_id' => $root->id]);
            $grandchild = InventoryLocation::factory()->create(['name' => 'Grandchild', 'parent_id' => $child->id]);

            $ancestors = $grandchild->ancestors;

            expect($ancestors)->toHaveCount(2);
        });

        it('returns empty collection for root', function (): void {
            $root = InventoryLocation::factory()->create(['name' => 'Root']);

            expect($root->ancestors)->toBeInstanceOf(Collection::class);
            expect($root->ancestors)->toBeEmpty();
        });

        it('returns empty collection when no path', function (): void {
            $location = new InventoryLocation(['name' => 'No Path']);
            $location->path = null;

            expect($location->ancestors)->toBeInstanceOf(Collection::class);
            expect($location->ancestors)->toBeEmpty();
        });
    });

    describe('getRoot', function (): void {
        it('returns self when is root', function (): void {
            $root = InventoryLocation::factory()->create(['name' => 'Root']);

            expect($root->getRoot()->id)->toBe($root->id);
        });

        it('returns root ancestor', function (): void {
            $root = InventoryLocation::factory()->create(['name' => 'Root']);
            $child = InventoryLocation::factory()->create(['name' => 'Child', 'parent_id' => $root->id]);
            $grandchild = InventoryLocation::factory()->create(['name' => 'Grandchild', 'parent_id' => $child->id]);

            expect($grandchild->getRoot()->id)->toBe($root->id);
        });

        it('returns null when no path', function (): void {
            $location = new InventoryLocation(['name' => 'No Path']);
            $location->path = null;
            $location->parent_id = 'some-id'; // Not root but no path

            expect($location->getRoot())->toBeNull();
        });
    });

    describe('isRoot', function (): void {
        it('returns true for root location', function (): void {
            $root = InventoryLocation::factory()->create(['name' => 'Root']);

            expect($root->isRoot())->toBeTrue();
        });

        it('returns false for child location', function (): void {
            $root = InventoryLocation::factory()->create(['name' => 'Root']);
            $child = InventoryLocation::factory()->create(['name' => 'Child', 'parent_id' => $root->id]);

            expect($child->isRoot())->toBeFalse();
        });
    });

    describe('isLeaf', function (): void {
        it('returns true for leaf location', function (): void {
            $root = InventoryLocation::factory()->create(['name' => 'Root']);
            $leaf = InventoryLocation::factory()->create(['name' => 'Leaf', 'parent_id' => $root->id]);

            expect($leaf->isLeaf())->toBeTrue();
        });

        it('returns false for location with children', function (): void {
            $root = InventoryLocation::factory()->create(['name' => 'Root']);
            InventoryLocation::factory()->create(['name' => 'Child', 'parent_id' => $root->id]);

            expect($root->isLeaf())->toBeFalse();
        });
    });

    describe('isAncestorOf', function (): void {
        it('returns true when ancestor', function (): void {
            $root = InventoryLocation::factory()->create(['name' => 'Root']);
            $child = InventoryLocation::factory()->create(['name' => 'Child', 'parent_id' => $root->id]);
            $grandchild = InventoryLocation::factory()->create(['name' => 'Grandchild', 'parent_id' => $child->id]);

            expect($root->isAncestorOf($grandchild))->toBeTrue();
            expect($child->isAncestorOf($grandchild))->toBeTrue();
        });

        it('returns false when not ancestor', function (): void {
            $root = InventoryLocation::factory()->create(['name' => 'Root']);
            $child = InventoryLocation::factory()->create(['name' => 'Child', 'parent_id' => $root->id]);

            expect($child->isAncestorOf($root))->toBeFalse();
        });

        it('returns false when path is null', function (): void {
            $location1 = new InventoryLocation(['name' => 'A']);
            $location1->path = null;

            $location2 = InventoryLocation::factory()->create(['name' => 'B']);

            expect($location1->isAncestorOf($location2))->toBeFalse();
        });
    });

    describe('isDescendantOf', function (): void {
        it('returns true when descendant', function (): void {
            $root = InventoryLocation::factory()->create(['name' => 'Root']);
            $child = InventoryLocation::factory()->create(['name' => 'Child', 'parent_id' => $root->id]);

            expect($child->isDescendantOf($root))->toBeTrue();
        });

        it('returns false when not descendant', function (): void {
            $root = InventoryLocation::factory()->create(['name' => 'Root']);
            $child = InventoryLocation::factory()->create(['name' => 'Child', 'parent_id' => $root->id]);

            expect($root->isDescendantOf($child))->toBeFalse();
        });
    });

    describe('isSiblingOf', function (): void {
        it('returns true for siblings', function (): void {
            $root = InventoryLocation::factory()->create(['name' => 'Root']);
            $child1 = InventoryLocation::factory()->create(['name' => 'Child 1', 'parent_id' => $root->id]);
            $child2 = InventoryLocation::factory()->create(['name' => 'Child 2', 'parent_id' => $root->id]);

            expect($child1->isSiblingOf($child2))->toBeTrue();
        });

        it('returns false for non-siblings', function (): void {
            $root = InventoryLocation::factory()->create(['name' => 'Root']);
            $child = InventoryLocation::factory()->create(['name' => 'Child', 'parent_id' => $root->id]);

            expect($root->isSiblingOf($child))->toBeFalse();
        });

        it('returns false for same location', function (): void {
            $root = InventoryLocation::factory()->create(['name' => 'Root']);
            $child = InventoryLocation::factory()->create(['name' => 'Child', 'parent_id' => $root->id]);

            expect($child->isSiblingOf($child))->toBeFalse();
        });
    });

    describe('getSiblings', function (): void {
        it('returns sibling locations', function (): void {
            $root = InventoryLocation::factory()->create(['name' => 'Root']);
            $child1 = InventoryLocation::factory()->create(['name' => 'Child 1', 'parent_id' => $root->id]);
            $child2 = InventoryLocation::factory()->create(['name' => 'Child 2', 'parent_id' => $root->id]);
            $child3 = InventoryLocation::factory()->create(['name' => 'Child 3', 'parent_id' => $root->id]);

            $siblings = $child1->getSiblings();

            expect($siblings)->toHaveCount(2);
            expect($siblings->pluck('id')->toArray())->not->toContain($child1->id);
        });
    });

    describe('scopes', function (): void {
        beforeEach(function (): void {
            $this->root = InventoryLocation::factory()->create(['name' => 'Root']);
            $this->child = InventoryLocation::factory()->create(['name' => 'Child', 'parent_id' => $this->root->id]);
            $this->grandchild = InventoryLocation::factory()->create(['name' => 'Grandchild', 'parent_id' => $this->child->id]);
        });

        it('filters roots only', function (): void {
            $roots = InventoryLocation::roots()->get();

            expect($roots)->toHaveCount(1);
            expect($roots->first()->id)->toBe($this->root->id);
        });

        it('filters leaves only', function (): void {
            $leaves = InventoryLocation::leaves()->get();

            expect($leaves)->toHaveCount(1);
            expect($leaves->first()->id)->toBe($this->grandchild->id);
        });

        it('filters by depth', function (): void {
            $atDepth0 = InventoryLocation::atDepth(0)->get();
            $atDepth1 = InventoryLocation::atDepth(1)->get();
            $atDepth2 = InventoryLocation::atDepth(2)->get();

            expect($atDepth0)->toHaveCount(1);
            expect($atDepth1)->toHaveCount(1);
            expect($atDepth2)->toHaveCount(1);
        });

        it('filters within subtree', function (): void {
            $subtree = InventoryLocation::withinSubtree($this->child)->get();

            expect($subtree)->toHaveCount(2);
            expect($subtree->pluck('id')->toArray())->toContain($this->child->id, $this->grandchild->id);
        });
    });

    describe('getBreadcrumbs', function (): void {
        it('returns breadcrumb trail', function (): void {
            $root = InventoryLocation::factory()->create(['name' => 'Root']);
            $child = InventoryLocation::factory()->create(['name' => 'Child', 'parent_id' => $root->id]);
            $grandchild = InventoryLocation::factory()->create(['name' => 'Grandchild', 'parent_id' => $child->id]);

            $breadcrumbs = $grandchild->getBreadcrumbs();

            expect($breadcrumbs)->toHaveCount(3);
            expect($breadcrumbs->last()->id)->toBe($grandchild->id);
        });
    });

    describe('getAncestors', function (): void {
        it('returns empty collection when no path', function (): void {
            $location = InventoryLocation::factory()->create([
                'name' => 'Root Location',
                'path' => null,
                'depth' => 0,
            ]);

            $ancestors = $location->ancestors;

            expect($ancestors)->toBeEmpty();
        });

        it('returns ancestors when path exists', function (): void {
            $service = app(AIArmada\Inventory\Services\LocationTreeService::class);

            $parent = $service->createLocation('Parent', 'P-001');
            $child = $service->createLocation('Child', 'C-001', $parent);
            $grandchild = $service->createLocation('Grandchild', 'GC-001', $child);

            // Debug: check the path
            expect($grandchild->path)->toContain($parent->id);
            expect($grandchild->path)->toContain($child->id);

            $ancestors = $grandchild->ancestors;

            expect($ancestors)->toHaveCount(2);
            expect($ancestors->pluck('id')->toArray())->toBe([$parent->id, $child->id]);
        });
    });

    describe('moveTo', function (): void {
        it('moves location to new parent', function (): void {
            $root1 = InventoryLocation::factory()->create(['name' => 'Root 1']);
            $root2 = InventoryLocation::factory()->create(['name' => 'Root 2']);
            $child = InventoryLocation::factory()->create(['name' => 'Child', 'parent_id' => $root1->id]);

            $child->moveTo($root2);

            expect($child->fresh()->parent_id)->toBe($root2->id);
        });

        it('moves location to root', function (): void {
            $root = InventoryLocation::factory()->create(['name' => 'Root']);
            $child = InventoryLocation::factory()->create(['name' => 'Child', 'parent_id' => $root->id]);

            $child->moveTo(null);

            expect($child->fresh()->parent_id)->toBeNull();
            expect($child->fresh()->isRoot())->toBeTrue();
        });

        it('throws exception when moving to own descendant', function (): void {
            $root = InventoryLocation::factory()->create(['name' => 'Root']);
            $child = InventoryLocation::factory()->create(['name' => 'Child', 'parent_id' => $root->id]);
            $grandchild = InventoryLocation::factory()->create(['name' => 'Grandchild', 'parent_id' => $child->id]);

            expect(fn () => $root->moveTo($grandchild))->toThrow(InvalidArgumentException::class);
        });
    });

    describe('path and depth updates', function (): void {
        it('sets path and depth on create', function (): void {
            $root = InventoryLocation::factory()->create(['name' => 'Root']);
            $child = InventoryLocation::factory()->create(['name' => 'Child', 'parent_id' => $root->id]);

            expect($root->depth)->toBe(0);
            expect($child->depth)->toBe(1);
            expect($child->path)->toContain($root->id);
        });

        it('updates depth on parent change', function (): void {
            $root1 = InventoryLocation::factory()->create(['name' => 'Root 1']);
            $root2 = InventoryLocation::factory()->create(['name' => 'Root 2']);
            $child = InventoryLocation::factory()->create(['name' => 'Child', 'parent_id' => $root1->id]);

            $originalDepth = $child->depth;

            $child->parent_id = $root2->id;
            $child->save();

            // The depth should remain the same since both parents are roots
            expect($child->fresh()->depth)->toBe($originalDepth);
            expect($child->fresh()->parent_id)->toBe($root2->id);
        });

        it('cascades children to parent on delete', function (): void {
            $root = InventoryLocation::factory()->create(['name' => 'Root']);
            $middle = InventoryLocation::factory()->create(['name' => 'Middle', 'parent_id' => $root->id]);
            $child = InventoryLocation::factory()->create(['name' => 'Child', 'parent_id' => $middle->id]);

            $middle->delete();

            expect($child->fresh()->parent_id)->toBe($root->id);
        });
    });
});
