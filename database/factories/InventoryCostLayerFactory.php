<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Database\Factories;

use AIArmada\Inventory\Enums\CostingMethod;
use AIArmada\Inventory\Models\InventoryCostLayer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryCostLayer>
 */
class InventoryCostLayerFactory extends Factory
{
    protected $model = InventoryCostLayer::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(10, 100);
        $unitCost = $this->faker->numberBetween(1000, 10000);

        return [
            'inventoryable_type' => 'App\\Models\\Product',
            'inventoryable_id' => $this->faker->uuid(),
            'location_id' => null,
            'batch_id' => null,
            'quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'unit_cost_minor' => $unitCost,
            'total_cost_minor' => $quantity * $unitCost,
            'currency' => 'MYR',
            'reference' => $this->faker->optional()->lexify('PO-????-????'),
            'costing_method' => CostingMethod::Fifo,
            'layer_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function fifo(): static
    {
        return $this->state(fn (array $attributes) => [
            'costing_method' => CostingMethod::Fifo,
        ]);
    }

    public function lifo(): static
    {
        return $this->state(fn (array $attributes) => [
            'costing_method' => CostingMethod::Lifo,
        ]);
    }

    public function weightedAverage(): static
    {
        return $this->state(fn (array $attributes) => [
            'costing_method' => CostingMethod::WeightedAverage,
        ]);
    }

    public function fullyConsumed(): static
    {
        return $this->state(fn (array $attributes) => [
            'remaining_quantity' => 0,
        ]);
    }

    public function partiallyConsumed(): static
    {
        return $this->state(function (array $attributes): array {
            $consumed = (int) ($attributes['quantity'] * $this->faker->randomFloat(2, 0.3, 0.7));

            return [
                'remaining_quantity' => $attributes['quantity'] - $consumed,
            ];
        });
    }

    public function atLocation(string $locationId): static
    {
        return $this->state(fn (array $attributes) => [
            'location_id' => $locationId,
        ]);
    }

    public function forBatch(string $batchId): static
    {
        return $this->state(fn (array $attributes) => [
            'batch_id' => $batchId,
        ]);
    }

    public function withReference(string $reference): static
    {
        return $this->state(fn (array $attributes) => [
            'reference' => $reference,
        ]);
    }

    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'layer_date' => $this->faker->dateTimeBetween('-90 days', '-60 days'),
        ]);
    }

    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'layer_date' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }
}
