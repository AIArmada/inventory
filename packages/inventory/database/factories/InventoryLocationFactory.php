<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Database\Factories;

use AIArmada\Inventory\Models\InventoryLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryLocation>
 */
class InventoryLocationFactory extends Factory
{
    protected $model = InventoryLocation::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company() . ' Warehouse',
            'code' => mb_strtoupper($this->faker->unique()->lexify('WH-???')),
            'line1' => $this->faker->streetAddress(),
            'line2' => $this->faker->optional()->secondaryAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'postcode' => $this->faker->postcode(),
            'country' => 'MY',
            'is_active' => true,
            'priority' => $this->faker->numberBetween(1, 100),
            'metadata' => null,
        ];
    }

    /**
     * Mark the location as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Set as default location.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'Default Location',
            'code' => InventoryLocation::DEFAULT_LOCATION_CODE,
            'priority' => 100,
        ]);
    }

    /**
     * Set high priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes): array => [
            'priority' => $this->faker->numberBetween(80, 100),
        ]);
    }

    /**
     * Set low priority.
     */
    public function lowPriority(): static
    {
        return $this->state(fn (array $attributes): array => [
            'priority' => $this->faker->numberBetween(1, 20),
        ]);
    }
}
