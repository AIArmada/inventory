<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Database\Factories;

use AIArmada\Inventory\Enums\BackorderPriority;
use AIArmada\Inventory\Enums\BackorderStatus;
use AIArmada\Inventory\Models\InventoryBackorder;
use AIArmada\Inventory\Models\InventoryLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryBackorder>
 */
class InventoryBackorderFactory extends Factory
{
    protected $model = InventoryBackorder::class;

    public function definition(): array
    {
        $requested = $this->faker->numberBetween(1, 100);

        return [
            'inventoryable_type' => 'App\\Models\\Product',
            'inventoryable_id' => $this->faker->uuid(),
            'location_id' => InventoryLocation::factory(),
            'order_id' => $this->faker->optional(0.8)->uuid(),
            'customer_id' => $this->faker->optional(0.7)->uuid(),
            'quantity_requested' => $requested,
            'quantity_fulfilled' => 0,
            'quantity_cancelled' => 0,
            'status' => BackorderStatus::Pending,
            'priority' => $this->faker->randomElement(BackorderPriority::cases()),
            'requested_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'promised_at' => $this->faker->optional(0.6)->dateTimeBetween('now', '+30 days'),
            'fulfilled_at' => null,
            'cancelled_at' => null,
            'notes' => $this->faker->optional(0.3)->sentence(),
            'metadata' => null,
        ];
    }

    /**
     * Set specific location.
     */
    public function forLocation(InventoryLocation | string $location): static
    {
        return $this->state(fn (array $attributes): array => [
            'location_id' => $location instanceof InventoryLocation ? $location->id : $location,
        ]);
    }

    /**
     * Make backorder pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BackorderStatus::Pending,
            'quantity_fulfilled' => 0,
        ]);
    }

    /**
     * Make backorder partially fulfilled.
     */
    public function partiallyFulfilled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BackorderStatus::PartiallyFulfilled,
            'quantity_fulfilled' => (int) ($attributes['quantity_requested'] / 2),
        ]);
    }

    /**
     * Make backorder fulfilled.
     */
    public function fulfilled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BackorderStatus::Fulfilled,
            'quantity_fulfilled' => $attributes['quantity_requested'],
            'fulfilled_at' => now(),
        ]);
    }

    /**
     * Make backorder cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BackorderStatus::Cancelled,
            'quantity_cancelled' => $attributes['quantity_requested'],
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Make backorder overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BackorderStatus::Pending,
            'promised_at' => $this->faker->dateTimeBetween('-14 days', '-1 day'),
        ]);
    }

    /**
     * Set priority.
     */
    public function withPriority(BackorderPriority $priority): static
    {
        return $this->state(fn (array $attributes): array => [
            'priority' => $priority,
        ]);
    }

    /**
     * Make backorder urgent.
     */
    public function urgent(): static
    {
        return $this->withPriority(BackorderPriority::Urgent);
    }
}
