<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Models;

use AIArmada\Cart\Database\Factories\ConditionFactory as BaseConditionFactory;
use AIArmada\Cart\Models\Condition as BaseCondition;
use AIArmada\FilamentCart\Database\Factories\ConditionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Filament-specific Condition model.
 *
 * Extends the base Cart package Condition model with Filament-specific functionality.
 */
final class Condition extends BaseCondition
{
    /** @use HasFactory<ConditionFactory> */
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): BaseConditionFactory
    {
        return ConditionFactory::new();
    }
}
