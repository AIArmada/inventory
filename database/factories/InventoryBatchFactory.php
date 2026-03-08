<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Database\Factories;

use AIArmada\Inventory\Enums\BatchStatus;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryBatch>
 */
class InventoryBatchFactory extends Factory
{
    protected $model = InventoryBatch::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(50, 500);
        $receivedAt = $this->faker->dateTimeBetween('-6 months', 'now');

        return [
            'inventoryable_type' => 'App\\Models\\Product',
            'inventoryable_id' => $this->faker->uuid(),
            'batch_number' => mb_strtoupper($this->faker->unique()->bothify('BATCH-####-??')),
            'lot_number' => $this->faker->optional(0.7)->bothify('LOT-####'),
            'supplier_batch_number' => $this->faker->optional(0.5)->bothify('SUP-####'),
            'location_id' => InventoryLocation::factory(),
            'quantity_received' => $quantity,
            'quantity_on_hand' => $quantity,
            'quantity_reserved' => 0,
            'manufactured_at' => $this->faker->optional(0.8)->dateTimeBetween('-1 year', $receivedAt),
            'received_at' => $receivedAt,
            'expires_at' => $this->faker->optional(0.6)->dateTimeBetween('now', '+2 years'),
            'status' => BatchStatus::Active->value,
            'unit_cost_minor' => $this->faker->numberBetween(100, 10000),
            'currency' => 'USD',
            'is_quarantined' => false,
            'is_recalled' => false,
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
     * Make batch expiring soon.
     */
    public function expiringSoon(int $days = 14): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->addDays($days),
        ]);
    }

    /**
     * Make batch expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDays($this->faker->numberBetween(1, 30)),
            'status' => BatchStatus::Expired->value,
        ]);
    }

    /**
     * Make batch quarantined.
     */
    public function quarantined(string $reason = 'Quality issue'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BatchStatus::Quarantined->value,
            'is_quarantined' => true,
            'quarantine_reason' => $reason,
        ]);
    }

    /**
     * Make batch recalled.
     */
    public function recalled(string $reason = 'Safety recall'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BatchStatus::Recalled->value,
            'is_recalled' => true,
            'recall_reason' => $reason,
            'recalled_at' => now(),
        ]);
    }

    /**
     * Make batch depleted.
     */
    public function depleted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
            'status' => BatchStatus::Depleted->value,
        ]);
    }

    /**
     * Make batch with reserved quantity.
     */
    public function withReserved(int $reserved): static
    {
        return $this->state(fn (array $attributes): array => [
            'quantity_reserved' => min($reserved, $attributes['quantity_on_hand']),
        ]);
    }

    /**
     * Make batch without expiry.
     */
    public function nonPerishable(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => null,
        ]);
    }
}
