<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Support;

use AIArmada\Cart\CartManager;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cashier\Events\PaymentFailed;
use AIArmada\Cashier\Events\PaymentSucceeded;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;

/**
 * Registers cart integration when aiarmada/cart is installed.
 *
 * Provides tight integration between cart and payment:
 * - Checkout orchestration (cart → payment → success/failure)
 * - Payment event handling (clear cart on success, release inventory on failure)
 * - Cart metadata preservation for payment context
 */
final class CartIntegrationRegistrar
{
    public function __construct(
        private readonly Application $app,
        private readonly Dispatcher $events,
    ) {}

    public function register(): void
    {
        if (! class_exists(CartManager::class)) {
            return;
        }

        if (! config('cashier.cart.enabled', true)) {
            return;
        }

        $this->registerPaymentListeners();
        $this->extendCartManager();
    }

    /**
     * Register listeners for payment events that affect cart.
     */
    private function registerPaymentListeners(): void
    {
        // Clear cart on successful payment
        if (config('cashier.cart.clear_on_success', true)) {
            $this->events->listen(PaymentSucceeded::class, function (PaymentSucceeded $event): void {
                $this->handlePaymentSuccess($event);
            });
        }

        // Handle payment failure (release inventory, etc.)
        if (config('cashier.cart.handle_failure', true)) {
            $this->events->listen(PaymentFailed::class, function (PaymentFailed $event): void {
                $this->handlePaymentFailure($event);
            });
        }
    }

    /**
     * Extend the cart manager with checkout capabilities.
     */
    private function extendCartManager(): void
    {
        // Add checkout macro to cart manager if configured
        if (! config('cashier.cart.register_checkout_macro', true)) {
            return;
        }

        $this->app->extend('cart', function (CartManagerInterface $manager, Application $app) {
            if ($manager instanceof CartManagerWithPayment) {
                return $manager;
            }

            $proxy = CartManagerWithPayment::fromCartManager($manager);

            $app->instance(CartManager::class, $proxy);
            $app->instance(CartManagerInterface::class, $proxy);

            if (class_exists(\AIArmada\Cart\Facades\Cart::class)) {
                \AIArmada\Cart\Facades\Cart::clearResolvedInstance('cart');
            }

            return $proxy;
        });
    }

    /**
     * Handle successful payment - clear cart, commit inventory, fire events.
     */
    private function handlePaymentSuccess(PaymentSucceeded $event): void
    {
        $cartId = $this->extractCartIdFromPayment($event);

        if (! $cartId) {
            return;
        }

        /** @var CartManagerInterface $cartManager */
        $cartManager = $this->app->make('cart');

        // Get cart before clearing for metadata preservation
        $cart = $cartManager->get($cartId);

        if (! $cart) {
            return;
        }

        // Commit inventory allocations if inventory package is present
        if (class_exists(\AIArmada\Inventory\InventoryServiceProvider::class)) {
            $this->commitInventoryAllocations($cartManager, $cartId, $event);
        }

        // Record affiliate conversion if affiliates package is present
        if (class_exists(\AIArmada\Affiliates\AffiliatesServiceProvider::class)) {
            $this->recordAffiliateConversion($cart, $event);
        }

        // Clear the cart
        if (config('cashier.cart.clear_on_success', true)) {
            $cartManager->destroy($cartId);
        }
    }

    /**
     * Handle payment failure - release inventory, notify, etc.
     */
    private function handlePaymentFailure(PaymentFailed $event): void
    {
        $cartId = $this->extractCartIdFromPayment($event);

        if (! $cartId) {
            return;
        }

        $failureMode = config('cashier.cart.failure_mode', 'retry_window');

        switch ($failureMode) {
            case 'immediate_release':
                $this->releaseInventoryAllocations($cartId);

                break;

            case 'retry_window':
                // Inventory will auto-release after TTL
                break;

            case 'hybrid':
                // Check if it's a hard failure
                if ($this->isHardFailure($event)) {
                    $this->releaseInventoryAllocations($cartId);
                }

                break;
        }
    }

    /**
     * Extract cart ID from payment event metadata.
     */
    private function extractCartIdFromPayment(object $event): ?string
    {
        $metadataKey = config('cashier.cart.metadata_key', 'cart_id');

        if (method_exists($event, 'metadata')) {
            $metadata = $event->metadata();

            return $metadata[$metadataKey] ?? null;
        }

        if (property_exists($event, 'payment') && method_exists($event->payment, 'metadata')) {
            $metadata = $event->payment->metadata();

            return $metadata[$metadataKey] ?? null;
        }

        return null;
    }

    /**
     * Commit inventory allocations on successful payment.
     */
    private function commitInventoryAllocations(CartManagerInterface $cartManager, string $cartId, PaymentSucceeded $event): void
    {
        if (! method_exists($cartManager, 'commitInventory')) {
            return;
        }

        $orderId = $this->extractOrderIdFromPayment($event);
        $cartManager->commitInventory($cartId, $orderId);
    }

    /**
     * Release inventory allocations on failure.
     */
    private function releaseInventoryAllocations(string $cartId): void
    {
        if (! class_exists(\AIArmada\Inventory\InventoryServiceProvider::class)) {
            return;
        }

        /** @var CartManagerInterface $cartManager */
        $cartManager = $this->app->make('cart');

        if (method_exists($cartManager, 'releaseAllInventory')) {
            $cartManager->releaseAllInventory($cartId);
        }
    }

    /**
     * Record affiliate conversion from cart.
     *
     * @param  \AIArmada\Cart\Cart  $cart
     */
    private function recordAffiliateConversion(object $cart, PaymentSucceeded $event): void
    {
        if (! class_exists(\AIArmada\Affiliates\Services\AffiliateService::class)) {
            return;
        }

        $affiliateMetadata = $cart->getMetadata('affiliate') ?? [];
        $affiliateId = $affiliateMetadata['affiliate_id'] ?? null;

        if (! $affiliateId) {
            return;
        }

        /** @var \AIArmada\Affiliates\Services\AffiliateService $affiliateService */
        $affiliateService = $this->app->make(\AIArmada\Affiliates\Services\AffiliateService::class);

        $amount = $event->payment->rawAmount() ?? 0;
        $currency = $event->payment->currency() ?? config('cashier.currency', 'USD');

        /** @var \AIArmada\Cart\Cart $cart */
        $affiliateService->recordConversion($cart, [
            'order_reference' => $this->extractOrderIdFromPayment($event),
            'total' => $amount,
            'commission_currency' => $currency,
            'metadata' => [
                'payment_id' => $event->payment->id(),
                'gateway' => $event->payment->gateway(),
                'cart_id' => $cart->getId(),
            ],
        ]);
    }

    /**
     * Extract order ID from payment.
     */
    private function extractOrderIdFromPayment(object $event): ?string
    {
        $metadataKey = config('cashier.cart.order_id_key', 'order_id');

        if (method_exists($event, 'metadata')) {
            $metadata = $event->metadata();

            return $metadata[$metadataKey] ?? $event->payment->id();
        }

        return $event->payment->id();
    }

    /**
     * Determine if payment failure is a "hard" failure (no retry possible).
     */
    private function isHardFailure(PaymentFailed $event): bool
    {
        $hardFailureCodes = config('cashier.cart.hard_failure_codes', [
            'card_declined',
            'insufficient_funds',
            'expired_card',
            'incorrect_cvc',
            'processing_error',
        ]);

        $errorCode = $event->payment->errorCode() ?? '';

        return in_array($errorCode, $hardFailureCodes, true);
    }
}
