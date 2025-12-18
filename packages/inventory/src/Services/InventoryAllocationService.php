<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Inventory\Enums\AllocationStrategy;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\InventoryAllocated;
use AIArmada\Inventory\Events\InventoryReleased;
use AIArmada\Inventory\Events\LowInventoryDetected;
use AIArmada\Inventory\Events\OutOfInventory;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class InventoryAllocationService
{
    public function __construct(
        private InventoryService $inventoryService,
        private readonly OwnerResolverInterface $ownerResolver,
    ) {}

    /**
     * Allocate inventory for a cart.
     *
     * @return Collection<int, InventoryAllocation>
     */
    public function allocate(
        Model $model,
        int $quantity,
        string $cartId,
        int $ttlMinutes = 30
    ): Collection {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive');
        }

        return DB::transaction(function () use ($model, $quantity, $cartId, $ttlMinutes): Collection {
            // Release any existing allocations for this model/cart combo
            $this->release($model, $cartId);

            $strategy = $this->getStrategy($model);
            $allowSplit = $strategy->allowsSplit() && config('inventory.allow_split_allocation', true);

            $levels = $this->getLevelsForAllocation($model, $strategy);
            /** @var Collection<int, InventoryAllocation> $allocations */
            $allocations = new Collection;
            $remaining = $quantity;
            $expiresAt = now()->addMinutes($ttlMinutes);

            foreach ($levels as $level) {
                if ($remaining <= 0) {
                    break;
                }

                $available = $level->available;

                if ($available <= 0) {
                    continue;
                }

                $toAllocate = $allowSplit ? min($remaining, $available) : ($available >= $remaining ? $remaining : 0);

                if ($toAllocate <= 0) {
                    continue;
                }

                // Create allocation record
                $allocation = InventoryAllocation::create([
                    'inventoryable_type' => $model->getMorphClass(),
                    'inventoryable_id' => $model->getKey(),
                    'location_id' => $level->location_id,
                    'level_id' => $level->id,
                    'cart_id' => $cartId,
                    'quantity' => $toAllocate,
                    'expires_at' => $expiresAt,
                ]);

                // Update reserved quantity
                $level->incrementReserved($toAllocate);

                $allocations->push($allocation);
                $remaining -= $toAllocate;

                // For single location strategy, break after first allocation
                if (! $allowSplit) {
                    break;
                }
            }

            if ($remaining > 0) {
                // Rollback all allocations if we couldn't fulfill the full quantity
                foreach ($allocations as $allocation) {
                    $allocation->level->decrementReserved($allocation->quantity);
                    $allocation->delete();
                }

                $allocatedQuantity = $quantity - $remaining;

                throw new InsufficientInventoryException(
                    sprintf('Insufficient inventory. Requested: %d, Available: %d', $quantity, $allocatedQuantity),
                    (string) $model->getKey(),
                    $quantity,
                    $allocatedQuantity
                );
            }

            if ($allocations->isNotEmpty()) {
                $this->inventoryService->clearCache($model);
                Event::dispatch(new InventoryAllocated($model, $allocations, $cartId));
            }

            return $allocations;
        });
    }

    /**
     * Release a single allocation directly.
     */
    public function releaseAllocation(InventoryAllocation $allocation): int
    {
        return DB::transaction(function () use ($allocation): int {
            $allocationQuery = InventoryAllocation::query()
                ->whereKey($allocation->getKey())
                ->lockForUpdate()
                ->with('location');

            $lockedAllocation = InventoryOwnerScope::applyToQueryByLocationRelation($allocationQuery)->first();

            if ($lockedAllocation === null) {
                return 0;
            }

            // Ensure relationships are loaded
            $lockedAllocation->loadMissing(['level', 'inventoryable']);

            $quantity = $lockedAllocation->quantity;
            $lockedAllocation->level->decrementReserved($quantity);

            $inventoryable = $lockedAllocation->inventoryable;
            $cartId = $lockedAllocation->cart_id;

            $lockedAllocation->delete();

            if ($inventoryable !== null) {
                $this->inventoryService->clearCache($inventoryable);
                Event::dispatch(new InventoryReleased($inventoryable, $quantity, $cartId));
            }

            return $quantity;
        });
    }

    /**
     * Release allocations for a model/cart.
     */
    public function release(Model $model, string $cartId): int
    {
        return DB::transaction(function () use ($model, $cartId): int {
            $allocationsQuery = InventoryAllocation::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->forCart($cartId)
                ->with('location')
                ->lockForUpdate()
                ;

            $allocations = InventoryOwnerScope::applyToQueryByLocationRelation($allocationsQuery)->get();

            $totalReleased = 0;

            foreach ($allocations as $allocation) {
                $allocation->level->decrementReserved($allocation->quantity);
                $totalReleased += $allocation->quantity;
                $allocation->delete();
            }

            if ($totalReleased > 0) {
                $this->inventoryService->clearCache($model);
                Event::dispatch(new InventoryReleased($model, $totalReleased, $cartId));
            }

            return $totalReleased;
        });
    }

    /**
     * Release all allocations for a cart (across all products).
     */
    public function releaseAllForCart(string $cartId): int
    {
        return DB::transaction(function () use ($cartId): int {
            $allocationsQuery = InventoryAllocation::query()
                ->forCart($cartId)
                ->with('level')
                ->with('location')
                ->lockForUpdate()
                ;

            $allocations = InventoryOwnerScope::applyToQueryByLocationRelation($allocationsQuery)->get();

            $totalReleased = 0;

            foreach ($allocations as $allocation) {
                $allocation->level->decrementReserved($allocation->quantity);
                $totalReleased += $allocation->quantity;
                $allocation->delete();
            }

            if ($totalReleased > 0) {
                $this->inventoryService->clearCache();
            }

            return $totalReleased;
        });
    }

    /**
     * Commit allocations (convert to shipments after payment).
     *
     * @return array<InventoryMovement>
     */
    public function commit(string $cartId, ?string $orderId = null): array
    {
        return DB::transaction(function () use ($cartId, $orderId): array {
            $allocationsQuery = InventoryAllocation::query()
                ->forCart($cartId)
                ->with(['level', 'inventoryable'])
                ->with('location')
                ->lockForUpdate()
                ;

            $allocations = InventoryOwnerScope::applyToQueryByLocationRelation($allocationsQuery)->get();

            $movements = [];

            foreach ($allocations as $allocation) {
                // Decrease on-hand (the reserved is already holding it)
                $allocation->level->decrementOnHand($allocation->quantity);
                $allocation->level->decrementReserved($allocation->quantity);

                // Create shipment movement
                $movement = InventoryMovement::create([
                    'inventoryable_type' => $allocation->inventoryable_type,
                    'inventoryable_id' => $allocation->inventoryable_id,
                    'from_location_id' => $allocation->location_id,
                    'quantity' => $allocation->quantity,
                    'type' => MovementType::Shipment->value,
                    'reason' => 'sale',
                    'reference' => $orderId,
                    'note' => sprintf('Committed from cart %s', $cartId),
                    'occurred_at' => now(),
                ]);

                $movements[] = $movement;

                // Check low inventory
                $this->checkLowInventory($allocation->inventoryable, $allocation->level);

                $allocation->delete();
            }

            if (count($movements) > 0) {
                $this->inventoryService->clearCache();
            }

            return $movements;
        });
    }

    /**
     * Extend allocations for a cart.
     */
    public function extendAllocations(string $cartId, int $minutes): int
    {
        $newExpiry = now()->addMinutes($minutes);

        $query = InventoryOwnerScope::applyToQueryByLocationRelation(
            InventoryAllocation::query()->forCart($cartId)->with('location')
        );

        return $query->update(['expires_at' => $newExpiry]);
    }

    /**
     * Get allocations for a cart.
     *
     * @return Collection<int, InventoryAllocation>
     */
    public function getAllocationsForCart(string $cartId): Collection
    {
        $query = InventoryAllocation::query()
            ->forCart($cartId)
            ->active()
            ->with(['location', 'level', 'inventoryable']);

        return InventoryOwnerScope::applyToQueryByLocationRelation($query)->get();
    }

    /**
     * Get allocation for a specific model/cart.
     *
     * @return Collection<int, InventoryAllocation>
     */
    public function getAllocations(Model $model, string $cartId): Collection
    {
        $query = InventoryAllocation::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey())
            ->forCart($cartId)
            ->active()
            ->with('location');

        return InventoryOwnerScope::applyToQueryByLocationRelation($query)->get();
    }

    /**
     * Check if a model has sufficient available inventory.
     */
    public function hasAvailableInventory(Model $model, int $quantity): bool
    {
        return $this->inventoryService->getTotalAvailable($model) >= $quantity;
    }

    /**
     * Get total available for a model (convenience method).
     */
    public function getTotalAvailable(Model $model): int
    {
        return $this->inventoryService->getTotalAvailable($model);
    }

    /**
     * Validate availability for multiple items.
     *
     * @param  array<array{model: Model, quantity: int}>  $items
     * @return array{available: bool, issues: array<array{model: Model, requested: int, available: int}>}
     */
    public function validateAvailability(array $items): array
    {
        $issues = [];
        $allAvailable = true;

        foreach ($items as $item) {
            $model = $item['model'];
            $requested = $item['quantity'];
            $available = $this->inventoryService->getTotalAvailable($model);

            if ($available < $requested) {
                $allAvailable = false;
                $issues[] = [
                    'model' => $model,
                    'requested' => $requested,
                    'available' => $available,
                ];
            }
        }

        return [
            'available' => $allAvailable,
            'issues' => $issues,
        ];
    }

    /**
     * Clean up expired allocations.
     */
    public function cleanupExpired(): int
    {
        return DB::transaction(function (): int {
            $allocationsQuery = InventoryAllocation::query()
                ->expired()
                ->with(['level', 'location'])
                ->lockForUpdate();

            $allocations = InventoryOwnerScope::applyToQueryByLocationRelation($allocationsQuery)->get();

            return $this->cleanupAllocationCollection($allocations);
        });
    }

    /**
     * Clean up expired allocations across all owners (maintenance).
     */
    public function cleanupExpiredGlobal(): int
    {
        return DB::transaction(function (): int {
            $allocations = InventoryAllocation::query()
                ->expired()
                ->with('level')
                ->lockForUpdate()
                ->get();

            return $this->cleanupAllocationCollection($allocations);
        });
    }

    /**
     * @param  Collection<int, InventoryAllocation>  $allocations
     */
    private function cleanupAllocationCollection(Collection $allocations): int
    {
        $count = 0;

        foreach ($allocations as $allocation) {
            $allocation->level->decrementReserved($allocation->quantity);
            $allocation->delete();
            $count++;
        }

        if ($count > 0) {
            $this->inventoryService->clearCache();
        }

        return $count;
    }

    /**
     * Get the effective allocation strategy for a model.
     */
    public function getStrategy(Model $model): AllocationStrategy
    {
        // Check if model implements InventoryableInterface and has custom strategy
        if (method_exists($model, 'getAllocationStrategy')) {
            $strategy = $model->getAllocationStrategy();

            if ($strategy !== null) {
                return $strategy;
            }
        }

        // Fall back to global config
        $global = config('inventory.allocation_strategy', 'priority');

        return AllocationStrategy::from($global);
    }

    /**
     * @return array{enabled: bool, owner: Model|null, includeGlobal: bool}
     */
    private function ownerScope(): array
    {
        $enabled = (bool) config('inventory.owner.enabled', false);

        if (! $enabled) {
            return [
                'enabled' => false,
                'owner' => null,
                'includeGlobal' => true,
            ];
        }

        return [
            'enabled' => true,
            'owner' => $this->ownerResolver->resolve(),
            'includeGlobal' => (bool) config('inventory.owner.include_global', true),
        ];
    }

    private function applyOwnerScopeToLocationQuery(Builder $query, array $scope): void
    {
        if (! $scope['enabled']) {
            return;
        }

        $owner = $scope['owner'];
        $includeGlobal = $scope['includeGlobal'];

        if ($owner === null) {
            $query->whereNull('owner_type')->whereNull('owner_id');

            return;
        }

        $query->where(function (Builder $builder) use ($owner, $includeGlobal): void {
            $builder->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey());

            if ($includeGlobal) {
                $builder->orWhere(function (Builder $inner): void {
                    $inner->whereNull('owner_type')->whereNull('owner_id');
                });
            }
        });
    }

    /**
     * Get inventory levels ordered by allocation strategy.
     *
     * @return Collection<int, InventoryLevel>
     */
    private function getLevelsForAllocation(Model $model, AllocationStrategy $strategy): Collection
    {
        $scope = $this->ownerScope();

        $query = InventoryLevel::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey())
            ->whereHas('location', function (Builder $query) use ($scope): void {
                $query->where('is_active', true);

                $this->applyOwnerScopeToLocationQuery($query, $scope);
            })
            ->with('location')
            ->lockForUpdate();

        return match ($strategy) {
            AllocationStrategy::Priority => $query
                ->join(
                    config('inventory.table_names.locations', 'inventory_locations'),
                    config('inventory.table_names.levels', 'inventory_levels') . '.location_id',
                    '=',
                    config('inventory.table_names.locations', 'inventory_locations') . '.id'
                )
                ->orderByDesc(config('inventory.table_names.locations', 'inventory_locations') . '.priority')
                ->select(config('inventory.table_names.levels', 'inventory_levels') . '.*')
                ->get(),

            AllocationStrategy::FIFO => $query
                ->orderBy('created_at')
                ->get(),

            AllocationStrategy::LeastStock => $query
                // Allocate from locations with MOST available stock first to balance inventory
                ->orderByRaw('(quantity_on_hand - quantity_reserved) DESC')
                ->get(),

            AllocationStrategy::SingleLocation => $query
                ->join(
                    config('inventory.table_names.locations', 'inventory_locations'),
                    config('inventory.table_names.levels', 'inventory_levels') . '.location_id',
                    '=',
                    config('inventory.table_names.locations', 'inventory_locations') . '.id'
                )
                ->orderByDesc(config('inventory.table_names.locations', 'inventory_locations') . '.priority')
                ->select(config('inventory.table_names.levels', 'inventory_levels') . '.*')
                ->get(),
        };
    }

    /**
     * Check and dispatch low inventory events.
     */
    private function checkLowInventory(Model $model, InventoryLevel $level): void
    {
        if (! config('inventory.events.low_inventory', true)) {
            return;
        }

        $level->refresh();

        if ($level->available === 0 && config('inventory.events.out_of_inventory', true)) {
            Event::dispatch(new OutOfInventory($model, $level));
        } elseif ($level->isLowStock()) {
            Event::dispatch(new LowInventoryDetected($model, $level));
        }
    }
}
