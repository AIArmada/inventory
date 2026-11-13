<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Listeners;

use AIArmada\Cart\Cart;
use AIArmada\Inventory\Cart\InventoryDemandResolver;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Services\Stock\InventoryAllocationService;
use Carbon\CarbonImmutable;

/**
 * Reserves (allocates) stock when checkout is initiated.
 *
 * Composite lines reserve their actual components multiplied by the package
 * quantity. Demand for the same inventory model is merged across composite
 * and standalone cart lines and allocated once, so the parent SKU is never
 * reserved and components are never double-allocated.
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
        $ttlMinutes = (int) config('inventory.allocation_ttl_minutes', 30);
        $demand = InventoryDemandResolver::forCart($cart->getItems());
        $allocations = [];
        $errors = [];

        foreach ($demand as $entry) {
            try {
                $itemAllocations = $this->allocationService->allocate(
                    $entry['model'],
                    $entry['quantity'],
                    $cartId,
                    $ttlMinutes
                );

                if ($itemAllocations->isEmpty()) {
                    continue;
                }

                $allocatedAt = CarbonImmutable::now()->toISOString();

                foreach ($entry['sources'] as $itemId => $source) {
                    $allocations[$itemId] = [
                        'item_id' => $itemId,
                        'item_name' => $source['name'],
                        'quantity' => $source['quantity'],
                        'allocation_count' => $itemAllocations->count(),
                        'allocated_at' => $allocatedAt,
                    ];
                }
            } catch (InsufficientInventoryException $e) {
                if (config('inventory.cart.block_checkout_on_insufficient', true)) {
                    // Release every allocation made for this checkout so a
                    // later component shortage cannot leave partial demand.
                    $this->releaseAllocations($cart, $cartId);

                    throw $e;
                }

                foreach (array_keys($entry['sources']) as $itemId) {
                    $errors[$itemId] = $e->getMessage();
                }
            }
        }

        // Store allocation metadata in cart
        if (count($allocations) > 0 || count($errors) > 0) {
            $cart->setMetadata(self::ALLOCATION_METADATA_KEY, [
                'allocations' => $allocations,
                'errors' => $errors,
                'reserved_at' => CarbonImmutable::now()->toISOString(),
                'expires_at' => CarbonImmutable::now()->addMinutes($ttlMinutes)->toISOString(),
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
        $this->allocationService->releaseAllForCart($cartId);
    }
}
