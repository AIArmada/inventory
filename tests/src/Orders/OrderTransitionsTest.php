<?php

declare(strict_types=1);

use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\States\Canceled;
use AIArmada\Orders\States\Delivered;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use AIArmada\Orders\States\Refunded;
use AIArmada\Orders\States\Returned;
use AIArmada\Orders\States\Shipped;
use AIArmada\Orders\Transitions\DeliveryConfirmed;
use AIArmada\Orders\Transitions\OrderCanceled;
use AIArmada\Orders\Transitions\PaymentConfirmed;
use AIArmada\Orders\Transitions\RefundProcessed;
use AIArmada\Orders\Transitions\ShipmentCreated;

describe('Order Transitions', function (): void {
    describe('PaymentConfirmed Transition', function (): void {
        it('transitions from PendingPayment to Processing', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-TRANS1-' . uniqid(),
                'status' => PendingPayment::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $transition = new PaymentConfirmed($order, 'txn_123', 'stripe', 10000);
            $result = $transition->handle();

            expect($result)->toBe($order);
            expect($order->status)->toBeInstanceOf(Processing::class);
            expect($order->paid_at)->not->toBeNull();
            expect($order->payments)->toHaveCount(1);
            expect($order->payments->first()->transaction_id)->toBe('txn_123');
        });
    });

    describe('ShipmentCreated Transition', function (): void {
        it('transitions from Processing to Shipped', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-TRANS2-' . uniqid(),
                'status' => Processing::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $transition = new ShipmentCreated($order, 'J&T', 'JT123456789', 'ship_123');
            $result = $transition->handle();

            expect($result)->toBe($order);
            expect($order->status)->toBeInstanceOf(Shipped::class);
            expect($order->shipped_at)->not->toBeNull();
        });
    });

    describe('DeliveryConfirmed Transition', function (): void {
        it('transitions from Shipped to Delivered', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-TRANS3-' . uniqid(),
                'status' => Shipped::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $transition = new DeliveryConfirmed($order);
            $result = $transition->handle();

            expect($result)->toBe($order);
            expect($order->status)->toBeInstanceOf(Delivered::class);
            expect($order->delivered_at)->not->toBeNull();
        });
    });

    describe('OrderCanceled Transition', function (): void {
        it('transitions from PendingPayment to Canceled state', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-TRANS4-' . uniqid(),
                'status' => PendingPayment::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $transition = new OrderCanceled($order, 'Customer request');
            $result = $transition->handle();

            expect($result)->toBe($order);
            expect($order->status)->toBeInstanceOf(Canceled::class);
            expect($order->canceled_at)->not->toBeNull();
            expect($order->cancellation_reason)->toBe('Customer request');
        });

        it('transitions to Canceled state and creates refund for paid order', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-TRANS4B-' . uniqid(),
                'status' => Processing::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
                'paid_at' => now(),
            ]);

            // Create a payment
            $payment = OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 10000,
                'currency' => 'MYR',
                'status' => 'completed',
                'paid_at' => now(),
            ]);

            $transition = new OrderCanceled($order, 'Customer request', null, true); // issueRefund = true
            $result = $transition->handle();

            expect($result)->toBe($order);
            expect($order->status)->toBeInstanceOf(Canceled::class);
            expect($order->refunds)->toHaveCount(1);
            expect($order->refunds->first()->amount)->toBe(10000);
            expect($order->refunds->first()->reason)->toBe('Order canceled: Customer request');
        });
    });

    describe('RefundProcessed Transition', function (): void {
        it('transitions from Returned to Refunded', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-TRANS5-' . uniqid(),
                'status' => Returned::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $transition = new RefundProcessed($order, 5000, 'ref_txn_123', 'Customer request');
            $result = $transition->handle();

            expect($result)->toBe($order);
            expect($order->status)->toBeInstanceOf(Refunded::class);
            expect($order->refunds)->toHaveCount(1);
            expect($order->refunds->first()->amount)->toBe(5000);
        });
    });
});
