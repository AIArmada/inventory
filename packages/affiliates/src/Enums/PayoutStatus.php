<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Enums;

/**
 * Status enum for affiliate payouts.
 */
enum PayoutStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
