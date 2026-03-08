<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Database\Factories;

use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryAllocation>
 */
class InventoryAllocationFactory extends Factory
{
    protected $model = InventoryAllocation::class;

    public function definition(): array
    {
        return [
            'inventoryable_type' => 'App\\Models\\Product',
            'inventoryable_id' => $this->faker->uuid(),
            'location_id' => InventoryLocation::factory(),
            'level_id' => InventoryLevel::factory(),
            'cart_id' => $this->faker->uuid(),
            'quantity' => $this->faker->numberBetween(1, 10),
            'expires_at' => now()->addMinutes(config('inventory.allocation_ttl_minutes', 30)),
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
     * Set specific level.
     */
    public function forLevel(InventoryLevel | string $level): static
    {
        return $this->state(fn (array $attributes): array => [
            'level_id' => $level instanceof InventoryLevel ? $level->id : $level,
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
     * Set specific cart.
     */
    public function forCart(string $cartId): static
    {
        return $this->state(fn (array $attributes): array => [
            'cart_id' => $cartId,
        ]);
    }

    /**
     * Mark as expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subMinutes(5),
        ]);
    }

    /**
     * Mark as expiring soon.
     */
    public function expiringSoon(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->addMinutes(2),
        ]);
    }

    /**
     * Set custom expiry.
     */
    public function expiresAt(DateTimeInterface $dateTime): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => $dateTime,
        ]);
    }
}
