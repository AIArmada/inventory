<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Data;

use Spatie\LaravelData\Data;

final class ReservationOutcome extends Data
{
    public function __construct(
        public string $reference,
        public string $state,
        public ?string $expiresAt = null,
        public ?string $orderId = null,
        /** @var array<string, array{requested: int, reserved: int}> */
        public array $lines = [],
    ) {}
}
