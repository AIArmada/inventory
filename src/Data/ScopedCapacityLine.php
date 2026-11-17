<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Data;

use InvalidArgumentException;

final readonly class ScopedCapacityLine
{
    public function __construct(
        public string $resourceKey,
        public string $scopeKey,
        public int $quantity,
    ) {
        if (mb_trim($resourceKey) === '' || $quantity < 1) {
            throw new InvalidArgumentException('Capacity reservation requires a resource key and positive quantity.');
        }
    }

    public function key(): string
    {
        return json_encode([$this->resourceKey, $this->scopeKey], JSON_THROW_ON_ERROR);
    }
}
