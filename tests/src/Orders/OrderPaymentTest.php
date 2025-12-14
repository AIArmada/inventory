<?php

declare(strict_types=1);

use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\States\Completed;
use AIArmada\Orders\States\Created;

describe('OrderPayment Model', function (): void {
    describe('OrderPayment Creation', function (): void {
        it('can create a payment', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PAY1-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $payment = OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 10000,
                'currency' => 'MYR',
                'status' => 'pending',
            ]);

            expect($payment)->toBeInstanceOf(OrderPayment::class)
                ->and($payment->gateway)->toBe('stripe')
                ->and($payment->amount)->toBe(10000)
                ->and($payment->status)->toBe('pending');
        });

        it('belongs to an order', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PAY2-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 5000,
                'grand_total' => 5000,
            ]);

            $payment = OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'manual',
                'amount' => 5000,
                'currency' => 'MYR',
                'status' => 'completed',
            ]);

            expect($payment->order->id)->toBe($order->id);
        });
    });

    describe('OrderPayment Status Helpers', function (): void {
        it('can check payment status', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PAY3-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $pending = OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 5000,
                'currency' => 'MYR',
                'status' => 'pending',
            ]);

            $completed = OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 3000,
                'currency' => 'MYR',
                'status' => 'completed',
            ]);

            $failed = OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 2000,
                'currency' => 'MYR',
                'status' => 'failed',
            ]);

            expect($pending->isPending())->toBeTrue()
                ->and($pending->isCompleted())->toBeFalse()
                ->and($pending->isFailed())->toBeFalse()
                ->and($pending->isRefunded())->toBeFalse();

            expect($completed->isPending())->toBeFalse()
                ->and($completed->isCompleted())->toBeTrue()
                ->and($completed->isFailed())->toBeFalse()
                ->and($completed->isRefunded())->toBeFalse();

            expect($failed->isPending())->toBeFalse()
                ->and($failed->isCompleted())->toBeFalse()
                ->and($failed->isFailed())->toBeTrue()
                ->and($failed->isRefunded())->toBeFalse();
        });
    });

    describe('OrderPayment Actions', function (): void {
        it('can mark payment as completed', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PAY4-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $payment = OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 10000,
                'currency' => 'MYR',
                'status' => 'pending',
            ]);

            $result = $payment->markAsCompleted('txn_123');

            expect($result)->toBe($payment)
                ->and($payment->status)->toBe('completed')
                ->and($payment->transaction_id)->toBe('txn_123')
                ->and($payment->paid_at)->not->toBeNull();
        });

        it('can mark payment as failed', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PAY5-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $payment = OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 10000,
                'currency' => 'MYR',
                'status' => 'pending',
            ]);

            $result = $payment->markAsFailed('Card declined');

            expect($result)->toBe($payment)
                ->and($payment->status)->toBe('failed')
                ->and($payment->failure_reason)->toBe('Card declined');
        });
    });

    describe('OrderPayment Formatting', function (): void {
        it('can format amount in different currencies', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PAY6-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $myrPayment = OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 10000,
                'currency' => 'MYR',
                'status' => 'completed',
            ]);

            $usdPayment = OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 5000,
                'currency' => 'USD',
                'status' => 'completed',
            ]);

            $eurPayment = OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 7500,
                'currency' => 'EUR',
                'status' => 'completed',
            ]);

            $gbpPayment = OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 2500,
                'currency' => 'GBP',
                'status' => 'completed',
            ]);

            expect($myrPayment->getFormattedAmount())->toBe('RM100.00');
            expect($usdPayment->getFormattedAmount())->toBe('$50.00');
            expect($eurPayment->getFormattedAmount())->toBe('€75.00');
            expect($gbpPayment->getFormattedAmount())->toBe('£25.00');
        });
    });
});