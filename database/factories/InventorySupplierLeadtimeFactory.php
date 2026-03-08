<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Database\Factories;

use AIArmada\Inventory\Models\InventorySupplierLeadtime;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<InventorySupplierLeadtime>
 */
final class InventorySupplierLeadtimeFactory extends Factory
{
    protected $model = InventorySupplierLeadtime::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventoryable_type' => Model::class,
            'inventoryable_id' => $this->faker->uuid(),
            'supplier_id' => $this->faker->uuid(),
            'supplier_name' => $this->faker->company(),
            'lead_time_days' => $this->faker->numberBetween(3, 14),
            'lead_time_variance_days' => $this->faker->numberBetween(0, 5),
            'unit_cost_minor' => $this->faker->numberBetween(1000, 10000),
            'currency' => 'MYR',
            'minimum_order_quantity' => $this->faker->numberBetween(1, 50),
            'order_multiple' => $this->faker->randomElement([1, 5, 10, 12, 24]),
            'is_primary' => false,
            'is_active' => true,
            'last_order_at' => null,
            'last_received_at' => null,
            'metadata' => null,
        ];
    }

    /**
     * Set the inventoryable model for this supplier leadtime.
     */
    public function forModel(Model $model): static
    {
        return $this->state(fn (array $attributes): array => [
            'inventoryable_type' => $model::class,
            'inventoryable_id' => $model->getKey(),
        ]);
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_primary' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function fastDelivery(): static
    {
        return $this->state(fn (array $attributes): array => [
            'lead_time_days' => $this->faker->numberBetween(1, 3),
        ]);
    }

    public function slowDelivery(): static
    {
        return $this->state(fn (array $attributes): array => [
            'lead_time_days' => $this->faker->numberBetween(14, 30),
        ]);
    }
}
