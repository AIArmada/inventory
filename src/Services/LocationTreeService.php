<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services;

use AIArmada\Inventory\Enums\TemperatureZone;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class LocationTreeService
{
    /**
     * Get a tree structure of all locations.
     *
     * @return Collection<int, InventoryLocation>
     */
    public function getTree(): Collection
    {
        $locations = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
            ->orderBy('depth')
            ->orderBy('name')
            ->get();

        return $this->buildTree($locations);
    }

    /**
     * Get a tree structure for active locations only.
     *
     * @return Collection<int, InventoryLocation>
     */
    public function getActiveTree(): Collection
    {
        $locations = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
            ->active()
            ->orderBy('depth')
            ->orderBy('name')
            ->get();

        return $this->buildTree($locations);
    }

    /**
     * Get the subtree starting from a location.
     *
     * @return Collection<int, InventoryLocation>
     */
    public function getSubtree(InventoryLocation $root): Collection
    {
        $rootIsAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query()->whereKey($root->getKey()))->exists();

        if (! $rootIsAllowed) {
            throw new InvalidArgumentException('Invalid root location for current owner');
        }

        $locations = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
            ->where(function (Builder $query) use ($root): void {
                $query->where('id', $root->id)
                    ->orWhere('path', 'like', $root->path . '/%');
            })
            ->orderBy('depth')
            ->orderBy('name')
            ->get();

        return $this->buildTree($locations, $root->parent_id);
    }

    /**
     * Get a flattened tree with indentation info.
     *
     * @return array<array{id: string, name: string, code: string, depth: int, is_active: bool, children_count: int}>
     */
    public function getFlatTree(): array
    {
        return InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
            ->withCount('children')
            ->orderByRaw('COALESCE(path, id)')
            ->get()
            ->map(fn (InventoryLocation $loc): array => [
                'id' => $loc->id,
                'name' => $loc->name,
                'code' => $loc->code,
                'depth' => $loc->depth,
                'is_active' => $loc->is_active,
                'children_count' => $loc->children_count,
            ])
            ->toArray();
    }

    /**
     * Get options for select fields with hierarchy indication.
     *
     * @return array<string, string>
     */
    public function getSelectOptions(): array
    {
        return InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
            ->active()
            ->orderByRaw('COALESCE(path, id)')
            ->get()
            ->mapWithKeys(fn (InventoryLocation $loc): array => [
                $loc->id => str_repeat('— ', $loc->depth) . $loc->name . ' (' . $loc->code . ')',
            ])
            ->toArray();
    }

    /**
     * Create a new location within the hierarchy.
     */
    public function createLocation(
        string $name,
        string $code,
        ?InventoryLocation $parent = null,
        ?TemperatureZone $zone = null,
        bool $isHazmatCertified = false
    ): InventoryLocation {
        return DB::transaction(function () use ($name, $code, $parent, $zone, $isHazmatCertified): InventoryLocation {
            if (InventoryOwnerScope::isEnabled()) {
                $owner = InventoryOwnerScope::resolveOwner();

                if ($parent !== null) {
                    $parentIsAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query()->whereKey($parent->getKey()))->exists();

                    if (! $parentIsAllowed) {
                        throw new InvalidArgumentException('Invalid parent location for current owner');
                    }

                    if ($owner !== null) {
                        if ($parent->owner_type !== $owner->getMorphClass() || $parent->owner_id !== $owner->getKey()) {
                            throw new InvalidArgumentException('Parent location must belong to current owner');
                        }
                    } else {
                        if ($parent->owner_type !== null || $parent->owner_id !== null) {
                            throw new InvalidArgumentException('Parent location must be global in global-only context');
                        }
                    }
                }
            }

            $location = new InventoryLocation([
                'name' => $name,
                'code' => $code,
                'is_active' => true,
                'temperature_zone' => $zone?->value,
                'is_hazmat_certified' => $isHazmatCertified,
            ]);

            if (InventoryOwnerScope::isEnabled()) {
                $owner = InventoryOwnerScope::resolveOwner();
                $location->owner_type = $owner?->getMorphClass();
                $location->owner_id = $owner?->getKey();
            }

            if ($parent !== null) {
                $location->parent_id = $parent->id;
                $location->depth = $parent->depth + 1;
            } else {
                $location->depth = 0;
            }

            $location->save();

            // Update path after we have ID
            if ($parent !== null) {
                $location->path = $parent->path . '/' . $location->id;
            } else {
                $location->path = $location->id;
            }

            $location->saveQuietly();

            return $location;
        });
    }

    /**
     * Move a location to a new parent.
     */
    public function moveLocation(InventoryLocation $location, ?InventoryLocation $newParent): InventoryLocation
    {
        if ($newParent !== null && $location->isAncestorOf($newParent)) {
            throw new InvalidArgumentException('Cannot move a location to its own descendant');
        }

        if (InventoryOwnerScope::isEnabled()) {
            $owner = InventoryOwnerScope::resolveOwner();

            $locationIsAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query()->whereKey($location->getKey()))->exists();
            if (! $locationIsAllowed) {
                throw new InvalidArgumentException('Invalid location for current owner');
            }

            if ($newParent !== null) {
                $newParentIsAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query()->whereKey($newParent->getKey()))->exists();
                if (! $newParentIsAllowed) {
                    throw new InvalidArgumentException('Invalid new parent for current owner');
                }

                if ($owner !== null) {
                    if ($newParent->owner_type !== $owner->getMorphClass() || $newParent->owner_id !== $owner->getKey()) {
                        throw new InvalidArgumentException('New parent must belong to current owner');
                    }
                } else {
                    if ($newParent->owner_type !== null || $newParent->owner_id !== null) {
                        throw new InvalidArgumentException('New parent must be global in global-only context');
                    }
                }
            }
        }

        return DB::transaction(function () use ($location, $newParent): InventoryLocation {
            $location->moveTo($newParent);

            return $location->fresh() ?? $location;
        });
    }

    /**
     * Rebuild all paths in the hierarchy (for maintenance/repair).
     */
    public function rebuildAllPaths(): int
    {
        return DB::transaction(function (): int {
            $count = 0;

            // First, handle root locations
            $roots = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())
                ->whereNull('parent_id')
                ->get();

            foreach ($roots as $root) {
                $root->path = $root->id;
                $root->depth = 0;
                $root->saveQuietly();
                $count++;

                $count += $this->rebuildChildPaths($root);
            }

            return $count;
        });
    }

    /**
     * Get all leaf locations (endpoints suitable for storing inventory).
     *
     * @return Collection<int, InventoryLocation>
     */
    public function getLeafLocations(): Collection
    {
        $query = InventoryLocation::leaves()
            ->active()
            ->orderBy('path');

        InventoryOwnerScope::applyToLocationQuery($query);

        return $query->get();
    }

    /**
     * Get locations at a specific depth level.
     *
     * @return Collection<int, InventoryLocation>
     */
    public function getLocationsAtDepth(int $depth): Collection
    {
        $query = InventoryLocation::atDepth($depth)
            ->active()
            ->orderBy('name');

        InventoryOwnerScope::applyToLocationQuery($query);

        return $query->get();
    }

    /**
     * Get the maximum depth in the hierarchy.
     */
    public function getMaxDepth(): int
    {
        return (int) InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query())->max('depth');
    }

    /**
     * Validate that a location can be moved to a new parent.
     *
     * @return array{valid: bool, reason: string|null}
     */
    public function validateMove(InventoryLocation $location, ?InventoryLocation $newParent): array
    {
        if ($newParent === null) {
            return ['valid' => true, 'reason' => null];
        }

        if ($location->id === $newParent->id) {
            return ['valid' => false, 'reason' => 'Cannot move a location to itself'];
        }

        if ($location->isAncestorOf($newParent)) {
            return ['valid' => false, 'reason' => 'Cannot move a location to its own descendant'];
        }

        // Check temperature zone compatibility
        if ($location->temperature_zone !== null && $newParent->temperature_zone !== null) {
            $locationZone = TemperatureZone::from($location->temperature_zone);
            $parentZone = TemperatureZone::from($newParent->temperature_zone);

            if (! $locationZone->isCompatibleWith($parentZone)) {
                return [
                    'valid' => false,
                    'reason' => sprintf(
                        'Temperature zone mismatch: %s cannot be placed within %s',
                        $locationZone->label(),
                        $parentZone->label()
                    ),
                ];
            }
        }

        return ['valid' => true, 'reason' => null];
    }

    /**
     * Build a tree from a flat collection.
     *
     * @param  Collection<int, InventoryLocation>  $locations
     * @return Collection<int, InventoryLocation>
     */
    private function buildTree(Collection $locations, ?string $parentId = null): Collection
    {
        $tree = new Collection;

        $roots = $locations->filter(fn (InventoryLocation $loc): bool => $loc->parent_id === $parentId);

        foreach ($roots as $root) {
            $root->setRelation('children', $this->buildTree($locations, $root->id));
            $tree->push($root);
        }

        return $tree;
    }

    /**
     * Recursively rebuild child paths.
     */
    private function rebuildChildPaths(InventoryLocation $parent): int
    {
        $count = 0;
        $children = InventoryLocation::where('parent_id', $parent->id)->get();

        foreach ($children as $child) {
            $child->path = $parent->path . '/' . $child->id;
            $child->depth = $parent->depth + 1;
            $child->saveQuietly();
            $count++;

            $count += $this->rebuildChildPaths($child);
        }

        return $count;
    }
}
