<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Listeners;

use AIArmada\Cashier\Events\PaymentSucceeded as CashierPaymentSucceeded;
use AIArmada\CashierChip\Events\PaymentSucceeded as CashierChipPaymentSucceeded;
use AIArmada\Inventory\Contracts\ProvidesInventoryCommitContext;
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
        if ($event instanceof ProvidesInventoryCommitContext) {
            return $event->inventoryCartId();
        }

        if ($event instanceof CashierPaymentSucceeded) {
            $metadataKey = config('cashier.cart.metadata_key', 'cart_id');
            $metadata = $event->metadata();

            $cartId = $metadata[$metadataKey] ?? null;

            return is_string($cartId) && $cartId !== '' ? $cartId : null;
        }

        if ($event instanceof CashierChipPaymentSucceeded) {
            $cartId = $event->metadata()['cart_id'] ?? null;

            return is_string($cartId) && $cartId !== '' ? $cartId : null;
        }

        return null;
    }

    /**
     * Extract order reference from event.
     */
    private function getOrderReference(object $event): ?string
    {
        if ($event instanceof ProvidesInventoryCommitContext) {
            return $event->inventoryOrderReference();
        }

        if ($event instanceof CashierPaymentSucceeded) {
            $metadataKey = config('cashier.cart.order_id_key', 'order_id');
            $metadata = $event->metadata();

            $orderReference = $metadata[$metadataKey] ?? $event->payment->id();

            return is_string($orderReference) && $orderReference !== '' ? $orderReference : null;
        }

        if ($event instanceof CashierChipPaymentSucceeded) {
            return $event->reference();
        }

        return null;
    }
}
