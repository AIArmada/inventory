<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Database\Factories;

use AIArmada\Cart\Database\Factories\ConditionFactory as BaseConditionFactory;
use AIArmada\Cart\Models\Condition;

/**
 * Filament-specific Condition factory.
 *
 * Extends the base Cart package factory for Filament-specific model.
 */
final class ConditionFactory extends BaseConditionFactory
{
    protected $model = Condition::class;
}
