<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services;

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\InventoryAdjusted;
use AIArmada\Inventory\Events\InventoryReceived;
use AIArmada\Inventory\Events\InventoryShipped;
use AIArmada\Inventory\Events\InventoryTransferred;
use AIArmada\Inventory\Events\LowInventoryDetected;
use AIArmada\Inventory\Events\OutOfInventory;
use AIArmada\Inventory\Exceptions\InsufficientStockException;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class InventoryService
{
    /**
     * Cached availability data keyed by model identity.
     *
     * @var array<string, array<string, int>>
     */
    private array $availabilityCache = [];

    /**
     * Cached total available quantities keyed by model identity.
     *
     * @var array<string, int>
     */
    private array $totalAvailableCache = [];

    public function __construct(
        private readonly OwnerResolverInterface $ownerResolver,
    ) {}

    /**
     * Receive inventory at a location.
     */
    public function receive(
        Model $model,
        string $locationId,
        int $quantity,
        ?string $reason = null,
        ?string $note = null,
        ?string $userId = null,
        ?\DateTimeInterface $occurredAt = null,
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive');
        }

        return DB::transaction(function () use ($model, $locationId, $quantity, $reason, $note, $userId, $occurredAt): InventoryMovement {
            $level = $this->getOrCreateLevel($model, $locationId);
            $level->incrementOnHand($quantity);
            $this->clearCache($model);

            $movement = InventoryMovement::create([
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
                'to_location_id' => $locationId,
                'quantity' => $quantity,
                'type' => MovementType::Receipt->value,
                'reason' => $reason,
                'user_id' => $userId,
                'note' => $note,
                'occurred_at' => $occurredAt ?? now(),
            ]);

            Event::dispatch(new InventoryReceived($model, $level, $movement));

            return $movement;
        });
    }

    /**
     * Ship inventory from a location.
     */
    public function ship(
        Model $model,
        string $locationId,
        int $quantity,
        ?string $reason = null,
        ?string $reference = null,
        ?string $note = null,
        ?string $userId = null,
        ?\DateTimeInterface $occurredAt = null,
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive');
        }

        return DB::transaction(function () use ($model, $locationId, $quantity, $reason, $reference, $note, $userId, $occurredAt): InventoryMovement {
            $scope = $this->ownerScope();

            $levelQuery = InventoryLevel::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->where('location_id', $locationId);

            if ($scope['enabled']) {
                $levelQuery->whereHas('location', function (Builder $locationQuery) use ($scope): void {
                    $this->applyOwnerScopeToLocationQuery($locationQuery, $scope);
                });
            }

            $level = $levelQuery->lockForUpdate()->first();

            $available = $level?->available ?? 0;

            if ($level === null || $available < $quantity) {
                throw InsufficientStockException::forLocation($locationId, $quantity, $available);
            }

            $level->decrementOnHand($quantity);
            $this->clearCache($model);

            $movement = InventoryMovement::create([
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
                'from_location_id' => $locationId,
                'quantity' => $quantity,
                'type' => MovementType::Shipment->value,
                'reason' => $reason,
                'reference' => $reference,
                'user_id' => $userId,
                'note' => $note,
                'occurred_at' => $occurredAt ?? now(),
            ]);

            Event::dispatch(new InventoryShipped($model, $level, $movement));

            $this->checkLowInventory($model, $level);

            return $movement;
        });
    }

    /**
     * Transfer inventory between locations.
     */
    public function transfer(
        Model $model,
        string $fromLocationId,
        string $toLocationId,
        int $quantity,
        ?string $note = null,
        ?string $userId = null,
        ?\DateTimeInterface $occurredAt = null,
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive');
        }

        if ($fromLocationId === $toLocationId) {
            throw new InvalidArgumentException('Source and destination locations must be different');
        }

        return DB::transaction(function () use ($model, $fromLocationId, $toLocationId, $quantity, $note, $userId, $occurredAt): InventoryMovement {
            $scope = $this->ownerScope();

            $fromLevelQuery = InventoryLevel::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->where('location_id', $fromLocationId);

            if ($scope['enabled']) {
                $fromLevelQuery->whereHas('location', function (Builder $locationQuery) use ($scope): void {
                    $this->applyOwnerScopeToLocationQuery($locationQuery, $scope);
                });
            }

            $fromLevel = $fromLevelQuery->lockForUpdate()->first();

            $available = $fromLevel?->available ?? 0;

            if ($fromLevel === null || $available < $quantity) {
                throw InsufficientStockException::forLocation($fromLocationId, $quantity, $available);
            }

            $toLevel = $this->getOrCreateLevel($model, $toLocationId);

            $fromLevel->decrementOnHand($quantity);
            $toLevel->incrementOnHand($quantity);
            $this->clearCache($model);

            $movement = InventoryMovement::create([
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
                'from_location_id' => $fromLocationId,
                'to_location_id' => $toLocationId,
                'quantity' => $quantity,
                'type' => MovementType::Transfer->value,
                'user_id' => $userId,
                'note' => $note,
                'occurred_at' => $occurredAt ?? now(),
            ]);

            Event::dispatch(new InventoryTransferred($model, $fromLevel, $toLevel, $movement));

            $this->checkLowInventory($model, $fromLevel);

            return $movement;
        });
    }

    /**
     * Adjust inventory to a specific quantity at a location.
     */
    public function adjust(
        Model $model,
        string $locationId,
        int $newQuantity,
        ?string $reason = null,
        ?string $note = null,
        ?string $userId = null,
        ?\DateTimeInterface $occurredAt = null,
    ): InventoryMovement {
        if ($newQuantity < 0) {
            throw new InvalidArgumentException('New quantity cannot be negative');
        }

        return DB::transaction(function () use ($model, $locationId, $newQuantity, $reason, $note, $userId, $occurredAt): InventoryMovement {
            $level = $this->getOrCreateLevel($model, $locationId);
            $oldQuantity = $level->quantity_on_hand;
            $difference = $newQuantity - $oldQuantity;

            if ($difference === 0) {
                throw new InvalidArgumentException('No adjustment needed, quantities are equal');
            }

            $level->update(['quantity_on_hand' => $newQuantity]);
            $this->clearCache($model);

            $movement = InventoryMovement::create([
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
                'from_location_id' => $difference < 0 ? $locationId : null,
                'to_location_id' => $difference > 0 ? $locationId : null,
                'quantity' => abs($difference),
                'type' => MovementType::Adjustment->value,
                'reason' => $reason,
                'user_id' => $userId,
                'note' => $note ?? sprintf('Adjusted from %d to %d', $oldQuantity, $newQuantity),
                'occurred_at' => $occurredAt ?? now(),
            ]);

            Event::dispatch(new InventoryAdjusted($model, $level, $movement, $oldQuantity, $newQuantity));

            $this->checkLowInventory($model, $level);

            return $movement;
        });
    }

    /**
     * Get availability across all locations.
     *
     * Uses parameter-keyed caching to avoid redundant queries when the same
     * model is checked multiple times within a single request (e.g., during checkout).
     *
     * @return array<string, int> Location ID => available quantity
     */
    public function getAvailability(Model $model): array
    {
        $cacheKey = $this->availabilityCacheKey($model);
        $scope = $this->ownerScope();

        if (! isset($this->availabilityCache[$cacheKey])) {
            $this->availabilityCache[$cacheKey] = InventoryLevel::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->whereHas('location', function (Builder $query) use ($scope): void {
                    $query->where('is_active', true);
                    $this->applyOwnerScopeToLocationQuery($query, $scope);
                })
                ->get()
                ->mapWithKeys(fn (InventoryLevel $level): array => [$level->location_id => $level->available])
                ->toArray();
        }

        return $this->availabilityCache[$cacheKey];
    }

    /**
     * Get total available quantity across all locations.
     *
     * Uses parameter-keyed caching to avoid redundant queries when the same
     * model is checked multiple times within a single request.
     */
    public function getTotalAvailable(Model $model): int
    {
        $cacheKey = $this->availabilityCacheKey($model);
        $scope = $this->ownerScope();

        if (! isset($this->totalAvailableCache[$cacheKey])) {
            $this->totalAvailableCache[$cacheKey] = InventoryLevel::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->whereHas('location', function (Builder $query) use ($scope): void {
                    $query->where('is_active', true);
                    $this->applyOwnerScopeToLocationQuery($query, $scope);
                })
                ->get()
                ->sum(fn (InventoryLevel $level): int => $level->available);
        }

        return $this->totalAvailableCache[$cacheKey];
    }

    /**
     * Get total on-hand quantity across all locations.
     */
    public function getTotalOnHand(Model $model): int
    {
        $scope = $this->ownerScope();

        $query = InventoryLevel::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey())
            ->whereHas('location', function (Builder $locationQuery) use ($scope): void {
                $locationQuery->where('is_active', true);
                $this->applyOwnerScopeToLocationQuery($locationQuery, $scope);
            });

        return (int) $query->sum('quantity_on_hand');
    }

    /**
     * Check if sufficient inventory exists.
     *
     * Uses the cached getTotalAvailable() to avoid redundant queries.
     */
    public function hasInventory(Model $model, int $quantity): bool
    {
        return $this->getTotalAvailable($model) >= $quantity;
    }

    /**
     * Clear the availability cache for a specific model.
     *
     * Call this after inventory mutations (receive, ship, transfer, adjust)
     * if you need fresh data within the same request.
     */
    public function clearCache(?Model $model = null): void
    {
        if ($model === null) {
            $this->availabilityCache = [];
            $this->totalAvailableCache = [];
        } else {
            $modelCacheKey = $this->modelCacheKey($model);

            foreach (array_keys($this->availabilityCache) as $cacheKey) {
                if ($cacheKey === $modelCacheKey || str_starts_with($cacheKey, $modelCacheKey . '|')) {
                    unset($this->availabilityCache[$cacheKey]);
                }
            }

            foreach (array_keys($this->totalAvailableCache) as $cacheKey) {
                if ($cacheKey === $modelCacheKey || str_starts_with($cacheKey, $modelCacheKey . '|')) {
                    unset($this->totalAvailableCache[$cacheKey]);
                }
            }
        }
    }

    /**
     * Get inventory level at a specific location.
     */
    public function getLevel(Model $model, string $locationId): ?InventoryLevel
    {
        $scope = $this->ownerScope();

        $query = InventoryLevel::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey())
            ->where('location_id', $locationId);

        if ($scope['enabled']) {
            $query->whereHas('location', function (Builder $locationQuery) use ($scope): void {
                $this->applyOwnerScopeToLocationQuery($locationQuery, $scope);
            });
        }

        return $query->first();
    }

    /**
     * Get or create inventory level at a location.
     */
    public function getOrCreateLevel(Model $model, string $locationId): InventoryLevel
    {
        $scope = $this->ownerScope();

        if ($scope['enabled']) {
            $locationQuery = InventoryLocation::query()->whereKey($locationId);
            $this->applyOwnerScopeToLocationQuery($locationQuery, $scope);
            $locationExists = $locationQuery->exists();

            if (! $locationExists) {
                throw new InvalidArgumentException('Invalid location for current owner');
            }
        }

        return InventoryLevel::firstOrCreate(
            [
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
                'location_id' => $locationId,
            ],
            [
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
            ]
        );
    }

    /**
     * Get movement history for a model.
     *
     * @return Collection<int, InventoryMovement>
     */
    public function getMovementHistory(Model $model, int $limit = 50): Collection
    {
        $scope = $this->ownerScope();

        $query = InventoryMovement::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey());

        if ($scope['enabled']) {
            $query->where(function (Builder $builder) use ($scope): void {
                $builder
                    ->whereHas('fromLocation', function (Builder $locationQuery) use ($scope): void {
                        $this->applyOwnerScopeToLocationQuery($locationQuery, $scope);
                    })
                    ->orWhereHas('toLocation', function (Builder $locationQuery) use ($scope): void {
                        $this->applyOwnerScopeToLocationQuery($locationQuery, $scope);
                    });
            });
        }

        return $query
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Receive inventory at the default location (for simple setups).
     */
    public function receiveAtDefault(
        Model $model,
        int $quantity,
        ?string $reason = null,
        ?string $note = null,
        ?string $userId = null
    ): InventoryMovement {
        $location = InventoryLocation::getOrCreateDefault();

        return $this->receive($model, $location->id, $quantity, $reason, $note, $userId);
    }

    /**
     * Ship inventory from the default location (for simple setups).
     */
    public function shipFromDefault(
        Model $model,
        int $quantity,
        ?string $reason = null,
        ?string $reference = null,
        ?string $note = null,
        ?string $userId = null
    ): InventoryMovement {
        $location = InventoryLocation::getOrCreateDefault();

        return $this->ship($model, $location->id, $quantity, $reason, $reference, $note, $userId);
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

    private function modelCacheKey(Model $model): string
    {
        return $model->getMorphClass() . ':' . $model->getKey();
    }

    private function availabilityCacheKey(Model $model): string
    {
        $cacheKey = $this->modelCacheKey($model);
        $scope = $this->ownerScope();

        if (! $scope['enabled']) {
            return $cacheKey;
        }

        $owner = $scope['owner'];
        $ownerKey = $owner === null
            ? 'null'
            : $owner->getMorphClass() . ':' . $owner->getKey();

        return $cacheKey . '|owner=' . $ownerKey . '|includeGlobal=' . ($scope['includeGlobal'] ? '1' : '0');
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
