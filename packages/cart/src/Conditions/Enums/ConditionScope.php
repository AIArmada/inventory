<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions\Enums;

use InvalidArgumentException;

enum ConditionScope: string
{
    case CART = 'cart';
    case ITEMS = 'items';
    case CUSTOM = 'custom';

    public static function fromString(string $scope): self
    {
        $normalized = mb_strtolower(mb_trim($scope));

        foreach (self::cases() as $case) {
            if ($case->value === $normalized) {
                return $case;
            }
        }

        throw new InvalidArgumentException("Unknown condition scope [{$scope}].");
    }
}
