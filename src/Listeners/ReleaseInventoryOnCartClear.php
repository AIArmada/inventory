<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Listeners;

use AIArmada\Inventory\Services\InventoryAllocationService;

final class ReleaseInventoryOnCartClear
{
    public function __construct(
        private InventoryAllocationService $allocationService
    ) {}

    /**
     * Handle CartCleared event.
     */
    public function handleCleared(object $event): void
    {
        $this->releaseForCart($event);
    }

    /**
     * Handle CartDestroyed event.
     */
    public function handleDestroyed(object $event): void
    {
        $this->releaseForCart($event);
    }

    /**
     * Release allocations for a cart event.
     */
    private function releaseForCart(object $event): void
    {
        $cartId = $this->getCartIdentifier($event);

        if ($cartId !== null) {
            $this->allocationService->releaseAllForCart($cartId);
        }
    }

    /**
     * Extract cart identifier from event.
     */
    private function getCartIdentifier(object $event): ?string
    {
        // Try to get cart ID from event
        if (property_exists($event, 'cartId')) {
            return $event->cartId;
        }

        if (property_exists($event, 'cart_id')) {
            return $event->cart_id;
        }

        // Try to get from cart object
        if (property_exists($event, 'cart')) {
            $cart = $event->cart;

            if (method_exists($cart, 'getId') && $cart->getId() !== null) {
                return (string) $cart->getId();
            }

            if (method_exists($cart, 'getIdentifier') && method_exists($cart, 'instance')) {
                return sprintf('%s_%s', $cart->getIdentifier(), $cart->instance());
            }
        }

        return null;
    }
}
