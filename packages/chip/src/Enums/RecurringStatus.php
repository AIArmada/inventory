<?php

declare(strict_types=1);

namespace AIArmada\Chip\Enums;

enum RecurringStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Cancelled => 'Cancelled',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Paused => 'warning',
            self::Cancelled => 'gray',
            self::Failed => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Active => 'heroicon-o-play',
            self::Paused => 'heroicon-o-pause',
            self::Cancelled => 'heroicon-o-x-circle',
            self::Failed => 'heroicon-o-exclamation-triangle',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Cancelled, self::Failed], true);
    }
}
