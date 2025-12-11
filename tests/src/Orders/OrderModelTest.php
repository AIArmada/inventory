<?php

declare(strict_types=1);

use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\Models\OrderRefund;
use AIArmada\Orders\States\Canceled;
use AIArmada\Orders\States\Completed;
use AIArmada\Orders\States\Created;

describe('Order Model', function (): void {
    describe('Order Creation', function (): void {
        it('can create an order', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10600,
            ]);

            expect($order)->toBeInstanceOf(Order::class)
                ->and($order->subtotal)->toBe(10000);
        });

        it('generates unique order numbers', function (): void {
            $order1 = Order::create([
                'order_number' => 'ORD-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 5000,
                'grand_total' => 5000,
            ]);

            $order2 = Order::create([
                'order_number' => 'ORD-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 5000,
                'grand_total' => 5000,
            ]);

            expect($order1->order_number)->not->toBe($order2->order_number);
        });
    });

    describe('Order Totals', function (): void {
        it('can format subtotal', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-FMT1-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10050,
                'grand_total' => 10650,
            ]);

            expect($order->getFormattedSubtotal())->toContain('100.50');
        });

        it('can format grand total', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-FMT2-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10600,
            ]);

            expect($order->getFormattedGrandTotal())->toContain('106.00');
        });
    });

    describe('Order Relationships', function (): void {
        it('can have order items', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-REL1-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'name' => 'Product 1',
                'quantity' => 2,
                'unit_price' => 2500,
                'total' => 5000,
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'name' => 'Product 2',
                'quantity' => 1,
                'unit_price' => 5000,
                'total' => 5000,
            ]);

            $order->refresh();

            expect($order->items)->toHaveCount(2);
        });
    });

    describe('Payment Tracking', function (): void {
        it('can track total paid', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PAY1-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'manual',
                'amount' => 5000,
                'currency' => 'MYR',
                'status' => 'completed',
            ]);

            OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'manual',
                'amount' => 5000,
                'currency' => 'MYR',
                'status' => 'completed',
            ]);

            expect($order->getTotalPaid())->toBe(10000);
        });

        it('can check if order is fully paid', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PAY2-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 5000,
                'grand_total' => 5000,
            ]);

            OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'manual',
                'amount' => 5000,
                'currency' => 'MYR',
                'status' => 'completed',
            ]);

            expect($order->isFullyPaid())->toBeTrue();
        });

        it('can calculate balance due', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PAY3-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'manual',
                'amount' => 4000,
                'currency' => 'MYR',
                'status' => 'completed',
            ]);

            expect($order->getBalanceDue())->toBe(6000);
        });
    });

    describe('Refund Tracking', function (): void {
        it('can track total refunded', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-REF1-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            OrderRefund::create([
                'order_id' => $order->id,
                'gateway' => 'manual',
                'amount' => 2000,
                'currency' => 'MYR',
                'reason' => 'Customer request',
                'status' => 'completed',
            ]);

            OrderRefund::create([
                'order_id' => $order->id,
                'gateway' => 'manual',
                'amount' => 1000,
                'currency' => 'MYR',
                'reason' => 'Partial refund',
                'status' => 'completed',
            ]);

            expect($order->getTotalRefunded())->toBe(3000);
        });
    });

    describe('Order Soft Deletes', function (): void {
        it('can soft delete an order', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-DEL-' . uniqid(),
                'status' => Canceled::class,
                'currency' => 'MYR',
                'subtotal' => 5000,
                'grand_total' => 5000,
            ]);

            $id = $order->id;
            $order->delete();

            expect(Order::find($id))->toBeNull()
                ->and(Order::withTrashed()->find($id))->not->toBeNull();
        });
    });
});
