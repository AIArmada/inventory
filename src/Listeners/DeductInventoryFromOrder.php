<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Listeners;

use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Services\InventoryAllocationService;
use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use AIArmada\Orders\Events\InventoryDeductionRequired;
use AIArmada\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
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

        // Try to commit allocations first (if cart was used)
        $cartId = $this->extractCartId($order);

        if ($cartId !== null) {
            $committed = $this->tryCommitAllocations($cartId, $order);

            if ($committed) {
                return;
            }
        }

        // No allocations found - deduct directly
        $this->deductDirectly($order);
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
            foreach ($order->items as $item) {
                $purchasable = $item->purchasable;

                if (! $purchasable instanceof Model) {
                    continue;
                }

                // Check if this model tracks inventory
                if (! $this->tracksInventory($purchasable)) {
                    continue;
                }

                $this->deductForItem($purchasable, $item->quantity, $order);
            }
        });

        Log::info('Inventory deducted directly for order', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'item_count' => $order->items->count(),
        ]);
    }

    /**
     * Deduct inventory for a single order item.
     */
    private function deductForItem(Model $model, int $quantity, Order $order): void
    {
        // Find the best location to deduct from
        $locationId = $this->findDeductionLocation($model, $quantity, $order);

        if ($locationId === null) {
            Log::warning('No inventory location found for deduction', [
                'order_id' => $order->id,
                'model_type' => $model->getMorphClass(),
                'model_id' => $model->getKey(),
                'quantity' => $quantity,
            ]);

            return;
        }

        $this->inventoryService->ship(
            model: $model,
            locationId: $locationId,
            quantity: $quantity,
            reason: 'order',
            reference: $order->order_number,
            note: sprintf('Order #%s', $order->order_number),
        );
    }

    /**
     * Find the best location to deduct inventory from.
     */
    private function findDeductionLocation(Model $model, int $quantity, Order $order): ?string
    {
        // Check if order has a specific fulfillment location
        $metadata = $order->metadata ?? [];
        $preferredLocation = $metadata['fulfillment_location_id'] ?? null;

        if ($preferredLocation !== null) {
            $level = $this->getLevelAtLocation($model, $preferredLocation);

            if ($level !== null && $level->available >= $quantity) {
                return $preferredLocation;
            }
        }

        // Find location with sufficient stock (priority-based)
        $level = InventoryOwnerScope::applyToQueryByLocationRelation(
            InventoryLevel::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->whereHas('location', fn ($q) => $q->where('is_active', true))
                ->whereRaw('(quantity_on_hand - quantity_reserved) >= ?', [$quantity])
                ->with('location')
        )
            ->orderByDesc(
                InventoryLevel::query()
                    ->selectRaw('priority')
                    ->from(config('inventory.database.tables.locations', 'inventory_locations'))
                    ->whereColumn('id', config('inventory.database.tables.levels', 'inventory_levels') . '.location_id')
                    ->limit(1)
            )
            ->first();

        return $level?->location_id;
    }

    /**
     * Get inventory level at a specific location.
     */
    private function getLevelAtLocation(Model $model, string $locationId): ?InventoryLevel
    {
        return InventoryOwnerScope::applyToQueryByLocationRelation(
            InventoryLevel::query()
                ->where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->where('location_id', $locationId)
                ->with('location')
        )->first();
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
