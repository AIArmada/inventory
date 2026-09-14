<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Listeners;

use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryOperation;
use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Inventory\Services\Stock\InventoryAllocationService;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use AIArmada\Orders\Events\InventoryDeductionRequired;
use AIArmada\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Deducts inventory when an order payment is confirmed.
 *
 * This listener bridges the orders package with inventory management.
 * It handles two scenarios:
 *
 * 1. **With cart allocations**: If inventory was pre-allocated during checkout
 *    (via cart integration), commit those allocations.
 *
 * 2. **Without allocations**: If the order was created directly without cart
 *    allocations (API, admin, etc.), deduct inventory directly.
 */
final class DeductInventoryFromOrder
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly InventoryAllocationService $allocationService,
    ) {}

    public function handle(InventoryDeductionRequired $event): void
    {
        $order = $event->order;

        if (! config('inventory.orders.enabled', true)) {
            return;
        }

        $operation = $this->resolveOrCreateOperation($order, InventoryOperation::KIND_DEDUCTION);

        if ($operation->status === InventoryOperation::STATUS_COMPLETED) {
            Log::info('Inventory deduction already completed for order', [
                'order_id' => $order->id,
                'operation_id' => $operation->id,
            ]);

            return;
        }

        DB::transaction(function () use ($order, $operation): void {
            $operation = InventoryOwnerScope::applyToLocationQuery(
                InventoryOperation::query()
                    ->whereKey($operation->id)
                    ->lockForUpdate()
            )->firstOrFail();

            if ($operation->status === InventoryOperation::STATUS_COMPLETED) {
                return;
            }

            $cartId = $this->extractCartId($order);

            if ($cartId !== null) {
                $committed = $this->tryCommitAllocations($cartId, $order);

                if ($committed) {
                    $operation->update([
                        'status' => InventoryOperation::STATUS_COMPLETED,
                        'completed_at' => CarbonImmutable::now(),
                    ]);

                    return;
                }
            }

            $this->deductDirectly($order);

            $operation->update([
                'status' => InventoryOperation::STATUS_COMPLETED,
                'completed_at' => CarbonImmutable::now(),
            ]);
        });
    }

    private function resolveOrCreateOperation(Order $order, string $kind): InventoryOperation
    {
        $existing = InventoryOwnerScope::applyToLocationQuery(InventoryOperation::query())
            ->where('order_id', $order->id)
            ->where('kind', $kind)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return InventoryOperation::create([
                'order_id' => $order->id,
                'kind' => $kind,
                'status' => InventoryOperation::STATUS_PENDING,
            ]);
        } catch (QueryException $e) {
            return InventoryOwnerScope::applyToLocationQuery(InventoryOperation::query())
                ->where('order_id', $order->id)
                ->where('kind', $kind)
                ->firstOrFail();
        }
    }

    /**
     * Extract cart ID from order metadata.
     */
    private function extractCartId(Order $order): ?string
    {
        $metadata = $order->metadata ?? [];

        return $metadata['cart_id']
            ?? $metadata['cartId']
            ?? $metadata['cart_identifier']
            ?? null;
    }

    /**
     * Try to commit existing cart allocations.
     */
    private function tryCommitAllocations(string $cartId, Order $order): bool
    {
        $allocations = $this->allocationService->getAllocationsForCart($cartId);

        if ($allocations->isEmpty()) {
            return false;
        }

        $this->allocationService->commit($cartId, $order->id);

        Log::info('Inventory committed from cart allocations', [
            'order_id' => $order->id,
            'cart_id' => $cartId,
            'allocation_count' => $allocations->count(),
        ]);

        return true;
    }

    /**
     * Deduct inventory directly for orders without allocations.
     */
    private function deductDirectly(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order->loadMissing('items.purchasable');

            /** @var list<array{0: Model, 1: int}> $deductibles */
            $deductibles = [];

            foreach ($order->items as $item) {
                $purchasable = $item->purchasable;

                if (! $purchasable instanceof Model) {
                    continue;
                }

                // Check if this model tracks inventory
                if (! $this->tracksInventory($purchasable)) {
                    continue;
                }

                $deductibles[] = [$purchasable, (int) $item->quantity];
            }

            // One query for every level the order may touch, instead of a
            // location search per line. In-memory tracking mirrors the
            // previous fresh-read-per-line semantics for sequential lines
            // sharing a SKU, while ship() still re-checks under a row lock.
            $availability = $this->preloadLevelAvailability($deductibles);

            foreach ($deductibles as [$purchasable, $quantity]) {
                $key = $purchasable->getMorphClass() . ':' . $purchasable->getKey();
                $locationId = $this->pickDeductionLocation($key, $quantity, $order, $availability);

                if ($locationId === null) {
                    Log::warning('No inventory location found for deduction', [
                        'order_id' => $order->id,
                        'model_type' => $purchasable->getMorphClass(),
                        'model_id' => $purchasable->getKey(),
                        'quantity' => $quantity,
                    ]);

                    continue;
                }

                $this->inventoryService->ship(
                    model: $purchasable,
                    locationId: $locationId,
                    quantity: $quantity,
                    reason: 'order',
                    reference: $order->order_number,
                    note: sprintf('Order #%s', $order->order_number),
                );

                foreach ($availability[$key] as &$slot) {
                    if ($slot['location_id'] === $locationId) {
                        $slot['available'] -= $quantity;

                        break;
                    }
                }

                unset($slot);
            }
        });

        Log::info('Inventory deducted directly for order', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'item_count' => $order->items->count(),
        ]);
    }

    /**
     * Preload every candidate level for the deducted models.
     *
     * @param  list<array{0: Model, 1: int}>  $deductibles
     * @return array<string, list<array{location_id: string, available: int, priority: int, active: bool}>>
     */
    private function preloadLevelAvailability(array $deductibles): array
    {
        /** @var array<string, array{0: string, 1: mixed}> $pairs */
        $pairs = [];

        foreach ($deductibles as [$model]) {
            $pairs[$model->getMorphClass() . ':' . $model->getKey()] = [
                $model->getMorphClass(),
                $model->getKey(),
            ];
        }

        $availability = [];

        foreach ($pairs as $key => $_) {
            $availability[$key] = [];
        }

        if ($pairs === []) {
            return $availability;
        }

        $levels = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
                ->whereIn('inventoryable_type', array_unique(array_column($pairs, 0)))
                ->whereIn('inventoryable_id', array_unique(array_column($pairs, 1)))
                ->with('location')
        )->get();

        foreach ($levels as $level) {
            $key = $level->inventoryable_type . ':' . $level->inventoryable_id;

            if (! array_key_exists($key, $availability)) {
                continue;
            }

            $availability[$key][] = [
                'location_id' => (string) $level->location_id,
                'available' => $level->quantity_on_hand - $level->quantity_reserved,
                'priority' => (int) ($level->location?->priority ?? 0),
                'active' => (bool) ($level->location?->is_active ?? false),
            ];
        }

        foreach ($availability as &$slots) {
            usort($slots, static fn (array $a, array $b): int => $b['priority'] <=> $a['priority']);
        }

        unset($slots);

        return $availability;
    }

    /**
     * Pick a deduction location from preloaded availability.
     *
     * Mirrors the previous per-line search exactly: the order's preferred
     * fulfillment location first (regardless of active flag), then the
     * highest-priority active location with sufficient raw stock.
     *
     * @param  array<string, list<array{location_id: string, available: int, priority: int, active: bool}>>  $availability
     */
    private function pickDeductionLocation(string $key, int $quantity, Order $order, array $availability): ?string
    {
        $slots = $availability[$key] ?? [];

        // Check if order has a specific fulfillment location
        $metadata = $order->metadata ?? [];
        $preferredLocation = $metadata['fulfillment_location_id'] ?? null;

        if ($preferredLocation !== null) {
            foreach ($slots as $slot) {
                if ($slot['location_id'] === (string) $preferredLocation && max(0, $slot['available']) >= $quantity) {
                    return $slot['location_id'];
                }
            }
        }

        // Find location with sufficient stock (priority-based)
        foreach ($slots as $slot) {
            if (! $slot['active']) {
                continue;
            }

            if ($slot['available'] >= $quantity) {
                return $slot['location_id'];
            }
        }

        return null;
    }

    /**
     * Check if a model tracks inventory.
     */
    private function tracksInventory(Model $model): bool
    {
        if (method_exists($model, 'tracksInventory')) {
            return $model->tracksInventory();
        }

        // Default: assume it tracks inventory if it's a purchasable
        return true;
    }
}
