<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Database\Factories;

use AIArmada\Inventory\Enums\ReorderSuggestionStatus;
use AIArmada\Inventory\Enums\ReorderUrgency;
use AIArmada\Inventory\Models\InventoryReorderSuggestion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<InventoryReorderSuggestion>
 */
final class InventoryReorderSuggestionFactory extends Factory
{
    protected $model = InventoryReorderSuggestion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventoryable_type' => Model::class,
            'inventoryable_id' => $this->faker->uuid(),
            'location_id' => null,
            'supplier_leadtime_id' => null,
            'status' => ReorderSuggestionStatus::Pending,
            'current_stock' => $this->faker->numberBetween(0, 50),
            'reorder_point' => $this->faker->numberBetween(20, 100),
            'suggested_quantity' => $this->faker->numberBetween(50, 200),
            'economic_order_quantity' => $this->faker->numberBetween(25, 100),
            'average_daily_demand' => $this->faker->numberBetween(5, 20),
            'lead_time_days' => $this->faker->numberBetween(3, 14),
            'expected_stockout_date' => $this->faker->optional()->dateTimeBetween('now', '+30 days'),
            'urgency' => $this->faker->randomElement(ReorderUrgency::cases()),
            'trigger_reason' => 'Stock below reorder point',
            'calculation_details' => [
                'safety_stock' => $this->faker->numberBetween(10, 30),
                'max_stock' => $this->faker->numberBetween(100, 200),
            ],
        ];
    }

    /**
     * Set the inventoryable model for this suggestion.
     */
    public function forModel(Model $model): static
    {
        return $this->state(fn (array $attributes): array => [
            'inventoryable_type' => $model::class,
            'inventoryable_id' => $model->getKey(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReorderSuggestionStatus::Pending,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReorderSuggestionStatus::Approved,
            'approved_by' => $this->faker->email(),
            'approved_at' => now(),
        ]);
    }

    public function ordered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReorderSuggestionStatus::Ordered,
            'purchase_order_id' => $this->faker->uuid(),
            'ordered_at' => now(),
        ]);
    }

    public function critical(): static
    {
        return $this->state(fn (array $attributes): array => [
            'urgency' => ReorderUrgency::Critical,
            'expected_stockout_date' => now()->addDays(2),
        ]);
    }

    public function normal(): static
    {
        return $this->state(fn (array $attributes): array => [
            'urgency' => ReorderUrgency::Normal,
        ]);
    }
}
