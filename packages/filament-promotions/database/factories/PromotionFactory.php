<?php

declare(strict_types=1);

namespace AIArmada\FilamentPromotions\Database\Factories;

use AIArmada\FilamentPromotions\Enums\PromotionType;
use AIArmada\FilamentPromotions\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true) . ' Promotion',
            'description' => $this->faker->optional()->sentence(),
            'code' => $this->faker->optional(0.5)->passthrough($this->faker->unique()->regexify('[A-Z]{4}[0-9]{2}')),
            'type' => $this->faker->randomElement(PromotionType::cases()),
            'discount_value' => $this->faker->numberBetween(5, 50),
            'min_purchase_amount' => $this->faker->optional(0.3)->numberBetween(1000, 10000),
            'min_quantity' => $this->faker->optional(0.3)->numberBetween(1, 10),
            'usage_limit' => $this->faker->optional(0.5)->numberBetween(10, 1000),
            'per_customer_limit' => $this->faker->optional(0.3)->numberBetween(1, 5),
            'usage_count' => 0,
            'priority' => $this->faker->numberBetween(0, 100),
            'is_active' => $this->faker->boolean(80),
            'is_stackable' => $this->faker->boolean(20),
            'starts_at' => $this->faker->optional(0.5)->dateTimeBetween('-1 month', '+1 month'),
            'ends_at' => $this->faker->optional(0.3)->dateTimeBetween('+1 month', '+6 months'),
            'conditions' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    public function percentage(int $value = 20): static
    {
        return $this->state(fn (): array => [
            'type' => PromotionType::Percentage,
            'discount_value' => $value,
        ]);
    }

    public function fixed(int $value = 1000): static
    {
        return $this->state(fn (): array => [
            'type' => PromotionType::Fixed,
            'discount_value' => $value,
        ]);
    }

    public function withCode(?string $code = null): static
    {
        return $this->state(fn (): array => [
            'code' => $code ?? mb_strtoupper($this->faker->unique()->lexify('????')) . $this->faker->numerify('##'),
        ]);
    }

    public function automatic(): static
    {
        return $this->state(fn (): array => [
            'code' => null,
        ]);
    }

    public function stackable(): static
    {
        return $this->state(fn (): array => [
            'is_stackable' => true,
        ]);
    }
}
