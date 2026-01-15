<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions;

use AIArmada\Cart\Conditions\Enums\ConditionPhase;

/**
 * Convenience wrappers for the most common condition targets.
 *
 * These helpers return fully-built ConditionTarget instances so callers
 * can avoid repetitive builder chains for standard scopes/phases.
 */
final class TargetPresets
{
    public static function cartSubtotal(): ConditionTarget
    {
        return Target::cart()
            ->phase(ConditionPhase::CART_SUBTOTAL)
            ->applyAggregate()
            ->build();
    }

    public static function cartGrandTotal(): ConditionTarget
    {
        return Target::cart()
            ->phase(ConditionPhase::GRAND_TOTAL)
            ->applyAggregate()
            ->build();
    }

    public static function cartShipping(): ConditionTarget
    {
        return Target::cart()
            ->phase(ConditionPhase::SHIPPING)
            ->applyAggregate()
            ->build();
    }

    public static function cartTaxable(): ConditionTarget
    {
        return Target::cart()
            ->phase(ConditionPhase::TAXABLE)
            ->applyAggregate()
            ->build();
    }

    public static function cartTax(): ConditionTarget
    {
        return Target::cart()
            ->phase(ConditionPhase::TAX)
            ->applyAggregate()
            ->build();
    }

    public static function itemsPerItem(): ConditionTarget
    {
        return Target::items()
            ->phase(ConditionPhase::ITEM_DISCOUNT)
            ->applyPerItem()
            ->build();
    }

    public static function itemsPreItem(): ConditionTarget
    {
        return Target::items()
            ->phase(ConditionPhase::PRE_ITEM)
            ->applyAggregate()
            ->build();
    }

    public static function customAggregate(): ConditionTarget
    {
        return Target::custom()->build();
    }
}
