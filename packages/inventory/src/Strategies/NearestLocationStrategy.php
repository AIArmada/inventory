<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Strategies;

use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Nearest Location allocation strategy.
 * Allocates from locations closest to a specified origin point.
 */
/** @implements AllocationStrategyInterface<InventoryLocation> */
final class NearestLocationStrategy implements AllocationStrategyInterface
{
    public function name(): string
    {
        return 'nearest_location';
    }

    public function label(): string
    {
        return 'Nearest Location';
    }

    public function description(): string
    {
        return 'Allocates inventory from locations closest to the specified origin, optimizing for picking efficiency.';
    }

    /**
     * @return array<int, array{location_id: string, quantity: int, distance: float}>
     */
    public function allocate(Model $model, int $quantity, ?AllocationContext $context = null): array
    {
        $context = $context ?? new AllocationContext;

        $levels = $this->getAvailableLevels($model, $context);
        $sortedLevels = $this->sortByDistance($levels, $context);

        return $this->buildAllocations($sortedLevels, $quantity, $context);
    }

    public function canFulfill(Model $model, int $quantity, ?AllocationContext $context = null): bool
    {
        $context = $context ?? new AllocationContext;

        $query = InventoryLevel::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey());

        if ($context->excludeLocationIds !== null) {
            $query->whereNotIn('location_id', $context->excludeLocationIds);
        }

        $available = $query->sum('quantity_available');

        return $available >= $quantity;
    }

    /**
     * @return Collection<int, InventoryLocation>
     */
    public function getRecommendedOrder(Model $model, ?AllocationContext $context = null): Collection
    {
        $context = $context ?? new AllocationContext;

        $levels = $this->getAvailableLevels($model, $context);
        $sortedLevels = $this->sortByDistance($levels, $context);

        $locationIds = $sortedLevels->pluck('location_id')->unique()->values();

        return InventoryLocation::whereIn('id', $locationIds)
            ->get()
            ->sortBy(function ($location) use ($locationIds) {
                return $locationIds->search($location->id);
            })
            ->values();
    }

    /**
     * Get locations sorted by distance from origin.
     *
     * @return Collection<int, InventoryLocation>
     */
    public function getLocationsByDistance(float $originX, float $originY, ?float $originZ = null): Collection
    {
        $locations = InventoryLocation::query()
            ->where('is_active', true)
            ->whereNotNull('coordinate_x')
            ->whereNotNull('coordinate_y')
            ->get();

        return $locations->sortBy(function ($location) use ($originX, $originY, $originZ) {
            return $this->calculateDistance(
                $originX,
                $originY,
                $originZ,
                $location->coordinate_x,
                $location->coordinate_y,
                $location->coordinate_z
            );
        })->values();
    }

    /**
     * @return Collection<int, InventoryLevel>
     */
    private function getAvailableLevels(Model $model, AllocationContext $context): Collection
    {
        $query = InventoryLevel::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey())
            ->where('quantity_available', '>', 0)
            ->with('location');

        if ($context->excludeLocationIds !== null) {
            $query->whereNotIn('location_id', $context->excludeLocationIds);
        }

        if ($context->preferLocationIds !== null) {
            $preferred = $context->preferLocationIds;
            $query->orderByRaw('FIELD(location_id, ?) DESC', [implode(',', $preferred)]);
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, InventoryLevel>  $levels
     * @return Collection<int, InventoryLevel>
     */
    private function sortByDistance(Collection $levels, AllocationContext $context): Collection
    {
        if (! $context->hasOriginCoordinates()) {
            return $levels->sortBy(function ($level) {
                return $level->location?->pick_sequence ?? 0;
            });
        }

        return $levels->sortBy(function ($level) use ($context) {
            $location = $level->location;
            if (! $location || $location->coordinate_x === null || $location->coordinate_y === null) {
                return PHP_FLOAT_MAX;
            }

            return $this->calculateDistance(
                $context->originX,
                $context->originY,
                $context->originZ,
                $location->coordinate_x,
                $location->coordinate_y,
                $location->coordinate_z
            );
        });
    }

    private function calculateDistance(
        ?float $x1,
        ?float $y1,
        ?float $z1,
        ?float $x2,
        ?float $y2,
        ?float $z2
    ): float {
        if ($x1 === null || $y1 === null || $x2 === null || $y2 === null) {
            return PHP_FLOAT_MAX;
        }

        $dx = $x2 - $x1;
        $dy = $y2 - $y1;
        $dz = ($z2 ?? 0) - ($z1 ?? 0);

        return sqrt($dx * $dx + $dy * $dy + $dz * $dz);
    }

    /**
     * @param  Collection<int, InventoryLevel>  $levels
     * @return array<int, array{location_id: string, quantity: int, distance: float}>
     */
    private function buildAllocations(Collection $levels, int $quantity, AllocationContext $context): array
    {
        $allocations = [];
        $remaining = $quantity;
        $locationCount = 0;

        foreach ($levels as $level) {
            if ($remaining <= 0) {
                break;
            }

            if ($context->maxLocations !== null && $locationCount >= $context->maxLocations) {
                break;
            }

            $available = $level->quantity_available;
            $allocate = min($available, $remaining);

            if ($allocate > 0) {
                $location = $level->location;
                $distance = 0.0;

                if ($context->hasOriginCoordinates() && $location) {
                    $distance = $this->calculateDistance(
                        $context->originX,
                        $context->originY,
                        $context->originZ,
                        $location->coordinate_x,
                        $location->coordinate_y,
                        $location->coordinate_z
                    );
                }

                $allocations[] = [
                    'location_id' => $level->location_id,
                    'quantity' => $allocate,
                    'distance' => $distance,
                ];

                $remaining -= $allocate;
                $locationCount++;
            }
        }

        return $allocations;
    }
}
