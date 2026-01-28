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

        it('can format unit price', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ITEM5-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'name' => 'Formatted Product',
                'quantity' => 1,
                'unit_price' => 10000,
                'currency' => 'MYR',
            ]);

            expect($item->getFormattedUnitPrice())->toBe('RM100.00');
        });

        it('can format total', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ITEM6-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 5000,
                'grand_total' => 5000,
            ]);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'name' => 'Total Product',
                'quantity' => 1,
                'unit_price' => 5000,
                'currency' => 'USD',
            ]);

            expect($item->getFormattedTotal())->toBe('$50.00');
        });

        it('can format with unknown currency', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ITEM7-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 5000,
                'grand_total' => 5000,
            ]);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'name' => 'Unknown Currency Product',
                'quantity' => 1,
                'unit_price' => 5000,
                'currency' => 'XYZ',
            ]);

            expect($item->getFormattedTotal())->toBe('XYZ 50.00');
        });

        it('can format with EUR currency', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ITEM8-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 5000,
                'grand_total' => 5000,
            ]);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'name' => 'EUR Product',
                'quantity' => 1,
                'unit_price' => 5000,
                'currency' => 'EUR',
            ]);

            // EUR uses European formatting (comma as decimal separator)
            expect($item->getFormattedTotal())->toBe('€50,00');
        });

        it('can format with GBP currency', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ITEM9-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 5000,
                'grand_total' => 5000,
            ]);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'name' => 'GBP Product',
                'quantity' => 1,
                'unit_price' => 5000,
                'currency' => 'GBP',
            ]);

            expect($item->getFormattedTotal())->toBe('£50.00');
        });

        it('can calculate total correctly', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ITEM7-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 9000,
                'grand_total' => 9000,
            ]);

            $item = new OrderItem([
                'order_id' => $order->id,
                'name' => 'Calc Product',
                'quantity' => 2,
                'unit_price' => 5000, // 10000 subtotal
                'discount_amount' => 1000, // 9000 after discount
                'tax_amount' => 1000, // 10000 final total
            ]);

            expect($item->calculateTotal())->toBe(10000); // (2*5000) - 1000 + 1000
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
                'country' => 'MY',
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
                'country' => 'MY',
            ]);

            $billing = OrderAddress::create([
                'order_id' => $order->id,
                'type' => 'billing',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'line1' => 'Bill Address',
                'city' => 'PJ',
                'postcode' => '47500',
                'country' => 'MY',
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
                'country' => 'MY',
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
                'country' => 'MY',
            ]);

            expect($address->getFullName())->toBe('John Doe');
        });
    });

    describe('OrderAddress Type Helpers', function (): void {
        it('can check if address is billing', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ADDR5-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 5000,
                'grand_total' => 5000,
            ]);

            $billing = OrderAddress::create([
                'order_id' => $order->id,
                'type' => 'billing',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'line1' => 'Bill Street',
                'city' => 'KL',
                'postcode' => '50000',
                'country' => 'MY',
            ]);

            $shipping = OrderAddress::create([
                'order_id' => $order->id,
                'type' => 'shipping',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'line1' => 'Ship Street',
                'city' => 'KL',
                'postcode' => '50000',
                'country' => 'MY',
            ]);

            expect($billing->isBilling())->toBeTrue()
                ->and($billing->isShipping())->toBeFalse()
                ->and($shipping->isBilling())->toBeFalse()
                ->and($shipping->isShipping())->toBeTrue();
        });
    });

    describe('OrderAddress Formatting', function (): void {
        it('can format address as one line', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ADDR6-' . uniqid(),
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
                'line1' => '123 Main Street',
                'line2' => 'Floor 5',
                'city' => 'Kuala Lumpur',
                'state' => 'KL',
                'postcode' => '50000',
                'country' => 'MY',
            ]);

            $oneLine = $address->getOneLine();
            expect($oneLine)->toContain('123 Main Street')
                ->and($oneLine)->toContain('Floor 5')
                ->and($oneLine)->toContain('Kuala Lumpur')
                ->and($oneLine)->toContain('KL')
                ->and($oneLine)->toContain('50000')
                ->and($oneLine)->toContain('MY');
        });

        it('can format address as multi-line', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ADDR7-' . uniqid(),
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
                'company' => 'ACME Corp',
                'line1' => '123 Main Street',
                'line2' => 'Floor 5',
                'city' => 'Kuala Lumpur',
                'state' => 'KL',
                'postcode' => '50000',
                'country' => 'MY',
            ]);

            $formatted = $address->getFormatted();
            $lines = explode("\n", $formatted);

            expect($lines)->toHaveCount(6)
                ->and($lines[0])->toBe('John Doe')
                ->and($lines[1])->toBe('ACME Corp')
                ->and($lines[2])->toBe('123 Main Street')
                ->and($lines[3])->toBe('Floor 5')
                ->and($lines[4])->toContain('Kuala Lumpur')
                ->and($lines[5])->toBe('MY');
        });

        it('can convert to address array', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ADDR8-' . uniqid(),
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
                'company' => 'ACME Corp',
                'line1' => '123 Main Street',
                'line2' => 'Floor 5',
                'city' => 'Kuala Lumpur',
                'state' => 'KL',
                'postcode' => '50000',
                'country' => 'MY',
                'phone' => '0123456789',
                'email' => 'john@example.com',
            ]);

            $array = $address->toAddressArray();

            expect($array)->toHaveKey('name', 'John Doe')
                ->and($array)->toHaveKey('company', 'ACME Corp')
                ->and($array)->toHaveKey('line1', '123 Main Street')
                ->and($array)->toHaveKey('line2', 'Floor 5')
                ->and($array)->toHaveKey('city', 'Kuala Lumpur')
                ->and($array)->toHaveKey('state', 'KL')
                ->and($array)->toHaveKey('postcode', '50000')
                ->and($array)->toHaveKey('country', 'MY')
                ->and($array)->toHaveKey('phone', '0123456789')
                ->and($array)->toHaveKey('email', 'john@example.com');
        });
    });
});
