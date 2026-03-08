<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Cart;

use AIArmada\Cart\Events\ItemAdded;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Services\InventoryAllocationService;
use Illuminate\Database\Eloquent\Model;

/**
 * Validates inventory availability when items are added to cart.
 *
 * This listener can optionally:
 * - Block additions when out of stock (unless backorder is allowed)
 * - Auto-allocate inventory on add
 * - Mark items as backorder when exceeding available stock
 */
final class ValidateInventoryOnAdd
{
    public function __construct(
        private readonly InventoryAllocationService $allocationService,
    ) {}

    public function handle(ItemAdded $event): void
    {
        if (! config('inventory.cart.validate_on_add', false)) {
            return;
        }

        $item = $event->item;
        $cart = $event->cart;

        // Get the associated model for the item
        $model = $item->getAssociatedModel();

        if (! $model instanceof Model) {
            return;
        }

        $requestedQuantity = $item->quantity;
        $availableQuantity = $this->allocationService->getTotalAvailable($model);

        // Check if we have sufficient inventory
        if ($availableQuantity >= $requestedQuantity) {
            $this->handleSufficientStock($cart, $item, $model);

            return;
        }

        // Insufficient stock - check backorder policy
        $allowBackorder = config('inventory.cart.allow_backorder', false);

        if (! $allowBackorder) {
            throw new InsufficientInventoryException(
                sprintf(
                    'Insufficient inventory for %s. Requested: %d, Available: %d',
                    $item->name,
                    $requestedQuantity,
                    $availableQuantity
                ),
                $item->id,
                $requestedQuantity,
                $availableQuantity
            );
        }

        // Handle backorder
        $this->handleBackorder($cart, $item, $model, $availableQuantity);
    }

    /**
     * Handle item with sufficient stock.
     */
    private function handleSufficientStock(object $cart, object $item, Model $model): void
    {
        // Auto-allocate if configured
        if (config('inventory.cart.auto_allocate_on_add', false)) {
            $ttl = config('inventory.cart.allocation_ttl_minutes', 30);
            $cartId = (string) $cart->getId();

            $this->allocationService->allocate(
                $model,
                $item->quantity,
                $cartId,
                $ttl
            );

            // Mark item as allocated in metadata
            $metadataKey = config('inventory.cart.allocation_metadata_key', 'inventory_allocated');
            $cart->setMetadata($metadataKey . '.' . $item->id, [
                'allocated' => true,
                'quantity' => $item->quantity,
                'allocated_at' => now()->toISOString(),
            ]);
        }
    }

    /**
     * Handle backorder scenario.
     */
    private function handleBackorder(object $cart, object $item, Model $model, int $availableQuantity): void
    {
        $requestedQuantity = $item->quantity;
        $backorderQuantity = $requestedQuantity - $availableQuantity;

        // Check max backorder limit
        $maxBackorder = config('inventory.cart.max_backorder_quantity');

        if ($maxBackorder !== null && $backorderQuantity > $maxBackorder) {
            throw new InsufficientInventoryException(
                sprintf(
                    'Backorder limit exceeded for %s. Max backorder: %d, Requested backorder: %d',
                    $item->name,
                    $maxBackorder,
                    $backorderQuantity
                ),
                $item->id,
                $requestedQuantity,
                $availableQuantity
            );
        }

        // Mark item as partial backorder in metadata
        $metadataKey = config('inventory.cart.backorder_metadata_key', 'is_backorder');
        $cart->setMetadata($metadataKey . '.' . $item->id, [
            'is_backorder' => true,
            'available_quantity' => $availableQuantity,
            'backorder_quantity' => $backorderQuantity,
            'marked_at' => now()->toISOString(),
        ]);

        // Auto-allocate available stock if configured
        if (config('inventory.cart.auto_allocate_on_add', false) && $availableQuantity > 0) {
            $ttl = config('inventory.cart.allocation_ttl_minutes', 30);
            $cartId = (string) $cart->getId();

            $this->allocationService->allocate(
                $model,
                $availableQuantity,
                $cartId,
                $ttl
            );
        }
    }
}
