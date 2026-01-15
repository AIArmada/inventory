<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions;

use AIArmada\Cart\Conditions\Enums\ConditionApplication;
use AIArmada\Cart\Conditions\Enums\ConditionPhase;
use AIArmada\Cart\Conditions\Enums\ConditionScope;

final class Target
{
    private function __construct() {}

    public static function items(): ConditionTargetBuilder
    {
        return new ConditionTargetBuilder(
            ConditionScope::ITEMS,
            ConditionPhase::ITEM_DISCOUNT,
            ConditionApplication::PER_ITEM
        );
    }

    public static function cart(): ConditionTargetBuilder
    {
        return new ConditionTargetBuilder(
            ConditionScope::CART,
            ConditionPhase::CART_SUBTOTAL,
            ConditionApplication::AGGREGATE
        );
    }

    public static function custom(): ConditionTargetBuilder
    {
        return new ConditionTargetBuilder(
            ConditionScope::CUSTOM,
            ConditionPhase::CUSTOM,
            ConditionApplication::AGGREGATE
        );
    }
}
