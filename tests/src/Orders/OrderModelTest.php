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

    describe('Order Number Generation', function (): void {
        it('generates order numbers with default config', function (): void {
            $orderNumber = Order::generateOrderNumber();

            expect($orderNumber)->toStartWith('ORD-')
                ->and($orderNumber)->toContain(date('Ymd'))
                ->and(strlen($orderNumber))->toBeGreaterThan(20);
        });

        it('auto-generates order number when creating order', function (): void {
            $order = Order::create([
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->order_number)->toStartWith('ORD-')
                ->and($order->order_number)->toContain(date('Ymd'));
        });
    });

    describe('Order Relationships', function (): void {
        it('can have addresses', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-ADDR-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $billingAddress = \AIArmada\Orders\Models\OrderAddress::create([
                'order_id' => $order->id,
                'type' => 'billing',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'phone' => '123456789',
                'line1' => '123 Main St',
                'city' => 'Kuala Lumpur',
                'state' => 'KL',
                'postcode' => '50000',
                'country_code' => 'MY',
            ]);

            $shippingAddress = \AIArmada\Orders\Models\OrderAddress::create([
                'order_id' => $order->id,
                'type' => 'shipping',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane@example.com',
                'phone' => '987654321',
                'line1' => '456 Oak St',
                'city' => 'Penang',
                'state' => 'PG',
                'postcode' => '10000',
                'country_code' => 'MY',
            ]);

            $order->refresh();

            expect($order->addresses)->toHaveCount(2)
                ->and($order->billingAddress)->not->toBeNull()
                ->and($order->billingAddress->first_name)->toBe('John')
                ->and($order->shippingAddress)->not->toBeNull()
                ->and($order->shippingAddress->first_name)->toBe('Jane');
        });

        it('can have payments', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PAY-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            OrderPayment::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 10000,
                'currency' => 'MYR',
                'status' => 'completed',
            ]);

            $order->refresh();

            expect($order->payments)->toHaveCount(1)
                ->and($order->payments->first()->gateway)->toBe('stripe');
        });

        it('can have refunds', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-REF-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            OrderRefund::create([
                'order_id' => $order->id,
                'gateway' => 'stripe',
                'amount' => 2000,
                'currency' => 'MYR',
                'reason' => 'Customer request',
                'status' => 'completed',
            ]);

            $order->refresh();

            expect($order->refunds)->toHaveCount(1)
                ->and($order->refunds->first()->amount)->toBe(2000);
        });

        it('can have order notes', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-NOTE-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            \AIArmada\Orders\Models\OrderNote::create([
                'order_id' => $order->id,
                'content' => 'Customer called about delivery',
                'is_customer_visible' => true,
            ]);

            $order->refresh();

            expect($order->orderNotes)->toHaveCount(1)
                ->and($order->orderNotes->first()->content)->toBe('Customer called about delivery');
        });
    });

    describe('Order Status Helpers', function (): void {
        it('can check if order is paid', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-PAID-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
                'paid_at' => now(),
            ]);

            expect($order->isPaid())->toBeTrue();
        });

        it('can check if order is shipped', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-SHIP-' . uniqid(),
                'status' => \AIArmada\Orders\States\Shipped::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
                'shipped_at' => now(),
            ]);

            expect($order->isShipped())->toBeTrue();
        });

        it('can check if order is delivered', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-DELIV-' . uniqid(),
                'status' => \AIArmada\Orders\States\Delivered::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
                'delivered_at' => now(),
            ]);

            expect($order->isDelivered())->toBeTrue();
        });

        it('can check if order is canceled', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-CANC-' . uniqid(),
                'status' => Canceled::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
                'canceled_at' => now(),
            ]);

            expect($order->isCanceled())->toBeTrue();
        });

        it('can check order status capabilities', function (): void {
            $createdOrder = Order::create([
                'order_number' => 'ORD-CAP1-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $completedOrder = Order::create([
                'order_number' => 'ORD-CAP2-' . uniqid(),
                'status' => Completed::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $canceledOrder = Order::create([
                'order_number' => 'ORD-CAP3-' . uniqid(),
                'status' => Canceled::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            // Test canBeCanceled - created orders can be canceled, completed/canceled cannot
            expect($createdOrder->canBeCanceled())->toBeTrue()
                ->and($completedOrder->canBeCanceled())->toBeFalse()
                ->and($canceledOrder->canBeCanceled())->toBeFalse();

            // Test canBeRefunded - completed orders can be refunded, created/canceled cannot
            expect($createdOrder->canBeRefunded())->toBeFalse()
                ->and($completedOrder->canBeRefunded())->toBeTrue()
                ->and($canceledOrder->canBeRefunded())->toBeFalse();

            // Test canBeModified - created orders can be modified, completed/canceled cannot
            expect($createdOrder->canBeModified())->toBeTrue()
                ->and($completedOrder->canBeModified())->toBeFalse()
                ->and($canceledOrder->canBeModified())->toBeFalse();

            // Test isFinal - completed and canceled orders are final, created is not
            expect($createdOrder->isFinal())->toBeFalse()
                ->and($completedOrder->isFinal())->toBeTrue()
                ->and($canceledOrder->isFinal())->toBeTrue();
        });
    });

    describe('Order Money Formatting', function (): void {
        it('can format discount total', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-DISC-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'discount_total' => 1000,
                'grand_total' => 9000,
            ]);

            expect($order->getFormattedDiscountTotal())->toBe('RM10.00');
        });

        it('can format shipping total', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-SHIP-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'shipping_total' => 500,
                'grand_total' => 10500,
            ]);

            expect($order->getFormattedShippingTotal())->toBe('RM5.00');
        });

        it('can format tax total', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-TAX-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'tax_total' => 600,
                'grand_total' => 10600,
            ]);

            expect($order->getFormattedTaxTotal())->toBe('RM6.00');
        });

        it('formats money in different currencies', function (): void {
            $usdOrder = Order::create([
                'order_number' => 'ORD-USD-' . uniqid(),
                'status' => Created::class,
                'currency' => 'USD',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $eurOrder = Order::create([
                'order_number' => 'ORD-EUR-' . uniqid(),
                'status' => Created::class,
                'currency' => 'EUR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($usdOrder->getFormattedGrandTotal())->toBe('$100.00');
            expect($eurOrder->getFormattedGrandTotal())->toBe('€100.00');
        });
    });

    describe('Order Item Helpers', function (): void {
        it('can get item count', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-COUNT-' . uniqid(),
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
                'quantity' => 3,
                'unit_price' => 1667,
                'total' => 5000,
            ]);

            expect($order->getItemCount())->toBe(5);
        });

        it('can recalculate totals', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-RECALC-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 0,
                'shipping_total' => 1000,
                'discount_total' => 500,
                'grand_total' => 0,
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'name' => 'Product 1',
                'quantity' => 1,
                'unit_price' => 5000,
                'tax_amount' => 300,
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'name' => 'Product 2',
                'quantity' => 1,
                'unit_price' => 3000,
                'tax_amount' => 180,
            ]);

            $order->refresh();

            // Check items were created correctly
            expect($order->items)->toHaveCount(2);

            $order->recalculateTotals();

            // OrderItem automatically calculates total = (quantity * unit_price) + tax_amount
            // Item 1: (1 * 5000) + 300 = 5300
            // Item 2: (1 * 3000) + 180 = 3180
            // Subtotal: 5300 + 3180 = 8480
            // Tax total: 300 + 180 = 480
            // Grand total: 8480 + 1000 + 480 - 500 = 9460

            expect($order->subtotal)->toBe(8480)
                ->and($order->tax_total)->toBe(480)
                ->and($order->grand_total)->toBe(9460);
        });
    });

    describe('Order Audit', function (): void {
        it('has audit include attributes', function (): void {
            $order = new Order();

            $auditInclude = $order->getAuditInclude();

            expect($auditInclude)->toContain('status')
                ->and($auditInclude)->toContain('subtotal')
                ->and($auditInclude)->toContain('grand_total')
                ->and($auditInclude)->toContain('paid_at');
        });
    });

    describe('Order Factory', function (): void {
        it('can create order using factory', function (): void {
            $order = Order::factory()->create();

            expect($order)->toBeInstanceOf(Order::class)
                ->and($order->order_number)->toStartWith('ORD-')
                ->and($order->status)->toBeInstanceOf(Created::class);
        });

        it('can format money with JPY currency', function (): void {
            $order = Order::create([
                'order_number' => 'ORD-JPY-' . uniqid(),
                'status' => Created::class,
                'currency' => 'JPY',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            expect($order->getFormattedGrandTotal())->toBe('JPY 100.00');
        });
    });
});
