<?php

declare(strict_types=1);

use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Canceled;
use AIArmada\Orders\States\Completed;
use AIArmada\Orders\States\Created;
use AIArmada\Orders\States\Delivered;
use AIArmada\Orders\States\Fraud;
use AIArmada\Orders\States\OnHold;
use AIArmada\Orders\States\PaymentFailed;
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

describe('Order State Machine - Full Coverage', function (): void {
    describe('Complete Order Flow', function (): void {
        it('can complete full happy path order flow', function (): void {
            // Start with Created
            $order = Order::create([
                'order_number' => 'ORD-FLOW1-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->status)->toBeInstanceOf(Created::class);

            // Created -> PendingPayment
            $order->status->transitionTo(PendingPayment::class);
            expect($order->status)->toBeInstanceOf(PendingPayment::class);

            // PendingPayment -> Processing (via PaymentConfirmed transition)
            $transition = new PaymentConfirmed($order, 'txn_123', 'stripe', 10000);
            $order = $transition->handle();
            expect($order->status)->toBeInstanceOf(Processing::class);

            // Processing -> Shipped (via ShipmentCreated transition)
            $transition = new ShipmentCreated($order, 'J&T', 'JT123456789', 'ship_123');
            $order = $transition->handle();
            expect($order->status)->toBeInstanceOf(Shipped::class);

            // Shipped -> Delivered (via DeliveryConfirmed transition)
            $transition = new DeliveryConfirmed($order);
            $order = $transition->handle();
            expect($order->status)->toBeInstanceOf(Delivered::class);

            // Delivered -> Completed
            $order->status->transitionTo(Completed::class);
            expect($order->status)->toBeInstanceOf(Completed::class);
        });

        it('can handle order cancellation flow', function (): void {
            // Start with PendingPayment
            $order = Order::create([
                'order_number' => 'ORD-FLOW2-' . uniqid(),
                'status' => PendingPayment::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            // PendingPayment -> Canceled (via OrderCanceled transition)
            $transition = new OrderCanceled($order, 'Customer request');
            $order = $transition->handle();
            expect($order->status)->toBeInstanceOf(Canceled::class);
        });

        it('can handle processing issues', function (): void {
            // Start with Processing
            $order = Order::create([
                'order_number' => 'ORD-FLOW3-' . uniqid(),
                'status' => Processing::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            // Processing -> OnHold
            $order->status->transitionTo(OnHold::class);
            expect($order->status)->toBeInstanceOf(OnHold::class);

            // OnHold -> Processing
            $order->status->transitionTo(Processing::class);
            expect($order->status)->toBeInstanceOf(Processing::class);

            // Processing -> Fraud
            $order->status->transitionTo(Fraud::class);
            expect($order->status)->toBeInstanceOf(Fraud::class);
        });

        it('can handle payment failures', function (): void {
            // Start with PendingPayment
            $order = Order::create([
                'order_number' => 'ORD-FLOW4-' . uniqid(),
                'status' => PendingPayment::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            // PendingPayment -> PaymentFailed
            $order->status->transitionTo(PaymentFailed::class);
            expect($order->status)->toBeInstanceOf(PaymentFailed::class);
        });

        it('can handle returns and refunds', function (): void {
            // Start with Shipped
            $order = Order::create([
                'order_number' => 'ORD-FLOW5-' . uniqid(),
                'status' => Shipped::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            // Shipped -> Returned
            $order->status->transitionTo(Returned::class);
            expect($order->status)->toBeInstanceOf(Returned::class);

            // Returned -> Refunded (via RefundProcessed transition)
            $transition = new RefundProcessed($order, 5000, 'ref_txn_123', 'Customer return');
            $order = $transition->handle();
            expect($order->status)->toBeInstanceOf(Refunded::class);
        });
    });
});