<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Integrations\InventoryAdapter;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\Completed;
use AIArmada\Inventory\InventoryServiceProvider;
use AIArmada\Orders\Contracts\OrderServiceInterface;

final class CreateOrderStep extends AbstractCheckoutStep
{
    public function getIdentifier(): string
    {
        return 'create_order';
    }

    public function getName(): string
    {
        return 'Create Order';
    }

    /**
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return ['process_payment'];
    }

    /**
     * @return array<string, string>
     */
    public function validate(CheckoutSession $session): array
    {
        $errors = [];

        $paymentData = $session->payment_data ?? [];
        $isFreeOrder = ($paymentData['type'] ?? null) === 'free_order';
        $paymentStatus = $paymentData['status'] ?? null;

        if (! $isFreeOrder && $paymentStatus !== PaymentStatus::Completed->value) {
            $errors['payment'] = 'Payment must be completed before creating order';
        }

        return $errors;
    }

    public function handle(CheckoutSession $session): StepResult
    {
        if (! app()->bound(OrderServiceInterface::class)) {
            return $this->failed('Orders package not available');
        }

        $orderService = app(OrderServiceInterface::class);

        $cartSnapshot = $session->cart_snapshot ?? [];
        $shippingData = $session->shipping_data ?? [];
        $billingData = $session->billing_data ?? [];
        $paymentData = $session->payment_data ?? [];

        $orderData = [
            'customer_id' => $session->customer_id,
            'subtotal' => $session->subtotal,
            'discount_total' => $session->discount_total,
            'shipping_total' => $session->shipping_total,
            'tax_total' => $session->tax_total,
            'grand_total' => $session->grand_total,
            'currency' => $session->currency,
            'metadata' => [
                'checkout_session_id' => $session->id,
                'cart_id' => $session->cart_id,
                'payment_gateway' => $session->selected_payment_gateway,
                'payment_id' => $session->payment_id,
                'payment_data' => $paymentData,
                'discount_data' => $session->discount_data,
                'tax_data' => $session->tax_data,
            ],
        ];

        $items = $this->transformCartItems($cartSnapshot['items'] ?? []);

        $order = $orderService->createOrder(
            orderData: $orderData,
            items: $items,
            billingAddress: $billingData ?: null,
            shippingAddress: $shippingData ?: null,
        );

        $session->update([
            'order_id' => $order->id,
            'completed_at' => now(),
        ]);
        $session->status->transitionTo(Completed::class);

        $this->commitInventoryReservations($session);
        $this->clearCart($session);

        return $this->success('Order created successfully', [
            'order_id' => $order->id,
            'order_number' => $order->order_number ?? $order->id,
        ]);
    }

    /**
     * @param  array<array<string, mixed>>  $items
     * @return array<array<string, mixed>>
     */
    private function transformCartItems(array $items): array
    {
        return array_map(fn (array $item) => [
            'purchasable_id' => $item['product_id'] ?? $item['purchasable_id'] ?? null,
            'purchasable_type' => $item['purchasable_type'] ?? null,
            'name' => $item['name'] ?? '',
            'sku' => $item['sku'] ?? null,
            'quantity' => $item['quantity'] ?? 1,
            'unit_price' => $item['price'] ?? $item['unit_price'] ?? 0,
            'discount_amount' => $item['discount'] ?? 0,
            'tax_amount' => $item['tax'] ?? 0,
            'options' => $item['attributes'] ?? $item['options'] ?? null,
            'metadata' => $item['metadata'] ?? null,
        ], $items);
    }

    private function commitInventoryReservations(CheckoutSession $session): void
    {
        if (! class_exists(InventoryServiceProvider::class)) {
            return;
        }

        $pricingData = $session->pricing_data ?? [];
        $reservations = $pricingData['inventory_reservations'] ?? [];

        if (empty($reservations)) {
            return;
        }

        $inventoryAdapter = app(InventoryAdapter::class);

        foreach ($reservations as $reservation) {
            if (isset($reservation['reservation_id'])) {
                $inventoryAdapter->commit($reservation['reservation_id']);
            }
        }
    }

    private function clearCart(CheckoutSession $session): void
    {
        if (! app()->bound(CartManagerInterface::class)) {
            return;
        }

        $cartManager = app(CartManagerInterface::class);
        $cart = $cartManager->getById($session->cart_id);

        if ($cart !== null) {
            $cart->clear();
        }
    }
}
