<?php

declare(strict_types=1);

use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Services\OrderService;
use AIArmada\Orders\States\Canceled;
use AIArmada\Orders\States\Created;
use AIArmada\Orders\States\Delivered;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use AIArmada\Orders\States\Refunded;
use AIArmada\Orders\States\Returned;
use AIArmada\Orders\States\Shipped;
use AIArmada\Commerce\Tests\Support\Fixtures\TestOwner;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

describe('OrderService', function (): void {
    describe('Order Creation', function (): void {
        it('ignores caller-supplied owner fields and assigns current owner context', function (): void {
            config()->set('orders.owner.enabled', true);
            config()->set('orders.owner.auto_assign_on_create', true);

            Schema::dropIfExists('test_owners');
            Schema::create('test_owners', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->timestamps();
            });

            $ownerA = TestOwner::query()->create(['name' => 'Owner A']);
            $ownerB = TestOwner::query()->create(['name' => 'Owner B']);

            app()->instance(OwnerResolverInterface::class, new class($ownerA) implements OwnerResolverInterface
            {
                public function __construct(
                    private readonly ?Model $owner,
                ) {}

                public function resolve(): ?Model
                {
                    return $this->owner;
                }
            });

            $service = new OrderService;

            $order = $service->createOrder([
                'order_number' => 'ORD-SVC-OWNER-' . uniqid(),
                'owner_type' => $ownerB->getMorphClass(),
                'owner_id' => $ownerB->getKey(),
                'subtotal' => 10000,
                'grand_total' => 10000,
                'currency' => 'MYR',
            ], [
                [
                    'name' => 'Product 1',
                    'quantity' => 1,
                    'unit_price' => 10000,
                    'tax_amount' => 0,
                ],
            ]);

            expect($order->owner_type)->toBe($ownerA->getMorphClass())
                ->and($order->owner_id)->toBe($ownerA->getKey());
        });

        it('can create an order with items and addresses', function (): void {
            $service = new OrderService;

            $orderData = [
                'order_number' => 'ORD-SVC1-' . uniqid(),
                'subtotal' => 10000,
                'shipping_total' => 500,
                'tax_total' => 600,
                'grand_total' => 11100,
                'currency' => 'MYR',
                'notes' => 'Test order',
            ];

            $items = [
                [
                    'name' => 'Product 1',
                    'quantity' => 2,
                    'unit_price' => 2500,
                    'tax_amount' => 300,
                ],
                [
                    'name' => 'Product 2',
                    'quantity' => 1,
                    'unit_price' => 5000,
                    'tax_amount' => 300,
                ],
            ];

            $billingAddress = [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'line1' => '123 Billing St',
                'city' => 'KL',
                'postcode' => '50000',
                'country_code' => 'MY',
            ];

            $shippingAddress = [
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'line1' => '456 Shipping St',
                'city' => 'Penang',
                'postcode' => '10000',
                'country_code' => 'MY',
            ];

            $order = $service->createOrder($orderData, $items, $billingAddress, $shippingAddress);

            expect($order)->toBeInstanceOf(Order::class)
                ->and($order->order_number)->toBe($orderData['order_number'])
                ->and($order->status)->toBeInstanceOf(PendingPayment::class)
                ->and($order->items)->toHaveCount(2)
                ->and($order->billingAddress)->not->toBeNull()
                ->and($order->shippingAddress)->not->toBeNull()
                ->and($order->billingAddress->first_name)->toBe('John')
                ->and($order->shippingAddress->first_name)->toBe('Jane');
        });

        it('can add items to an order', function (): void {
            $service = new OrderService;
            $order = Order::create([
                'order_number' => 'ORD-SVC2-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 0,
                'grand_total' => 0,
            ]);

            $itemData = [
                'name' => 'Test Product',
                'quantity' => 3,
                'unit_price' => 2000,
                'tax_amount' => 180,
                'sku' => 'TEST-001',
            ];

            $item = $service->addItem($order, $itemData);

            expect($item->name)->toBe('Test Product')
                ->and($item->quantity)->toBe(3)
                ->and($item->unit_price)->toBe(2000)
                ->and($item->total)->toBe(6180); // (3*2000) + 180
        });

        it('can add addresses to an order', function (): void {
            $service = new OrderService;
            $order = Order::create([
                'order_number' => 'ORD-SVC3-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 0,
                'grand_total' => 0,
            ]);

            $addressData = [
                'first_name' => 'John',
                'last_name' => 'Smith',
                'line1' => '789 Test Ave',
                'city' => 'JB',
                'postcode' => '80000',
                'country_code' => 'MY',
                'phone' => '0123456789',
            ];

            $service->addAddress($order, $addressData, 'billing');

            $order->refresh();

            expect($order->billingAddress)->not->toBeNull()
                ->and($order->billingAddress->first_name)->toBe('John')
                ->and($order->billingAddress->phone)->toBe('0123456789');
        });
    });

    describe('Order Operations', function (): void {
        it('can recalculate order totals', function (): void {
            $service = new OrderService;
            $order = Order::create([
                'order_number' => 'ORD-SVC4-' . uniqid(),
                'status' => Created::class,
                'currency' => 'MYR',
                'subtotal' => 0,
                'grand_total' => 0,
            ]);

            // Add items
            $service->addItem($order, [
                'name' => 'Item 1',
                'quantity' => 1,
                'unit_price' => 5000,
                'tax_amount' => 300,
            ]);

            $service->addItem($order, [
                'name' => 'Item 2',
                'quantity' => 2,
                'unit_price' => 2500,
                'tax_amount' => 150,
            ]);

            $order->shipping_total = 500;
            $order->discount_total = 200;
            $order->save();

            $updatedOrder = $service->recalculateTotals($order);

            expect($updatedOrder->subtotal)->toBe(10450) // 5300 + 5150 (item totals)
                ->and($updatedOrder->tax_total)->toBe(450) // 300 + 150 (tax_amounts)
                ->and($updatedOrder->grand_total)->toBe(10750); // 10450 + 500 - 200
        });

        it('can create order from cart object', function (): void {
            $service = new OrderService;

            // Mock cart object
            $cart = (object) [
                'subtotal' => 20000,
                'discount' => 2000,
                'shipping' => 1000,
                'tax' => 1200,
                'total' => 19200,
                'currency' => 'MYR',
                'id' => 'cart_123',
                'items' => [
                    (object) [
                        'purchasable_id' => 'prod_1',
                        'purchasable_type' => 'Product',
                        'name' => 'Test Product',
                        'sku' => 'TEST-001',
                        'quantity' => 2,
                        'price' => 8000,
                        'discount' => 0,
                        'tax' => 480,
                        'options' => ['color' => 'red'],
                        'metadata' => ['custom' => 'data'],
                    ],
                ],
            ];

            // Create a simple test model
            $customer = new class extends Illuminate\Database\Eloquent\Model
            {
                protected $table = 'users';

                public function getKey()
                {
                    return 1;
                }

                public function getMorphClass()
                {
                    return 'User';
                }
            };

            $billingAddress = [
                'first_name' => 'John',
                'last_name' => 'Cart',
                'line1' => '123 Cart Street',
                'city' => 'Cart City',
                'postcode' => '12345',
                'country_code' => 'MY',
            ];

            $order = $service->createFromCart($cart, $customer, $billingAddress);

            expect($order)->toBeInstanceOf(Order::class);
            expect($order->subtotal)->toBe(20000);
            expect($order->discount_total)->toBe(2000);
            expect($order->shipping_total)->toBe(1000);
            expect($order->tax_total)->toBe(1200);
            expect($order->grand_total)->toBe(19200);
            expect($order->items)->toHaveCount(1);
            expect($order->billingAddress)->not->toBeNull();
        });
    });

    describe('Order Operations', function (): void {
        it('can cancel an order', function (): void {
            $service = new OrderService;
            $order = Order::create([
                'order_number' => 'ORD-CANCEL-' . uniqid(),
                'status' => PendingPayment::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $result = $service->cancel($order, 'Customer request', 'admin@example.com');

            expect($result)->toBe($order);
            expect($order->status)->toBeInstanceOf(Canceled::class);
            expect($order->cancellation_reason)->toBe('Customer request');
            expect($order->orderNotes)->toHaveCount(1);
        });

        it('can confirm payment for an order', function (): void {
            $service = new OrderService;
            $order = Order::create([
                'order_number' => 'ORD-PAY-CONFIRM-' . uniqid(),
                'status' => PendingPayment::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $result = $service->confirmPayment($order, 'txn_123', 'stripe', 10000);

            expect($result)->toBe($order);
            expect($order->status)->toBeInstanceOf(Processing::class);
            expect($order->paid_at)->not->toBeNull();
            expect($order->payments)->toHaveCount(1);
        });

        it('can ship an order', function (): void {
            $service = new OrderService;
            $order = Order::create([
                'order_number' => 'ORD-SHIP-' . uniqid(),
                'status' => Processing::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $result = $service->ship($order, 'J&T', 'JT123456789', 'ship_123');

            expect($result)->toBe($order);
            expect($order->status)->toBeInstanceOf(Shipped::class);
            expect($order->shipped_at)->not->toBeNull();
        });

        it('can confirm delivery', function (): void {
            $service = new OrderService;
            $order = Order::create([
                'order_number' => 'ORD-DELIVER-' . uniqid(),
                'status' => Shipped::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $result = $service->confirmDelivery($order, ['delivered_by' => 'customer']);

            expect($result)->toBe($order);
            expect($order->status)->toBeInstanceOf(Delivered::class);
            expect($order->delivered_at)->not->toBeNull();
        });

        it('can process refund', function (): void {
            $service = new OrderService;
            $order = Order::create([
                'order_number' => 'ORD-REFUND-' . uniqid(),
                'status' => Returned::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]);

            $result = $service->processRefund($order, 5000, 'ref_txn_123', 'Customer return');

            expect($result)->toBe($order);
            expect($order->status)->toBeInstanceOf(Refunded::class);
            expect($order->refunds)->toHaveCount(1);
            expect($order->refunds->first()->amount)->toBe(5000);
        });
    });
});
