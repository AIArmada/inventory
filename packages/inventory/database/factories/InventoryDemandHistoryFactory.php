<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Database\Factories;

use AIArmada\Inventory\Enums\DemandPeriodType;
use AIArmada\Inventory\Models\InventoryDemandHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryDemandHistory>
 */
final class InventoryDemandHistoryFactory extends Factory
{
    protected $model = InventoryDemandHistory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventoryable_type' => 'product',
            'inventoryable_id' => $this->faker->uuid(),
            'location_id' => null,
            'period_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'period_type' => DemandPeriodType::Daily,
            'quantity_demanded' => $this->faker->numberBetween(10, 100),
            'quantity_fulfilled' => $this->faker->numberBetween(5, 90),
            'quantity_lost' => $this->faker->numberBetween(0, 10),
            'order_count' => $this->faker->numberBetween(1, 20),
        ];
    }

    public function daily(): static
    {
        return $this->state(fn (array $attributes): array => [
            'period_type' => DemandPeriodType::Daily,
        ]);
    }

    public function weekly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'period_type' => DemandPeriodType::Weekly,
        ]);
    }

    public function monthly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'period_type' => DemandPeriodType::Monthly,
        ]);
    }
}
