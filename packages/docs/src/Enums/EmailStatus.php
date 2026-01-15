<?php

declare(strict_types=1);

namespace AIArmada\Docs\Enums;

/**
 * Status values for document emails.
 */
enum EmailStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Delivered = 'delivered';
    case Bounced = 'bounced';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Sent => 'Sent',
            self::Failed => 'Failed',
            self::Delivered => 'Delivered',
            self::Bounced => 'Bounced',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued => 'gray',
            self::Sent => 'info',
            self::Failed => 'danger',
            self::Delivered => 'success',
            self::Bounced => 'warning',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Delivered, self::Bounced, self::Failed], true);
    }
}
