<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Database\Factories;

use AIArmada\Inventory\Enums\AllocationStrategy;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryLevel>
 */
class InventoryLevelFactory extends Factory
{
    protected $model = InventoryLevel::class;

    public function definition(): array
    {
        return [
            'inventoryable_type' => 'App\\Models\\Product',
            'inventoryable_id' => $this->faker->uuid(),
            'location_id' => InventoryLocation::factory(),
            'quantity_on_hand' => $this->faker->numberBetween(10, 500),
            'quantity_reserved' => 0,
            'reorder_point' => $this->faker->numberBetween(5, 20),
            'allocation_strategy' => null,
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
     * Set specific inventoryable.
     *
     * @param  class-string  $type
     */
    public function forInventoryable(string $type, string $id): static
    {
        return $this->state(fn (array $attributes): array => [
            'inventoryable_type' => $type,
            'inventoryable_id' => $id,
        ]);
    }

    /**
     * Set as low stock.
     */
    public function lowStock(): static
    {
        return $this->state(fn (array $attributes): array => [
            'quantity_on_hand' => $this->faker->numberBetween(1, 5),
            'quantity_reserved' => 0,
            'reorder_point' => 10,
        ]);
    }

    /**
     * Set as out of stock.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes): array => [
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
        ]);
    }

    /**
     * Set with reserved quantity.
     */
    public function withReserved(int $reserved): static
    {
        return $this->state(fn (array $attributes): array => [
            'quantity_reserved' => $reserved,
        ]);
    }

    /**
     * Set allocation strategy.
     */
    public function withStrategy(AllocationStrategy $strategy): static
    {
        return $this->state(fn (array $attributes): array => [
            'allocation_strategy' => $strategy->value,
        ]);
    }
}
