<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Database\Factories;

use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    public function definition(): array
    {
        return [
            'inventoryable_type' => 'App\\Models\\Product',
            'inventoryable_id' => $this->faker->uuid(),
            'from_location_id' => null,
            'to_location_id' => InventoryLocation::factory(),
            'quantity' => $this->faker->numberBetween(1, 100),
            'type' => MovementType::Receipt->value,
            'reason' => $this->faker->randomElement(['purchase', 'return', 'adjustment']),
            'reference' => mb_strtoupper($this->faker->lexify('REF-??????')),
            'user_id' => null,
            'note' => $this->faker->optional()->sentence(),
            'occurred_at' => now(),
        ];
    }

    /**
     * Create a receipt movement.
     */
    public function receipt(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => MovementType::Receipt->value,
            'from_location_id' => null,
            'to_location_id' => InventoryLocation::factory(),
        ]);
    }

    /**
     * Create a shipment movement.
     */
    public function shipment(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => MovementType::Shipment->value,
            'from_location_id' => InventoryLocation::factory(),
            'to_location_id' => null,
        ]);
    }

    /**
     * Create a transfer movement.
     */
    public function transfer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => MovementType::Transfer->value,
            'from_location_id' => InventoryLocation::factory(),
            'to_location_id' => InventoryLocation::factory(),
        ]);
    }

    /**
     * Create an adjustment movement.
     */
    public function adjustment(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => MovementType::Adjustment->value,
            'reason' => $this->faker->randomElement(['damage', 'count', 'correction']),
        ]);
    }

    /**
     * Set from location.
     */
    public function fromLocation(InventoryLocation | string $location): static
    {
        return $this->state(fn (array $attributes): array => [
            'from_location_id' => $location instanceof InventoryLocation ? $location->id : $location,
        ]);
    }

    /**
     * Set to location.
     */
    public function toLocation(InventoryLocation | string $location): static
    {
        return $this->state(fn (array $attributes): array => [
            'to_location_id' => $location instanceof InventoryLocation ? $location->id : $location,
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
     * Set user who performed the movement.
     */
    public function byUser(string $userId): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $userId,
        ]);
    }

    /**
     * Set occurred at a specific time.
     */
    public function occurredAt(DateTimeInterface $dateTime): static
    {
        return $this->state(fn (array $attributes): array => [
            'occurred_at' => $dateTime,
        ]);
    }
}
