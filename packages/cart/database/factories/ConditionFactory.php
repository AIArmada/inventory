<?php

declare(strict_types=1);

namespace AIArmada\Cart\Database\Factories;

use AIArmada\Cart\Conditions\ConditionTarget;
use AIArmada\Cart\Models\Condition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Condition>
 */
class ConditionFactory extends Factory
{
    protected $model = Condition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $target = $this->randomFrom([
            'cart@cart_subtotal/aggregate',
            'cart@grand_total/aggregate',
            'items@item_discount/per-item',
        ]);

        return [
            'name' => 'condition_'.Str::lower(Str::random(8)),
            'display_name' => 'Condition '.Str::upper(Str::random(4)),
            'description' => 'Auto generated condition '.Str::lower(Str::random(12)),
            'type' => $this->randomFrom(['discount', 'tax', 'fee', 'shipping', 'surcharge']),
            'target' => $target,
            'target_definition' => ConditionTarget::from($target)->toArray(),
            'value' => $this->generateValue(),
            'order' => random_int(0, 10),
            'attributes' => [],
            'is_active' => random_int(0, 100) < 80,
            'is_global' => false,
        ];
    }

    public function discount(): static
    {
        return $this->state(function () {
            $target = $this->randomFrom([
                'cart@cart_subtotal/aggregate',
                'items@item_discount/per-item',
            ]);

            return [
                'type' => 'discount',
                'value' => '-'.random_int(5, 50).'%',
                'target' => $target,
                'target_definition' => ConditionTarget::from($target)->toArray(),
            ];
        });
    }

    public function tax(): static
    {
        return $this->state(function () {
            $target = 'cart@cart_subtotal/aggregate';

            return [
                'type' => 'tax',
                'value' => random_int(5, 15).'%',
                'target' => $target,
                'target_definition' => ConditionTarget::from($target)->toArray(),
            ];
        });
    }

    public function fee(): static
    {
        return $this->state(function () {
            $target = 'cart@cart_subtotal/aggregate';

            return [
                'type' => 'fee',
                'value' => '+'.random_int(200, 5000),
                'target' => $target,
                'target_definition' => ConditionTarget::from($target)->toArray(),
            ];
        });
    }

    public function shipping(): static
    {
        return $this->state(function () {
            $target = 'cart@cart_subtotal/aggregate';

            return [
                'type' => 'shipping',
                'value' => '+'.random_int(500, 8000),
                'target' => $target,
                'target_definition' => ConditionTarget::from($target)->toArray(),
                'attributes' => [
                    'method' => $this->randomFrom(['standard', 'express', 'overnight']),
                    'carrier' => $this->randomFrom(['UPS', 'FedEx', 'DHL', 'USPS']),
                ],
            ];
        });
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function forItems(): static
    {
        return $this->state(fn () => [
            'target' => 'items@item_discount/per-item',
            'target_definition' => ConditionTarget::from('items@item_discount/per-item')->toArray(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function withAttributes(array $attributes): static
    {
        return $this->state(fn () => ['attributes' => $attributes]);
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    public function withRules(array $rules): static
    {
        return $this->state(function () use ($rules) {
            $isDynamic = ! empty($rules);

            return [
                'rules' => Condition::normalizeRulesDefinition($rules, $isDynamic),
                'is_dynamic' => $isDynamic,
            ];
        });
    }

    private function generateValue(): string
    {
        return match ($this->randomFrom(['percentage', 'fixed_positive', 'fixed_negative'])) {
            'percentage' => random_int(1, 50).'%',
            'fixed_positive' => '+'.random_int(100, 10000),
            'fixed_negative' => '-'.random_int(100, 10000),
            default => '+'.random_int(100, 10000), // fallback
        };
    }

    /**
     * @param  array<mixed>  $options
     */
    private function randomFrom(array $options): mixed
    {
        return $options[array_rand($options)];
    }
}
