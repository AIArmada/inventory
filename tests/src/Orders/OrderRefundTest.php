<?php

declare(strict_types=1);

use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\Models\OrderRefund;
use AIArmada\Orders\States\Completed;

describe('OrderRefund Model', function (): void {
    describe('OrderRefund Creation', function (): void {
        it('can create a refund', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-REF1-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $refund = OrderRefund::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 5000,
                'currency' => 'MYR',
                'reason' => 'Customer request',
                'status' => 'pending',
            ]);

            expect($refund)->toBeInstanceOf(OrderRefund::class)
                ->and($refund->gateway)->toBe('stripe')
                ->and($refund->amount)->toBe(5000)
                ->and($refund->reason)->toBe('Customer request')
                ->and($refund->status)->toBe('pending');
        });

        it('belongs to an order', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-REF2-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $refund = OrderRefund::create([
                'order_id' => $order->id,
                'gateway' => 'manual',
                'amount' => 2000,
                'currency' => 'MYR',
                'reason' => 'Partial refund',
                'status' => 'completed',
            ]);

            expect($refund->order->id)->toBe($order->id);
        });

        it('can belong to a payment', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-REF3-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $payment = OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 10000,
                'currency' => 'MYR',
                'status' => 'completed',
            ]);

            $refund = OrderRefund::create([
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'gateway' => 'stripe',
                'amount' => 3000,
                'currency' => 'MYR',
                'reason' => 'Item return',
                'status' => 'completed',
            ]);

            expect($refund->payment->id)->toBe($payment->id);
        });
    });

    describe('OrderRefund Status Helpers', function (): void {
        it('can check refund status', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-REF4-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $pending = OrderRefund::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 2000,
                'currency' => 'MYR',
                'reason' => 'Test',
                'status' => 'pending',
            ]);

            $completed = OrderRefund::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 3000,
                'currency' => 'MYR',
                'reason' => 'Test',
                'status' => 'completed',
            ]);

            $failed = OrderRefund::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 1000,
                'currency' => 'MYR',
                'reason' => 'Test',
                'status' => 'failed',
            ]);

            expect($pending->isPending())->toBeTrue()
                ->and($pending->isCompleted())->toBeFalse()
                ->and($pending->isFailed())->toBeFalse();

            expect($completed->isPending())->toBeFalse()
                ->and($completed->isCompleted())->toBeTrue()
                ->and($completed->isFailed())->toBeFalse();

            expect($failed->isPending())->toBeFalse()
                ->and($failed->isCompleted())->toBeFalse()
                ->and($failed->isFailed())->toBeTrue();
        });
    });

    describe('OrderRefund Actions', function (): void {
        it('can mark refund as completed', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-REF5-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $refund = OrderRefund::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 5000,
                'currency' => 'MYR',
                'reason' => 'Customer request',
                'status' => 'pending',
            ]);

            $result = $refund->markAsCompleted('ref_txn_123');

            expect($result)->toBe($refund)
                ->and($refund->status)->toBe('completed')
                ->and($refund->transaction_id)->toBe('ref_txn_123')
                ->and($refund->refunded_at)->not->toBeNull();
        });

        it('can mark refund as failed', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-REF6-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $refund = OrderRefund::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 5000,
                'currency' => 'MYR',
                'reason' => 'Customer request',
                'status' => 'pending',
            ]);

            $result = $refund->markAsFailed('Gateway error');

            expect($result)->toBe($refund)
                ->and($refund->status)->toBe('failed')
                ->and($refund->notes)->toBe('Gateway error');
        });
    });

    describe('OrderRefund Formatting', function (): void {
        it('can format amount in different currencies', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-REF7-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $myrRefund = OrderRefund::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 7500,
                'currency' => 'MYR',
                'reason' => 'Test',
                'status' => 'completed',
            ]);

            $usdRefund = OrderRefund::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 2500,
                'currency' => 'USD',
                'reason' => 'Test',
                'status' => 'completed',
            ]);

            $eurRefund = OrderRefund::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 1500,
                'currency' => 'EUR',
                'reason' => 'Test',
                'status' => 'completed',
            ]);

            $gbpRefund = OrderRefund::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 500,
                'currency' => 'GBP',
                'reason' => 'Test',
                'status' => 'completed',
            ]);

            expect($myrRefund->getFormattedAmount())->toBe('RM75.00');
            expect($usdRefund->getFormattedAmount())->toBe('$25.00');
            expect($eurRefund->getFormattedAmount())->toBe('€15.00');
            expect($gbpRefund->getFormattedAmount())->toBe('£5.00');
        });
    });
});
