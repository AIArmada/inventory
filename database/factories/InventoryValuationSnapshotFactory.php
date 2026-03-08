<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Database\Factories;

use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Models\InventoryValuationSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryValuationSnapshot>
 */
final class InventoryValuationSnapshotFactory extends Factory
{
    protected $model = InventoryValuationSnapshot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalQuantity = $this->faker->numberBetween(100, 10000);
        $averageCost = $this->faker->numberBetween(500, 5000);

        return [
            'location_id' => null,
            'costing_method' => $this->faker->randomElement(CostingMethod::cases()),
            'snapshot_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'total_quantity' => $totalQuantity,
            'total_value_minor' => $totalQuantity * $averageCost,
            'average_unit_cost_minor' => $averageCost,
            'currency' => 'MYR',
            'sku_count' => $this->faker->numberBetween(10, 500),
            'variance_from_previous_minor' => $this->faker->optional()->numberBetween(-100000, 100000),
            'breakdown' => [
                'product' => [
                    'units' => $totalQuantity,
                    'value' => $totalQuantity * $averageCost,
                ],
            ],
        ];
    }

    public function fifo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'costing_method' => CostingMethod::Fifo,
        ]);
    }

    public function weightedAverage(): static
    {
        return $this->state(fn (array $attributes): array => [
            'costing_method' => CostingMethod::WeightedAverage,
        ]);
    }

    public function standard(): static
    {
        return $this->state(fn (array $attributes): array => [
            'costing_method' => CostingMethod::Standard,
        ]);
    }

    public function global(): static
    {
        return $this->state(fn (array $attributes): array => [
            'location_id' => null,
        ]);
    }

    public function forLocation(string $locationId): static
    {
        return $this->state(fn (array $attributes): array => [
            'location_id' => $locationId,
        ]);
    }

    public function today(): static
    {
        return $this->state(fn (array $attributes): array => [
            'snapshot_date' => today(),
        ]);
    }
}
