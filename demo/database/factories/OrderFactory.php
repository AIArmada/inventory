<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
final class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = $this->faker->numberBetween(5000, 500000);
        $discount = $this->faker->optional(0.3)->numberBetween(500, $subtotal * 0.2) ?? 0;
        $tax = (int) (($subtotal - $discount) * 0.06);
        $shipping = $this->faker->randomElement([0, 1000, 1500, 2000]);
        $grandTotal = $subtotal - $discount + $tax + $shipping;

        return [
            'user_id' => User::factory(),
            'order_number' => 'ORD-'.mb_strtoupper($this->faker->unique()->bothify('????####')),
            'status' => $this->faker->randomElement(['pending', 'processing', 'shipped', 'delivered', 'cancelled']),
            'payment_status' => $this->faker->randomElement(['pending', 'paid', 'failed', 'refunded']),
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'tax_total' => $tax,
            'shipping_total' => $shipping,
            'grand_total' => $grandTotal,
            'currency' => 'MYR',
            'voucher_code' => $this->faker->optional(0.2)->regexify('[A-Z]{4}[0-9]{2}'),
            'billing_address' => $this->generateAddress(),
            'shipping_address' => $this->generateAddress(),
            'metadata' => null,
            'notes' => $this->faker->optional(0.1)->sentence(),
            'placed_at' => $this->faker->dateTimeBetween('-3 months', 'now'),
            'paid_at' => null,
            'shipped_at' => null,
            'delivered_at' => null,
            'cancelled_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'payment_status' => 'paid',
            'paid_at' => now()->subDays(rand(1, 7)),
        ]);
    }

    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'shipped',
            'payment_status' => 'paid',
            'paid_at' => now()->subDays(rand(5, 14)),
            'shipped_at' => now()->subDays(rand(1, 4)),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'delivered',
            'payment_status' => 'paid',
            'paid_at' => now()->subDays(rand(10, 30)),
            'shipped_at' => now()->subDays(rand(5, 9)),
            'delivered_at' => now()->subDays(rand(1, 4)),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'payment_status' => 'refunded',
            'cancelled_at' => now()->subDays(rand(1, 14)),
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    private function generateAddress(): array
    {
        return [
            'name' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'line1' => $this->faker->streetAddress(),
            'line2' => $this->faker->optional()->secondaryAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->randomElement(['Selangor', 'Kuala Lumpur', 'Penang', 'Johor', 'Perak']),
            'postcode' => $this->faker->postcode(),
            'country' => 'MY',
        ];
    }
}
