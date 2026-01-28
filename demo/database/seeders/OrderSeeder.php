<?php

declare(strict_types=1);

namespace Database\Seeders;

use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderAddress;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\States\Canceled;
use AIArmada\Orders\States\Delivered;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use AIArmada\Orders\States\Shipped;
use AIArmada\Products\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

final class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $products = Product::all();

        if ($users->isEmpty() || $products->isEmpty()) {
            return;
        }

        $orderStatuses = [PendingPayment::class, Processing::class, Shipped::class, Delivered::class, Canceled::class];

        // Create 20 sample orders
        for ($i = 0; $i < 20; $i++) {
            $user = $users->random();
            /** @var class-string<\AIArmada\Orders\States\OrderStatus> $status */
            $status = $orderStatuses[array_rand($orderStatuses)];
            $itemCount = rand(1, 4);
            $orderProducts = $products->random($itemCount);

            $subtotal = 0;
            $items = [];

            foreach ($orderProducts as $product) {
                $quantity = rand(1, 3);
                $unitPrice = $product->price;
                $totalPrice = $quantity * $unitPrice;
                $subtotal += $totalPrice;

                $items[] = [
                    'purchasable_type' => $product->getMorphClass(),
                    'purchasable_id' => $product->getKey(),
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => $totalPrice,
                    'currency' => $product->currency,
                ];
            }

            $discountTotal = rand(0, 1) ? rand(500, 5000) : 0;
            $taxTotal = (int) round($subtotal * 0.06); // 6% SST
            $shippingTotal = rand(0, 1) ? rand(500, 2000) : 0;
            $grandTotal = $subtotal - $discountTotal + $taxTotal + $shippingTotal;

            [$firstName, $lastName] = array_pad(explode(' ', $user->name, 2), 2, '');

            $address = [
                'first_name' => $firstName ?: $user->name,
                'last_name' => $lastName ?: '-',
                'phone' => fake()->phoneNumber(),
                'email' => $user->email,
                'line1' => fake()->streetAddress(),
                'city' => fake()->city(),
                'state' => fake()->randomElement(['Selangor', 'Kuala Lumpur', 'Penang', 'Johor', 'Sabah']),
                'postcode' => fake()->postcode(),
                'country' => 'MY',
            ];

            $order = Order::create([
                'order_number' => Order::generateOrderNumber(),
                'status' => $status,
                'customer_type' => $user->getMorphClass(),
                'customer_id' => $user->getKey(),
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'shipping_total' => $shippingTotal,
                'grand_total' => $grandTotal,
                'currency' => 'MYR',
                'paid_at' => in_array($status, [Processing::class, Shipped::class, Delivered::class], true) ? fake()->dateTimeBetween('-29 days', 'now') : null,
                'shipped_at' => in_array($status, [Shipped::class, Delivered::class], true) ? fake()->dateTimeBetween('-20 days', 'now') : null,
                'delivered_at' => $status === Delivered::class ? fake()->dateTimeBetween('-10 days', 'now') : null,
                'canceled_at' => $status === Canceled::class ? fake()->dateTimeBetween('-5 days', 'now') : null,
            ]);

            OrderAddress::create([
                ...$address,
                'order_id' => $order->id,
                'type' => 'billing',
            ]);

            OrderAddress::create([
                ...$address,
                'order_id' => $order->id,
                'type' => 'shipping',
            ]);

            foreach ($items as $itemData) {
                OrderItem::create([
                    ...$itemData,
                    'order_id' => $order->id,
                ]);
            }
        }
    }
}
