<?php

declare(strict_types=1);

use AIArmada\Orders\Events\OrderCanceled;
use AIArmada\Orders\Events\OrderCreated;
use AIArmada\Orders\Events\OrderDelivered;
use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Orders\Events\OrderRefunded;
use AIArmada\Orders\Events\OrderShipped;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Canceled;
use AIArmada\Orders\States\Completed;
use AIArmada\Orders\States\Created;
use AIArmada\Orders\States\Delivered;
use AIArmada\Orders\States\Shipped;

describe('Order Events', function (): void {
    describe('OrderCreated Event', function (): void {
        it('can be instantiated with an order', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-EVENT1-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $event = new OrderCreated($order);

            expect($event->order)->toBe($order);
            expect($event->order->status)->toBeInstanceOf(Created::class);
        });
    });

    describe('OrderPaid Event', function (): void {
        it('can be instantiated with a paid order', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-EVENT2-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
                'paid_at' => now(),
            ]);

            $event = new OrderPaid($order, 'txn_123', 'stripe');

            expect($event->order)->toBe($order);
            expect($event->transactionId)->toBe('txn_123');
            expect($event->gateway)->toBe('stripe');
            expect($event->order->isPaid())->toBeTrue();
        });
    });

    describe('OrderShipped Event', function (): void {
        it('can be instantiated with a shipped order', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-EVENT3-' . uniqid(),
                'status' => Shipped::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
                'shipped_at' => now(),
            ]);

            $event = new OrderShipped($order, 'J&T', 'JT123456789', 'ship_123');

            expect($event->order)->toBe($order);
            expect($event->carrier)->toBe('J&T');
            expect($event->trackingNumber)->toBe('JT123456789');
            expect($event->shipmentId)->toBe('ship_123');
            expect($event->order->isShipped())->toBeTrue();
        });
    });

    describe('OrderDelivered Event', function (): void {
        it('can be instantiated with a delivered order', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-EVENT4-' . uniqid(),
                'status' => Delivered::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
                'delivered_at' => now(),
            ]);

            $event = new OrderDelivered($order);

            expect($event->order)->toBe($order);
            expect($event->order->isDelivered())->toBeTrue();
        });
    });

    describe('OrderCanceled Event', function (): void {
        it('can be instantiated with a canceled order', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-EVENT5-' . uniqid(),
                'status' => Canceled::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
                'canceled_at' => now(),
            ]);

            $event = new OrderCanceled($order, 'Customer request');

            expect($event->order)->toBe($order);
            expect($event->reason)->toBe('Customer request');
            expect($event->order->isCanceled())->toBeTrue();
        });
    });

    describe('OrderRefunded Event', function (): void {
        it('can be instantiated with a refunded order', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-EVENT6-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $event = new OrderRefunded($order, 5000, 'Customer request');

            expect($event->order)->toBe($order);
            expect($event->amount)->toBe(5000);
            expect($event->reason)->toBe('Customer request');
        });
    });
});