<?php

declare(strict_types=1);

namespace AIArmada\Cart\Queries;

use DateTimeInterface;

/**
 * Query to get abandoned carts for recovery.
 */
final readonly class GetAbandonedCartsQuery
{
    public function __construct(
        public DateTimeInterface $olderThan,
        public ?int $minValueCents = null,
        public int $limit = 100
    ) {}
}
