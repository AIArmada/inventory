<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Enums;

enum ReorderSuggestionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Ordered = 'ordered';
    case Received = 'received';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Review',
            self::Approved => 'Approved',
            self::Ordered => 'Ordered',
            self::Received => 'Received',
            self::Rejected => 'Rejected',
            self::Expired => 'Expired',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'info',
            self::Ordered => 'primary',
            self::Received => 'success',
            self::Rejected => 'danger',
            self::Expired => 'gray',
        };
    }

    public function isActionable(): bool
    {
        return in_array($this, [self::Pending, self::Approved], true);
    }

    public function isComplete(): bool
    {
        return in_array($this, [self::Received, self::Rejected, self::Expired], true);
    }
}
