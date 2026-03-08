<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Listeners;

use AIArmada\Inventory\Services\InventoryAllocationService;

final class CommitInventoryOnPayment
{
    public function __construct(
        private InventoryAllocationService $allocationService
    ) {}

    /**
     * Handle PaymentSucceeded event.
     */
    public function handle(object $event): void
    {
        $cartId = $this->getCartIdentifier($event);
        $orderId = $this->getOrderReference($event);

        if ($cartId !== null) {
            $this->allocationService->commit($cartId, $orderId);
        }
    }

    /**
     * Extract cart identifier from event.
     */
    private function getCartIdentifier(object $event): ?string
    {
        // Try common property names
        $properties = ['cartId', 'cart_id', 'cartIdentifier', 'cart_identifier'];

        foreach ($properties as $prop) {
            if (property_exists($event, $prop) && $event->{$prop} !== null) {
                return (string) $event->{$prop};
            }
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

        // Try to get from payment/purchase object
        if (property_exists($event, 'payment') && property_exists($event->payment, 'cart_id')) {
            return $event->payment->cart_id;
        }

        if (property_exists($event, 'purchase') && property_exists($event->purchase, 'cart_id')) {
            return $event->purchase->cart_id;
        }

        return null;
    }

    /**
     * Extract order reference from event.
     */
    private function getOrderReference(object $event): ?string
    {
        // Try common property names
        $properties = ['orderId', 'order_id', 'orderReference', 'order_reference', 'reference'];

        foreach ($properties as $prop) {
            if (property_exists($event, $prop) && $event->{$prop} !== null) {
                return (string) $event->{$prop};
            }
        }

        // Try to get from payment/purchase object
        if (property_exists($event, 'payment')) {
            $payment = $event->payment;

            if (property_exists($payment, 'order_id')) {
                return $payment->order_id;
            }

            if (property_exists($payment, 'reference')) {
                return $payment->reference;
            }

            if (method_exists($payment, 'getKey')) {
                return (string) $payment->getKey();
            }
        }

        if (property_exists($event, 'purchase')) {
            $purchase = $event->purchase;

            if (method_exists($purchase, 'getKey')) {
                return (string) $purchase->getKey();
            }
        }

        return null;
    }
}
