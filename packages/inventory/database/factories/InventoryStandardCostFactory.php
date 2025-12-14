<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Database\Factories;

use AIArmada\Inventory\Models\InventoryStandardCost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryStandardCost>
 */
class InventoryStandardCostFactory extends Factory
{
    protected $model = InventoryStandardCost::class;

    public function definition(): array
    {
        return [
            'inventoryable_type' => 'App\\Models\\Product',
            'inventoryable_id' => $this->faker->uuid(),
            'standard_cost_minor' => $this->faker->numberBetween(100, 10000),
            'currency' => 'MYR',
            'effective_from' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'effective_to' => null,
            'approved_by' => $this->faker->optional(0.7)->uuid(),
            'notes' => $this->faker->optional(0.3)->sentence(),
            'metadata' => null,
        ];
    }

    /**
     * Make cost currently active.
     */
    public function current(): static
    {
        return $this->state(fn (array $attributes): array => [
            'effective_from' => now()->subMonth(),
            'effective_to' => null,
        ]);
    }

    /**
     * Make cost expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'effective_from' => now()->subMonths(2),
            'effective_to' => now()->subMonth(),
        ]);
    }

    /**
     * Make cost scheduled for future.
     */
    public function future(): static
    {
        return $this->state(fn (array $attributes): array => [
            'effective_from' => now()->addMonth(),
            'effective_to' => null,
        ]);
    }

    /**
     * Set specific cost.
     */
    public function withCost(int $costMinor): static
    {
        return $this->state(fn (array $attributes): array => [
            'standard_cost_minor' => $costMinor,
        ]);
    }
}
