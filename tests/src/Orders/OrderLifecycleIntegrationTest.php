<?php

declare(strict_types=1);

use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\Services\OrderService;
use AIArmada\Orders\States\Canceled;
use AIArmada\Orders\States\Completed;
use AIArmada\Orders\States\Created;
use AIArmada\Orders\States\Delivered;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use AIArmada\Orders\States\Refunded;
use AIArmada\Orders\States\Returned;
use AIArmada\Orders\States\Shipped;

describe('Order Lifecycle Integration', function (): void {
    it('can handle complete order lifecycle from creation to completion', function (): void {
        $service = new OrderService();

        // 1. Create order
        $orderData = [
            'order_number' => 'ORD-LIFECYCLE-' . uniqid(),
            'subtotal' => 20000,
            'shipping_total' => 1000,
            'tax_total' => 1200,
            'grand_total' => 22200,
            'currency' => 'MYR',
            'notes' => 'Integration test order',
        ];

        $items = [
            [
                'name' => 'Premium Widget',
                'quantity' => 2,
                'unit_price' => 8000,
                'tax_amount' => 480,
                'sku' => 'WIDGET-PREM-001',
            ],
            [
                'name' => 'Standard Gadget',
                'quantity' => 1,
                'unit_price' => 4000,
                'tax_amount' => 240,
                'sku' => 'GADGET-STD-001',
            ],
        ];

        $billingAddress = [
            'first_name' => 'John',
            'last_name' => 'Integration',
            'line1' => '123 Test Street',
            'city' => 'Test City',
            'postcode' => '12345',
            'country_code' => 'MY',
            'email' => 'john@test.com',
        ];

        $order = $service->createOrder($orderData, $items, $billingAddress);

        expect($order)->toBeInstanceOf(Order::class);
        expect($order->status)->toBeInstanceOf(PendingPayment::class);
        expect($order->items)->toHaveCount(2);
        expect($order->billingAddress)->not->toBeNull();

        // 2. Confirm payment
        $order = $service->confirmPayment($order, 'txn_integration_123', 'stripe', 22200);

        expect($order->status)->toBeInstanceOf(Processing::class);
        expect($order->paid_at)->not->toBeNull();
        expect($order->payments)->toHaveCount(1);

        // 3. Ship order
        $order = $service->ship($order, 'Test Carrier', 'TC123456789', 'ship_integration_123');

        expect($order->status)->toBeInstanceOf(Shipped::class);
        expect($order->shipped_at)->not->toBeNull();

        // 4. Confirm delivery
        $order = $service->confirmDelivery($order, ['delivered_by' => 'integration_test']);

        expect($order->status)->toBeInstanceOf(Delivered::class);
        expect($order->delivered_at)->not->toBeNull();

        // 5. Complete order
        $order->status->transitionTo(Completed::class);

        expect($order->status)->toBeInstanceOf(Completed::class);
    });

    it('can handle order return and refund process', function (): void {
        $service = new OrderService();

        // Create and process order to delivered state
        $order = Order::create([
            'order_number' => 'ORD-RETURN-' . uniqid(),
            'status' => Delivered::class,
            'currency' => 'MYR',
            'subtotal' => 15000,
            'grand_total' => 15000,
            'paid_at' => now(),
        ]);

        // Create payment record
        OrderPayment::create([
            'order_id' => $order->id,
            'gateway' => 'stripe',
            'amount' => 15000,
            'currency' => 'MYR',
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        // 1. Mark as returned
        $order->status->transitionTo(Returned::class);
        expect($order->status)->toBeInstanceOf(Returned::class);

        // 2. Process refund
        $order = $service->processRefund($order, 15000, 'ref_integration_123', 'Full return');

        expect($order->status)->toBeInstanceOf(Refunded::class);
        expect($order->refunds)->toHaveCount(1);
        expect($order->refunds->first()->amount)->toBe(15000);
    });

    it('can handle order cancellation with refund', function (): void {
        $service = new OrderService();

        // Create order in pending payment state
        $order = Order::create([
            'order_number' => 'ORD-CANCEL-REFUND-' . uniqid(),
            'status' => PendingPayment::class,
            'currency' => 'MYR',
            'subtotal' => 10000,
            'grand_total' => 10000,
            'paid_at' => now(),
        ]);

        // Create payment record
        OrderPayment::create([
            'order_id' => $order->id,
            'gateway' => 'stripe',
            'amount' => 10000,
            'currency' => 'MYR',
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        // Cancel with refund
        $order = $service->cancel($order, 'Customer changed mind');

        expect($order->status)->toBeInstanceOf(Canceled::class);
        expect($order->cancellation_reason)->toBe('Customer changed mind');
        expect($order->refunds)->toHaveCount(1);
        expect($order->orderNotes)->toHaveCount(1);
    });
});