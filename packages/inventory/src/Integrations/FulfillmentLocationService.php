<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Integrations;

use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use AIArmada\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Provides fulfillment location suggestions for orders.
 *
 * This service helps shipping packages determine the optimal warehouse
 * or fulfillment location to ship from based on inventory availability.
 *
 * @example Get best location to fulfill an order
 * ```php
 * $fulfillment = app(FulfillmentLocationService::class);
 * $location = $fulfillment->getBestFulfillmentLocation($order);
 *
 * if ($location) {
 *     // Use $location->id as origin_location_id for shipping
 * }
 * ```
 * @example Check if order can be fulfilled from single location
 * ```php
 * $canFulfill = $fulfillment->canFulfillFromSingleLocation($order);
 * if (!$canFulfill) {
 *     // May need to split shipment across locations
 * }
 * ```
 */
final class FulfillmentLocationService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
    ) {}

    /**
     * Get the best fulfillment location for an order.
     *
     * Returns the location that can fulfill the most items with priority
     * given to locations that can fulfill the entire order.
     */
    public function getBestFulfillmentLocation(Order $order): ?InventoryLocation
    {
        $itemRequirements = $this->getItemRequirements($order);

        if ($itemRequirements->isEmpty()) {
            return null;
        }

        $locations = $this->getActiveLocations();
        $bestLocation = null;
        $bestScore = -1;

        foreach ($locations as $location) {
            $score = $this->calculateFulfillmentScore($location, $itemRequirements);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestLocation = $location;
            }
        }

        return $bestLocation;
    }

    /**
     * Check if the entire order can be fulfilled from a single location.
     */
    public function canFulfillFromSingleLocation(Order $order): bool
    {
        $itemRequirements = $this->getItemRequirements($order);

        if ($itemRequirements->isEmpty()) {
            return true;
        }

        $locations = $this->getActiveLocations();

        foreach ($locations as $location) {
            if ($this->locationCanFulfillAll($location, $itemRequirements)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get fulfillment plan showing which items ship from which locations.
     *
     * @return Collection<int, array{location: InventoryLocation, items: array<array{model: Model, quantity: int}>}>
     */
    public function getFulfillmentPlan(Order $order): Collection
    {
        $itemRequirements = $this->getItemRequirements($order);
        $locations = $this->getActiveLocations()->sortByDesc('priority');
        $plan = new Collection;
        $remaining = $itemRequirements->mapWithKeys(fn ($item) => [
            $item['key'] => $item['quantity'],
        ]);

        foreach ($locations as $location) {
            $locationItems = [];

            foreach ($itemRequirements as $item) {
                $key = $item['key'];
                $needed = $remaining->get($key, 0);

                if ($needed <= 0) {
                    continue;
                }

                $available = $this->getAvailableAtLocation($item['model'], $location->id);
                $fulfill = min($needed, $available);

                if ($fulfill > 0) {
                    $locationItems[] = [
                        'model' => $item['model'],
                        'quantity' => $fulfill,
                    ];
                    $remaining->put($key, $needed - $fulfill);
                }
            }

            if (count($locationItems) > 0) {
                $plan->push([
                    'location' => $location,
                    'items' => $locationItems,
                ]);
            }
        }

        return $plan;
    }

    /**
     * Get availability summary for an order across all locations.
     *
     * @return array{
     *     fully_available: bool,
     *     total_available: int,
     *     total_required: int,
     *     by_item: array<string, array{required: int, available: int, shortage: int}>
     * }
     */
    public function getAvailabilitySummary(Order $order): array
    {
        $itemRequirements = $this->getItemRequirements($order);
        $totalRequired = 0;
        $totalAvailable = 0;
        $fullyAvailable = true;
        $byItem = [];

        foreach ($itemRequirements as $item) {
            $required = $item['quantity'];
            $available = $this->inventoryService->getTotalAvailable($item['model']);
            $shortage = max(0, $required - $available);

            $totalRequired += $required;
            $totalAvailable += min($required, $available);

            if ($shortage > 0) {
                $fullyAvailable = false;
            }

            $byItem[$item['key']] = [
                'required' => $required,
                'available' => $available,
                'shortage' => $shortage,
            ];
        }

        return [
            'fully_available' => $fullyAvailable,
            'total_available' => $totalAvailable,
            'total_required' => $totalRequired,
            'by_item' => $byItem,
        ];
    }

    /**
     * Extract item requirements from order.
     *
     * @return Collection<int, array{model: Model, quantity: int, key: string}>
     */
    private function getItemRequirements(Order $order): Collection
    {
        return $order->items
            ->filter(fn ($item) => $item->purchasable instanceof Model)
            ->filter(fn ($item) => $this->tracksInventory($item->purchasable))
            ->map(fn ($item) => [
                'model' => $item->purchasable,
                'quantity' => $item->quantity,
                'key' => $item->purchasable->getMorphClass() . ':' . $item->purchasable->getKey(),
            ]);
    }

    /**
     * Get active inventory locations.
     *
     * @return Collection<int, InventoryLocation>
     */
    private function getActiveLocations(): Collection
    {
        return InventoryOwnerScope::applyToLocationQuery(
            InventoryLocation::query()
                ->where('is_active', true)
                ->orderByDesc('priority')
        )->get();
    }

    /**
     * Calculate a score for how well a location can fulfill requirements.
     *
     * @param  Collection<int, array{model: Model, quantity: int, key: string}>  $requirements
     */
    private function calculateFulfillmentScore(InventoryLocation $location, Collection $requirements): int
    {
        $score = 0;
        $canFulfillAll = true;

        foreach ($requirements as $item) {
            $available = $this->getAvailableAtLocation($item['model'], $location->id);
            $needed = $item['quantity'];

            if ($available >= $needed) {
                $score += $needed * 100; // Full fulfillment bonus
            } else {
                $score += $available;
                $canFulfillAll = false;
            }
        }

        // Bonus for locations that can fulfill everything
        if ($canFulfillAll) {
            $score += 10000;
        }

        // Factor in location priority
        $score += $location->priority ?? 0;

        return $score;
    }

    /**
     * Check if a location can fulfill all requirements.
     *
     * @param  Collection<int, array{model: Model, quantity: int, key: string}>  $requirements
     */
    private function locationCanFulfillAll(InventoryLocation $location, Collection $requirements): bool
    {
        foreach ($requirements as $item) {
            $available = $this->getAvailableAtLocation($item['model'], $location->id);

            if ($available < $item['quantity']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get available quantity at a specific location.
     */
    private function getAvailableAtLocation(Model $model, string $locationId): int
    {
        $level = InventoryLevel::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey())
            ->where('location_id', $locationId)
            ->first();

        return $level?->available ?? 0;
    }

    /**
     * Check if a model tracks inventory.
     */
    private function tracksInventory(Model $model): bool
    {
        if (method_exists($model, 'tracksInventory')) {
            return $model->tracksInventory();
        }

        return true;
    }
}
