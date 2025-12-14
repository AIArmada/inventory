<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Enums;

enum SerialCondition: string
{
    case New = 'new';
    case LikeNew = 'like_new';
    case Refurbished = 'refurbished';
    case Used = 'used';
    case Damaged = 'damaged';
    case ForParts = 'for_parts';

    /**
     * Get all options for select fields.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $condition) {
            $options[$condition->value] = $condition->label();
        }

        return $options;
    }

    /**
     * Get a human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::LikeNew => 'Like New',
            self::Refurbished => 'Refurbished',
            self::Used => 'Used',
            self::Damaged => 'Damaged',
            self::ForParts => 'For Parts Only',
        };
    }

    /**
     * Get the badge color for UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::New => 'success',
            self::LikeNew => 'success',
            self::Refurbished => 'info',
            self::Used => 'warning',
            self::Damaged => 'danger',
            self::ForParts => 'gray',
        };
    }

    /**
     * Check if condition is sellable.
     */
    public function isSellable(): bool
    {
        return in_array($this, [self::New, self::LikeNew, self::Refurbished, self::Used], true);
    }

    /**
     * Get quality score (1-10).
     */
    public function qualityScore(): int
    {
        return match ($this) {
            self::New => 10,
            self::LikeNew => 9,
            self::Refurbished => 7,
            self::Used => 5,
            self::Damaged => 2,
            self::ForParts => 1,
        };
    }
}
