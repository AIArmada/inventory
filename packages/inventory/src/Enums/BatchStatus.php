<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Enums;

enum BatchStatus: string
{
    case Active = 'active';
    case Quarantined = 'quarantined';
    case Expired = 'expired';
    case Depleted = 'depleted';
    case Recalled = 'recalled';
    case OnHold = 'on_hold';

    /**
     * Get statuses that allow inventory movement.
     *
     * @return array<self>
     */
    public static function movableStatuses(): array
    {
        return [self::Active, self::OnHold];
    }

    /**
     * Get all options for select fields.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }

    /**
     * Get a human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Quarantined => 'Quarantined',
            self::Expired => 'Expired',
            self::Depleted => 'Depleted',
            self::Recalled => 'Recalled',
            self::OnHold => 'On Hold',
        };
    }

    /**
     * Get the badge color for UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Quarantined => 'warning',
            self::Expired => 'danger',
            self::Depleted => 'gray',
            self::Recalled => 'danger',
            self::OnHold => 'warning',
        };
    }

    /**
     * Check if batch can be allocated.
     */
    public function isAllocatable(): bool
    {
        return $this === self::Active;
    }

    /**
     * Check if batch requires attention.
     */
    public function requiresAttention(): bool
    {
        return in_array($this, [self::Quarantined, self::Expired, self::Recalled], true);
    }
}
