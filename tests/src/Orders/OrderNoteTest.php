<?php

declare(strict_types=1);

use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderNote;
use AIArmada\Orders\States\Created;

describe('OrderNote Model', function (): void {
    describe('OrderNote Creation', function (): void {
        it('can create an order note', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-NOTE1-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 5000,
                'grand_total' => 5000,
            ]);

            $note = OrderNote::create([
                'order_id' => $order->id,
                'content' => 'Customer requested expedited shipping',
                'is_customer_visible' => false,
            ]);

            expect($note)->toBeInstanceOf(OrderNote::class)
                ->and($note->content)->toBe('Customer requested expedited shipping')
                ->and($note->is_customer_visible)->toBeFalse();
        });

        it('belongs to an order', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-NOTE2-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 5000,
                'grand_total' => 5000,
            ]);

            $note = OrderNote::create([
                'order_id' => $order->id,
                'content' => 'Order processed successfully',
                'is_customer_visible' => true,
            ]);

            expect($note->order->id)->toBe($order->id);
        });
    });

    describe('OrderNote Scopes', function (): void {
        it('can scope internal notes', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-NOTE3-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 5000,
                'grand_total' => 5000,
            ]);

            OrderNote::create([
                'order_id' => $order->id,
                'content' => 'Internal note for staff',
                'is_customer_visible' => false,
            ]);

            OrderNote::create([
                'order_id' => $order->id,
                'content' => 'Customer visible update',
                'is_customer_visible' => true,
            ]);

            $internalNotes = OrderNote::internal()->where('order_id', $order->id)->get();
            $customerNotes = OrderNote::customerVisible()->where('order_id', $order->id)->get();

            expect($internalNotes)->toHaveCount(1)
                ->and($internalNotes->first()->is_customer_visible)->toBeFalse()
                ->and($customerNotes)->toHaveCount(1)
                ->and($customerNotes->first()->is_customer_visible)->toBeTrue();
        });
    });
});