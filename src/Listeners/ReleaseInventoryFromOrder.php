<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Listeners;

use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Models\InventoryOperation;
use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Inventory\Services\Stock\InventoryAllocationService;
use AIArmada\Orders\Events\InventoryReleaseRequired;
use AIArmada\Orders\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Releases inventory when an order is cancelled.
 *
 * This listener handles two scenarios:
 *
 * 1. **With cart allocations**: Release any pending allocations that
 *    haven't been committed yet.
 *
 * 2. **After shipment**: If inventory was already deducted, create
 *    a return movement to restore stock.
 */
final class ReleaseInventoryFromOrder
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly InventoryAllocationService $allocationService,
    ) {}

    public function handle(InventoryReleaseRequired $event): void
    {
        $order = $event->order;

        if (! config('inventory.orders.enabled', true)) {
            return;
        }

        $operation = $this->resolveOrCreateOperation($order, InventoryOperation::KIND_RELEASE);

        if ($operation->status === InventoryOperation::STATUS_COMPLETED) {
            Log::info('Inventory release already completed for order', [
                'order_id' => $order->id,
                'operation_id' => $operation->id,
            ]);

            return;
        }

        DB::transaction(function () use ($order, $operation): void {
            $operation = InventoryOperation::lockForUpdate()->findOrFail($operation->id);

            if ($operation->status === InventoryOperation::STATUS_COMPLETED) {
                return;
            }

            $this->releaseAllocations($order);
            $this->restoreShippedInventory($order);

            $operation->update([
                'status' => InventoryOperation::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        });
    }

    private function resolveOrCreateOperation(Order $order, string $kind): InventoryOperation
    {
        $existing = InventoryOperation::where('order_id', $order->id)
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
            return InventoryOperation::where('order_id', $order->id)
                ->where('kind', $kind)
                ->firstOrFail();
        }
    }

    /**
     * Release any pending cart allocations.
     */
    private function releaseAllocations(Order $order): void
    {
        $cartId = $this->extractCartId($order);

        if ($cartId === null) {
            return;
        }

        $released = $this->allocationService->releaseAllForCart($cartId);

        if ($released > 0) {
            Log::info('Released pending inventory allocations for cancelled order', [
                'order_id' => $order->id,
                'cart_id' => $cartId,
                'quantity_released' => $released,
            ]);
        }
    }

    /**
     * Restore inventory that was already shipped/deducted.
     */
    private function restoreShippedInventory(Order $order): void
    {
        // Find shipment movements for this order
        $movements = InventoryMovement::query()
            ->where('type', MovementType::Shipment->value)
            ->where('reference', $order->order_number)
            ->get();

        if ($movements->isEmpty()) {
            return;
        }

        foreach ($movements as $movement) {
            $locationId = $movement->from_location_id;

            if ($locationId === null) {
                continue;
            }

            // Create a return movement to restore stock
            $this->inventoryService->receive(
                model: $movement->inventoryable,
                locationId: $locationId,
                quantity: $movement->quantity,
                reason: sprintf('order_cancelled:%s', $order->order_number),
                note: sprintf('Restored from cancelled order #%s', $order->order_number),
            );
        }

        Log::info('Restored inventory for cancelled order', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'movement_count' => $movements->count(),
        ]);
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
}
