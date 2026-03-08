<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Database\Factories;

use AIArmada\Inventory\Enums\SerialCondition;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventorySerial;
use AIArmada\Inventory\States\Available;
use AIArmada\Inventory\States\Reserved;
use AIArmada\Inventory\States\SerialStatus;
use AIArmada\Inventory\States\Sold;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventorySerial>
 */
class InventorySerialFactory extends Factory
{
    protected $model = InventorySerial::class;

    public function definition(): array
    {
        return [
            'inventoryable_type' => 'App\\Models\\Product',
            'inventoryable_id' => $this->faker->uuid(),
            'serial_number' => mb_strtoupper($this->faker->unique()->bothify('SN-????-####-????')),
            'sku' => $this->faker->optional(0.8)->bothify('SKU-####'),
            'location_id' => InventoryLocation::factory(),
            'batch_id' => null,
            'status' => SerialStatus::normalize(Available::class),
            'condition' => SerialCondition::New->value,
            'unit_cost_minor' => $this->faker->numberBetween(1000, 100000),
            'currency' => 'USD',
            'warranty_expires_at' => $this->faker->optional(0.7)->dateTimeBetween('now', '+2 years'),
            'manufactured_at' => $this->faker->optional(0.8)->dateTimeBetween('-1 year', 'now'),
            'received_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
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
     * Make serial reserved.
     */
    public function reserved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SerialStatus::normalize(Reserved::class),
        ]);
    }

    /**
     * Make serial sold.
     */
    public function sold(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SerialStatus::normalize(Sold::class),
            'sold_at' => now(),
            'order_id' => $this->faker->uuid(),
            'customer_id' => $this->faker->uuid(),
        ]);
    }

    /**
     * Make serial refurbished.
     */
    public function refurbished(): static
    {
        return $this->state(fn (array $attributes): array => [
            'condition' => SerialCondition::Refurbished->value,
        ]);
    }

    /**
     * Make serial with expiring warranty.
     */
    public function warrantyExpiringSoon(int $days = 30): static
    {
        return $this->state(fn (array $attributes): array => [
            'warranty_expires_at' => now()->addDays($days),
        ]);
    }

    /**
     * Make serial with expired warranty.
     */
    public function warrantyExpired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'warranty_expires_at' => now()->subDays($this->faker->numberBetween(1, 365)),
        ]);
    }

    /**
     * Make serial damaged.
     */
    public function damaged(): static
    {
        return $this->state(fn (array $attributes): array => [
            'condition' => SerialCondition::Damaged->value,
        ]);
    }
}
