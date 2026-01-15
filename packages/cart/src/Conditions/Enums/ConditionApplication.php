<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions\Enums;

use InvalidArgumentException;

enum ConditionApplication: string
{
    case AGGREGATE = 'aggregate';
    case PER_ITEM = 'per-item';
    case PER_UNIT = 'per-unit';
    case PER_GROUP = 'per-group';

    public static function fromString(string $application): self
    {
        $normalized = str_replace('_', '-', mb_strtolower(mb_trim($application)));

        foreach (self::cases() as $case) {
            if ($case->value === $normalized) {
                return $case;
            }
        }

        throw new InvalidArgumentException("Unknown condition application [{$application}].");
    }
}
