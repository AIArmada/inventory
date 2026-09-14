<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services;

use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\InventoryAdjusted;
use AIArmada\Inventory\Events\InventoryReceived;
use AIArmada\Inventory\Events\InventoryShipped;
use AIArmada\Inventory\Events\InventoryTransferred;
use AIArmada\Inventory\Events\LowInventoryDetected;
use AIArmada\Inventory\Events\OutOfInventory;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

/**
 * Core inventory operations service.
 *
 * Handles receiving, shipping, transferring, and adjusting inventory
 * with full movement tracking and event dispatching.
 *
 * @example Receiving inventory
 * ```php
 * $service = app(InventoryService::class);
 * $movement = $service->receive($product, $location->id, 100, reason: 'PO-2024-001');
 * ```
 * @example Shipping inventory
 * ```php
 * $movement = $service->ship($product, $location->id, 10, reason: 'ORD-2024-001');
 * ```
 * @example Checking availability
 * ```php
 * $availability = $service->getAvailability($product);
 * // ['total' => 100, 'reserved' => 20, 'available' => 80, 'locations' => [...]]
 * ```
 */
final class InventoryService
{
    /**
     * Receive inventory at a location.
     *
     * Creates an inventory level record if one doesn't exist, increments
     * on-hand quantity, and records the movement for audit purposes.
     *
     * @param  Model  $model  The inventoryable model (e.g., Product)
     * @param  string  $locationId  UUID of the receiving location
     * @param  int  $quantity  Positive quantity to receive
     * @param  string|null  $reason  Reference number (e.g., PO number)
     * @param  string|null  $note  Additional notes
     * @param  string|null  $userId  ID of user performing the action
     * @param  DateTimeInterface|null  $occurredAt  When the receipt occurred (defaults to now)
     * @return InventoryMovement The created movement record
     *
     * @throws InvalidArgumentException If quantity is not positive
     */
    public function receive(
        Model $model,
        string $locationId,
        int $quantity,
        ?string $reason = null,
        ?string $note = null,
        ?string $userId = null,
        ?DateTimeInterface $occurredAt = null,
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
                'occurred_at' => $occurredAt ?? CarbonImmutable::now(),
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
        ?DateTimeInterface $occurredAt = null,
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive');
        }

        return DB::transaction(function () use ($model, $locationId, $quantity, $reason, $reference, $note, $userId, $occurredAt): InventoryMovement {
            $levelQuery = InventoryOwnerScope::applyToLocationQuery(
                InventoryLevel::query()
                    ->where('inventoryable_type', $model->getMorphClass())
                    ->where('inventoryable_id', $model->getKey())
                    ->where('location_id', $locationId)
            );

            $level = $levelQuery->lockForUpdate()->first();

            $available = $level?->available ?? 0;

            if ($level === null || $available < $quantity) {
                throw InsufficientInventoryException::forLocation($locationId, $quantity, $available);
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
                'occurred_at' => $occurredAt ?? CarbonImmutable::now(),
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
        ?DateTimeInterface $occurredAt = null,
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be positive');
        }

        if ($fromLocationId === $toLocationId) {
            throw new InvalidArgumentException('Source and destination locations must be different');
        }

        return DB::transaction(function () use ($model, $fromLocationId, $toLocationId, $quantity, $note, $userId, $occurredAt): InventoryMovement {
            $fromLevelQuery = InventoryOwnerScope::applyToLocationQuery(
                InventoryLevel::query()
                    ->where('inventoryable_type', $model->getMorphClass())
                    ->where('inventoryable_id', $model->getKey())
                    ->where('location_id', $fromLocationId)
            );

            $fromLevel = $fromLevelQuery->lockForUpdate()->first();

            $available = $fromLevel?->available ?? 0;

            if ($fromLevel === null || $available < $quantity) {
                throw InsufficientInventoryException::forLocation($fromLocationId, $quantity, $available);
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
                'occurred_at' => $occurredAt ?? CarbonImmutable::now(),
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
        ?DateTimeInterface $occurredAt = null,
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

            // Ledger path: the movement below is the audit record, so the
            // level's direct-write auto-audit must stand down.
            $level->suppressLedgerAudit = true;
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
                'occurred_at' => $occurredAt ?? CarbonImmutable::now(),
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
        $rows = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->whereHas('location', fn (Builder $query): Builder => $query->where('is_active', true))
        )
            ->select('location_id')
            ->selectRaw($this->availableAggregateExpression() . ' as available_total')
            ->groupBy('location_id')
            ->get();

        $availability = [];

        foreach ($rows as $row) {
            $availability[$row->location_id] = (int) $row->getAttribute('available_total');
        }

        return $availability;
    }

    /**
     * Get total available quantity across all locations.
     */
    public function getTotalAvailable(Model $model): int
    {
        $total = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->whereHas('location', fn (Builder $query): Builder => $query->where('is_active', true))
        )->sum(DB::raw($this->availablePerLevelExpression()));

        return (int) $total;
    }

    /**
     * Get total available quantities for many models with one query.
     *
     * Powers per-variant fan-out reads (product pages, carts) without the
     * N+1 cost of calling getTotalAvailable() per variant. Models without
     * any active-location level are reported as zero.
     *
     * @param  iterable<int, Model>  $models
     * @return array<string, int> Keyed by "{morph-class}:{key}".
     */
    public function getAvailabilityForMany(iterable $models): array
    {
        $pairs = [];

        foreach ($models as $model) {
            $pairs[$model->getMorphClass() . ':' . $model->getKey()] = [
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
            ];
        }

        $totals = array_fill_keys(array_keys($pairs), 0);

        if ($pairs === []) {
            return $totals;
        }

        $rows = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
                ->whereIn('inventoryable_type', array_unique(array_column($pairs, 'inventoryable_type')))
                ->whereIn('inventoryable_id', array_unique(array_column($pairs, 'inventoryable_id')))
                ->whereHas('location', fn (Builder $query): Builder => $query->where('is_active', true))
        )
            ->select('inventoryable_type', 'inventoryable_id')
            ->selectRaw($this->availableAggregateExpression() . ' as available_total')
            ->groupBy('inventoryable_type', 'inventoryable_id')
            ->get();

        foreach ($rows as $row) {
            $key = $row->inventoryable_type . ':' . $row->inventoryable_id;

            if (array_key_exists($key, $totals)) {
                $totals[$key] = (int) $row->getAttribute('available_total');
            }
        }

        return $totals;
    }

    /**
     * Per-level available quantity, mirroring InventoryLevel::available
     * (never negative) in portable SQL.
     */
    private function availablePerLevelExpression(): string
    {
        return 'CASE WHEN quantity_on_hand > quantity_reserved THEN quantity_on_hand - quantity_reserved ELSE 0 END';
    }

    private function availableAggregateExpression(): string
    {
        return 'SUM(' . $this->availablePerLevelExpression() . ')';
    }

    /**
     * Get total on-hand quantity across all locations.
     */
    public function getTotalOnHand(Model $model): int
    {
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->whereHas('location', fn (Builder $locationQuery): Builder => $locationQuery->where('is_active', true))
        );

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
        // No-op: availability is derived from fresh queries to remain safe under long-lived workers.
    }

    /**
     * Get inventory level at a specific location.
     */
    public function getLevel(Model $model, string $locationId): ?InventoryLevel
    {
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->where('location_id', $locationId)
        );

        return $query->first();
    }

    /**
     * Get or create inventory level at a location.
     */
    public function getOrCreateLevel(Model $model, string $locationId): InventoryLevel
    {
        if (InventoryOwnerScope::isEnabled()) {
            $locationExists = InventoryOwnerScope::applyToLocationQuery(
                InventoryLocation::query()->whereKey($locationId)
            )->exists();

            if (! $locationExists) {
                throw new InvalidArgumentException('Invalid location for current owner');
            }
        }

        $identity = [
            'inventoryable_type' => $model->getMorphClass(),
            'inventoryable_id' => $model->getKey(),
            'location_id' => $locationId,
        ];

        try {
            return InventoryLevel::firstOrCreate(
                $identity,
                [
                    'quantity_on_hand' => 0,
                    'quantity_reserved' => 0,
                ]
            );
        } catch (QueryException $exception) {
            // The framework already rescues plain unique races, but its
            // recovery lookup is over-constrained by the mutable quantity
            // defaults. Re-select by identity so a loser never 500s when
            // the winner's row has since moved.
            if (! in_array((string) ($exception->errorInfo[0] ?? $exception->getCode()), ['23000', '23505'], true)) {
                throw $exception;
            }

            $level = InventoryLevel::query()->where($identity)->first();

            if (! $level instanceof InventoryLevel) {
                throw $exception;
            }

            return $level;
        }
    }

    /**
     * Get movement history for a model.
     *
     * @return Collection<int, InventoryMovement>
     */
    public function getMovementHistory(Model $model, int $limit = 50): Collection
    {
        $query = InventoryMovement::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey());

        InventoryOwnerScope::applyToMovementQuery($query);

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
