<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Services;

use AIArmada\Inventory\Enums\BackorderPriority;
use AIArmada\Inventory\Models\InventoryBackorder;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\States\Pending;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class BackorderService
{
    /**
     * Create a new backorder.
     */
    public function create(
        Model $model,
        int $quantity,
        ?string $locationId = null,
        ?string $orderId = null,
        ?string $customerId = null,
        BackorderPriority $priority = BackorderPriority::Normal,
        ?Carbon $promisedAt = null,
        ?string $notes = null
    ): InventoryBackorder {
        if ($locationId !== null && InventoryOwnerScope::isEnabled()) {
            $isAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query()->whereKey($locationId))->exists();

            if (! $isAllowed) {
                throw new InvalidArgumentException('Invalid location for current owner');
            }
        }

        return InventoryBackorder::create([
            'inventoryable_type' => $model->getMorphClass(),
            'inventoryable_id' => $model->getKey(),
            'location_id' => $locationId,
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'quantity_requested' => $quantity,
            'quantity_fulfilled' => 0,
            'quantity_cancelled' => 0,
            'status' => Pending::class,
            'priority' => $priority,
            'requested_at' => now(),
            'promised_at' => $promisedAt,
            'notes' => $notes,
        ]);
    }

    /**
     * Fulfill backorder when stock becomes available.
     */
    public function fulfill(InventoryBackorder $backorder, int $quantity): bool
    {
        return $backorder->fulfill($quantity);
    }

    /**
     * Cancel a backorder.
     */
    public function cancel(InventoryBackorder $backorder, ?int $quantity = null, ?string $reason = null): bool
    {
        return $backorder->cancel($quantity, $reason);
    }

    /**
     * Get open backorders for a model.
     *
     * @return Collection<int, InventoryBackorder>
     */
    public function getOpenBackorders(Model $model): Collection
    {
        $query = InventoryBackorder::query()
            ->forModel($model)
            ->open()
            ->byPriority()
            ->orderBy('requested_at');

        $this->applyOwnerScopeToBackorderQuery($query);

        return $query->get();
    }

    /**
     * Get all open backorders by priority.
     *
     * @return Collection<int, InventoryBackorder>
     */
    public function getAllOpenBackorders(): Collection
    {
        $query = InventoryBackorder::query()
            ->open()
            ->byPriority()
            ->orderBy('requested_at');

        $this->applyOwnerScopeToBackorderQuery($query);

        return $query->get();
    }

    /**
     * Get overdue backorders.
     *
     * @return Collection<int, InventoryBackorder>
     */
    public function getOverdueBackorders(): Collection
    {
        $query = InventoryBackorder::query()
            ->overdue()
            ->byPriority();

        $this->applyOwnerScopeToBackorderQuery($query);

        return $query->get();
    }

    /**
     * Get backorders due within days.
     *
     * @return Collection<int, InventoryBackorder>
     */
    public function getBackordersDueWithin(int $days): Collection
    {
        $query = InventoryBackorder::query()
            ->dueWithin($days)
            ->byPriority();

        $this->applyOwnerScopeToBackorderQuery($query);

        return $query->get();
    }

    /**
     * Auto-fulfill backorders when stock is received.
     *
     * @return array{fulfilled: int, backorders_updated: int}
     */
    public function autoFulfill(Model $model, int $availableQuantity, ?string $locationId = null): array
    {
        return DB::transaction(function () use ($model, $availableQuantity, $locationId): array {
            $query = InventoryBackorder::query()
                ->forModel($model)
                ->open()
                ->byPriority()
                ->orderBy('requested_at');

            $this->applyOwnerScopeToBackorderQuery($query);

            if ($locationId !== null) {
                if (InventoryOwnerScope::isEnabled()) {
                    $isAllowed = InventoryOwnerScope::applyToLocationQuery(InventoryLocation::query()->whereKey($locationId))->exists();

                    if (! $isAllowed) {
                        throw new InvalidArgumentException('Invalid location for current owner');
                    }
                }

                $query->where(function ($q) use ($locationId): void {
                    $q->where('location_id', $locationId)
                        ->orWhereNull('location_id');
                });
            }

            $backorders = $query->get();

            $totalFulfilled = 0;
            $backordersUpdated = 0;
            $remaining = $availableQuantity;

            foreach ($backorders as $backorder) {
                if ($remaining <= 0) {
                    break;
                }

                $needed = $backorder->quantityRemaining();
                $fulfillQty = min($needed, $remaining);

                if ($fulfillQty > 0 && $backorder->fulfill($fulfillQty)) {
                    $totalFulfilled += $fulfillQty;
                    $remaining -= $fulfillQty;
                    $backordersUpdated++;
                }
            }

            return [
                'fulfilled' => $totalFulfilled,
                'backorders_updated' => $backordersUpdated,
            ];
        });
    }

    /**
     * Escalate overdue backorders.
     */
    public function escalateOverdue(): int
    {
        $overdue = $this->getOverdueBackorders();
        $escalated = 0;

        foreach ($overdue as $backorder) {
            if ($backorder->priority !== BackorderPriority::Urgent) {
                $backorder->escalate();
                $escalated++;
            }
        }

        return $escalated;
    }

    /**
     * Expire old backorders.
     */
    public function expireOld(int $daysOld = 90): int
    {
        $cutoff = now()->subDays($daysOld);

        $oldBackordersQuery = InventoryBackorder::query()
            ->open()
            ->where('requested_at', '<', $cutoff);

        $this->applyOwnerScopeToBackorderQuery($oldBackordersQuery);

        $oldBackorders = $oldBackordersQuery->get();

        $expired = 0;

        foreach ($oldBackorders as $backorder) {
            if ($backorder->expire()) {
                $expired++;
            }
        }

        return $expired;
    }

    /**
     * Get backorder statistics.
     *
     * @return array{total_open: int, total_quantity: int, overdue: int, by_priority: array<string, int>}
     */
    public function getStatistics(): array
    {
        $openBackordersQuery = InventoryBackorder::query()->open();
        $this->applyOwnerScopeToBackorderQuery($openBackordersQuery);

        $openBackorders = $openBackordersQuery->get();

        $byPriority = [];
        foreach (BackorderPriority::cases() as $priority) {
            $byPriority[$priority->value] = $openBackorders
                ->where('priority', $priority)
                ->count();
        }

        return [
            'total_open' => $openBackorders->count(),
            'total_quantity' => $openBackorders->sum(fn ($b) => $b->quantityRemaining()),
            'overdue' => $openBackorders->filter(fn ($b) => $b->isOverdue())->count(),
            'by_priority' => $byPriority,
        ];
    }

    /**
     * Get backorders that can be fulfilled with current inventory.
     *
     * @return Collection<int, InventoryBackorder>
     */
    public function getFulfillableBackorders(): Collection
    {
        $backordersQuery = InventoryBackorder::query()
            ->open()
            ->byPriority()
            ->orderBy('requested_at');

        $this->applyOwnerScopeToBackorderQuery($backordersQuery);

        $backorders = $backordersQuery->get();

        return $backorders->filter(function ($backorder): bool {
            $query = InventoryLevel::query()
                ->where('inventoryable_type', $backorder->inventoryable_type)
                ->where('inventoryable_id', $backorder->inventoryable_id);

            if ($backorder->location_id !== null) {
                $query->where('location_id', $backorder->location_id);
            }

            InventoryOwnerScope::applyToQueryByLocationRelation($query, 'location');

            // quantity_available is computed as (quantity_on_hand - quantity_reserved)
            $available = (int) $query->sum(DB::raw('quantity_on_hand - quantity_reserved'));

            return $available >= $backorder->quantityRemaining();
        });
    }

    /**
     * Update promised date.
     */
    public function updatePromisedDate(InventoryBackorder $backorder, Carbon $promisedAt): bool
    {
        return $backorder->update(['promised_at' => $promisedAt]);
    }

    /**
     * Get total backordered quantity for a model.
     */
    public function getTotalBackorderedQuantity(Model $model): int
    {
        $query = InventoryBackorder::query()
            ->forModel($model)
            ->open()
            ->selectRaw('SUM(quantity_requested - quantity_fulfilled - quantity_cancelled) as total');

        $this->applyOwnerScopeToBackorderQuery($query);

        return (int) $query->value('total');
    }

    /**
     * @param  Builder<InventoryBackorder>  $query
     */
    private function applyOwnerScopeToBackorderQuery(Builder $query): void
    {
        if (! InventoryOwnerScope::isEnabled()) {
            return;
        }

        $includeNullLocation = InventoryOwnerScope::includeGlobal() || InventoryOwnerScope::resolveOwner() === null;

        $query->where(function (Builder $builder) use ($includeNullLocation): void {
            $builder->whereHas('location', fn (Builder $locationQuery): Builder => InventoryOwnerScope::applyToLocationQuery($locationQuery));

            if ($includeNullLocation) {
                $builder->orWhereNull('location_id');
            }
        });
    }
}
