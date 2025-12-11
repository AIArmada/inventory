<?php

declare(strict_types=1);

use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderAddress;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\States\Created;

describe('OrderItem Model', function (): void {
    describe('OrderItem Creation', function (): void {
        it('can create an order item', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ITEM1-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 5000,
                'grand_total' => 5000,
            ]);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'name' => 'Test Product',
                'sku' => 'SKU-001',
                'quantity' => 2,
                'unit_price' => 2500,
                'total' => 5000,
            ]);

            expect($item)->toBeInstanceOf(OrderItem::class)
                ->and($item->name)->toBe('Test Product')
                ->and($item->quantity)->toBe(2);
        });

        it('belongs to an order', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ITEM2-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 3000,
                'grand_total' => 3000,
            ]);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'name' => 'Product X',
                'quantity' => 1,
                'unit_price' => 3000,
                'total' => 3000,
            ]);

            expect($item->order->id)->toBe($order->id);
        });
    });

    describe('OrderItem Calculations', function (): void {
        it('can calculate total with discount', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ITEM3-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 4500,
                'grand_total' => 4500,
            ]);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'name' => 'Discounted Product',
                'quantity' => 2,
                'unit_price' => 2500,
                'discount_amount' => 500,
                'total' => 4500,
            ]);

            expect($item->unit_price)->toBe(2500)
                ->and($item->discount_amount)->toBe(500)
                ->and($item->total)->toBe(4500);
        });

        it('can store metadata', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ITEM4-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 2000,
                'grand_total' => 2000,
            ]);

            $metadata = ['color' => 'red', 'size' => 'L'];

            $item = OrderItem::create([
                'order_id' => $order->id,
                'name' => 'Custom Product',
                'quantity' => 1,
                'unit_price' => 2000,
                'total' => 2000,
                'metadata' => $metadata,
            ]);

            expect($item->metadata)->toBe($metadata);
        });
    });
});

describe('OrderAddress Model', function (): void {
    describe('OrderAddress Creation', function (): void {
        it('can create a shipping address', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ADDR1-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 5000,
                'grand_total' => 5000,
            ]);

            $address = OrderAddress::create([
                'order_id' => $order->id,
                'type' => 'shipping',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'line1' => '123 Ship Street',
                'city' => 'Kuala Lumpur',
                'state' => 'KL',
                'postcode' => '50000',
                'country_code' => 'MY',
            ]);

            expect($address)->toBeInstanceOf(OrderAddress::class)
                ->and($address->type)->toBe('shipping')
                ->and($address->city)->toBe('Kuala Lumpur');
        });

        it('can create both shipping and billing addresses', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ADDR2-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 5000,
                'grand_total' => 5000,
            ]);

            $shipping = OrderAddress::create([
                'order_id' => $order->id,
                'type' => 'shipping',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'line1' => 'Ship Address',
                'city' => 'KL',
                'postcode' => '50000',
                'country_code' => 'MY',
            ]);

            $billing = OrderAddress::create([
                'order_id' => $order->id,
                'type' => 'billing',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'line1' => 'Bill Address',
                'city' => 'PJ',
                'postcode' => '47500',
                'country_code' => 'MY',
            ]);

            expect($shipping->type)->toBe('shipping')
                ->and($billing->type)->toBe('billing');
        });
    });

    describe('OrderAddress Relationship', function (): void {
        it('belongs to an order', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ADDR3-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 5000,
                'grand_total' => 5000,
            ]);

            $address = OrderAddress::create([
                'order_id' => $order->id,
                'type' => 'shipping',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'line1' => 'Main Street',
                'city' => 'KL',
                'postcode' => '50000',
                'country_code' => 'MY',
            ]);

            expect($address->order->id)->toBe($order->id);
        });
    });

    describe('OrderAddress Full Name', function (): void {
        it('can compose full name', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ADDR4-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 5000,
                'grand_total' => 5000,
            ]);

            $address = OrderAddress::create([
                'order_id' => $order->id,
                'type' => 'shipping',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'line1' => 'Main Street',
                'city' => 'KL',
                'postcode' => '50000',
                'country_code' => 'MY',
            ]);

            expect($address->getFullName())->toBe('John Doe');
        });
    });
});
