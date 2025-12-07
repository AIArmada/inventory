<?php

declare(strict_types=1);

namespace AIArmada\Cart\Queries;

use DateTimeInterface;

/**
 * Query to search carts with various filters.
 */
final readonly class SearchCartsQuery
{
    public function __construct(
        public ?string $identifier = null,
        public ?string $instance = null,
        public ?DateTimeInterface $createdAfter = null,
        public ?DateTimeInterface $createdBefore = null,
        public ?int $minItems = null,
        public int $limit = 50,
        public int $offset = 0
    ) {}
}
