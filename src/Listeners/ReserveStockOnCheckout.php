<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Listeners;

use AIArmada\Cart\Cart;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Services\InventoryAllocationService;
use Illuminate\Database\Eloquent\Model;

/**
 * Reserves (allocates) stock when checkout is initiated.
 *
 * This listener ensures inventory is reserved for the duration
 * of the checkout process to prevent overselling.
 */
class ReserveStockOnCheckout
{
    private const string ALLOCATION_METADATA_KEY = 'inventory_allocations';

    public function __construct(
        private readonly InventoryAllocationService $allocationService
    ) {}

    /**
     * Handle the checkout started event.
     *
     * Allocates inventory for all cart items and stores allocation
     * references in cart metadata for later confirmation or release.
     *
     * @param  object  $event  The checkout started event
     *
     * @throws InsufficientInventoryException When inventory cannot be allocated
     */
    public function handle(object $event): void
    {
        if (! config('inventory.cart.reserve_on_checkout', true)) {
            return;
        }

        $cart = $this->extractCart($event);

        if ($cart === null) {
            return;
        }

        $cartId = (string) $cart->getId();
        $ttlMinutes = (int) config('inventory.cart.checkout_reservation_ttl_minutes', 30);
        $allocations = [];
        $errors = [];

        foreach ($cart->getItems() as $item) {
            $model = $item->getAssociatedModel();

            if (! $model instanceof Model) {
                continue;
            }

            try {
                $itemAllocations = $this->allocationService->allocate(
                    $model,
                    $item->quantity,
                    $cartId,
                    $ttlMinutes
                );

                if ($itemAllocations->isNotEmpty()) {
                    $allocations[$item->id] = [
                        'item_id' => $item->id,
                        'item_name' => $item->name,
                        'quantity' => $item->quantity,
                        'allocation_count' => $itemAllocations->count(),
                        'allocated_at' => now()->toISOString(),
                    ];
                }
            } catch (InsufficientInventoryException $e) {
                if (config('inventory.cart.block_checkout_on_insufficient', true)) {
                    // Release any allocations we've made so far
                    $this->releaseAllocations($cart, $cartId);

                    throw $e;
                }

                $errors[$item->id] = $e->getMessage();
            }
        }

        // Store allocation metadata in cart
        if (count($allocations) > 0) {
            $cart->setMetadata(self::ALLOCATION_METADATA_KEY, [
                'allocations' => $allocations,
                'errors' => $errors,
                'reserved_at' => now()->toISOString(),
                'expires_at' => now()->addMinutes($ttlMinutes)->toISOString(),
            ]);
        }
    }

    /**
     * Extract cart from the event.
     */
    private function extractCart(object $event): ?Cart
    {
        if (property_exists($event, 'cart') && $event->cart instanceof Cart) {
            return $event->cart;
        }

        return null;
    }

    /**
     * Release all allocations for a cart (rollback on error).
     */
    private function releaseAllocations(Cart $cart, string $cartId): void
    {
        foreach ($cart->getItems() as $item) {
            $model = $item->getAssociatedModel();

            if ($model instanceof Model) {
                $this->allocationService->release($model, $cartId);
            }
        }
    }
}
