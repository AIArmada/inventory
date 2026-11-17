<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Data;

final readonly class ScopedCapacityOutcome
{
    /** @param array<string, int> $lines */
    public function __construct(
        public string $reference,
        public string $state,
        public array $lines,
        public ?string $expiresAt = null,
        public ?string $orderId = null,
    ) {}
}
