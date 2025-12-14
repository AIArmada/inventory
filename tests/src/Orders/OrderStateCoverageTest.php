<?php

declare(strict_types=1);

use AIArmada\Orders\Models\Order;
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

describe('Order State Classes - Comprehensive Coverage', function (): void {
    describe('Delivered State Coverage', function (): void {
        it('can transition to Delivered state', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-DELIVERED-' . uniqid(),
                'status' => Shipped::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $order->status->transitionTo(Delivered::class);
            expect($order->status)->toBeInstanceOf(Delivered::class);
        });
    });

    describe('Fraud State Coverage', function (): void {
        it('can transition to Fraud state', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-FRAUD-' . uniqid(),
                'status' => Processing::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $order->status->transitionTo(Fraud::class);
            expect($order->status)->toBeInstanceOf(Fraud::class);
        });
    });

    describe('OnHold State Coverage', function (): void {
        it('can transition to OnHold state', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ONHOLD-' . uniqid(),
                'status' => Processing::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $order->status->transitionTo(OnHold::class);
            expect($order->status)->toBeInstanceOf(OnHold::class);
        });

        it('can transition from OnHold back to Processing', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ONHOLD-PROC-' . uniqid(),
                'status' => OnHold::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $order->status->transitionTo(Processing::class);
            expect($order->status)->toBeInstanceOf(Processing::class);
        });
    });

    describe('PaymentFailed State Coverage', function (): void {
        it('can transition to PaymentFailed state', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PAYFAIL-' . uniqid(),
                'status' => PendingPayment::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $order->status->transitionTo(PaymentFailed::class);
            expect($order->status)->toBeInstanceOf(PaymentFailed::class);
        });
    });

    describe('Processing State Coverage', function (): void {
        it('can transition to Processing state', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PROCESSING-' . uniqid(),
                'status' => PendingPayment::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $order->status->transitionTo(Processing::class);
            expect($order->status)->toBeInstanceOf(Processing::class);
        });
    });

    describe('Refunded State Coverage', function (): void {
        it('can transition to Refunded state', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-REFUNDED-' . uniqid(),
                'status' => Returned::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $order->status->transitionTo(Refunded::class);
            expect($order->status)->toBeInstanceOf(Refunded::class);
        });
    });

    describe('Shipped State Coverage', function (): void {
        it('can transition to Shipped state', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-SHIPPED-' . uniqid(),
                'status' => Processing::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $order->status->transitionTo(Shipped::class);
            expect($order->status)->toBeInstanceOf(Shipped::class);
        });
    });

    describe('Complete State Machine Coverage', function (): void {
        it('exercises all state transitions', function (): void {
            // Start with Created
            $order = Order::create([
                'order_number' => 'ORD-FULL-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            // Created -> PendingPayment
            $order->status->transitionTo(PendingPayment::class);
            expect($order->status)->toBeInstanceOf(PendingPayment::class);

            // Test PaymentFailed separately
            $failedOrder = Order::create([
                'order_number' => 'ORD-FAILED-' . uniqid(),
                'status' => PendingPayment::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $failedOrder->status->transitionTo(PaymentFailed::class);
            expect($failedOrder->status)->toBeInstanceOf(PaymentFailed::class);

            // PendingPayment -> Processing
            $order->status->transitionTo(Processing::class);
            expect($order->status)->toBeInstanceOf(Processing::class);

            // Processing -> OnHold
            $order->status->transitionTo(OnHold::class);
            expect($order->status)->toBeInstanceOf(OnHold::class);

            // OnHold -> Processing
            $order->status->transitionTo(Processing::class);
            expect($order->status)->toBeInstanceOf(Processing::class);

            // Processing -> Fraud
            $order->status->transitionTo(Fraud::class);
            expect($order->status)->toBeInstanceOf(Fraud::class);

            // Reset to Processing for normal flow
            $order = Order::create([
                'order_number' => 'ORD-FULL2-' . uniqid(),
                'status' => Processing::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            // Processing -> Shipped
            $order->status->transitionTo(Shipped::class);
            expect($order->status)->toBeInstanceOf(Shipped::class);

            // Shipped -> Delivered
            $order->status->transitionTo(Delivered::class);
            expect($order->status)->toBeInstanceOf(Delivered::class);

            // Delivered -> Completed
            $order->status->transitionTo(Completed::class);
            expect($order->status)->toBeInstanceOf(Completed::class);

            // Test return flow
            $order2 = Order::create([
                'order_number' => 'ORD-RETURN-' . uniqid(),
                'status' => Delivered::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            // Delivered -> Returned
            $order2->status->transitionTo(Returned::class);
            expect($order2->status)->toBeInstanceOf(Returned::class);

            // Returned -> Refunded
            $order2->status->transitionTo(Refunded::class);
            expect($order2->status)->toBeInstanceOf(Refunded::class);
        });

    });
});
