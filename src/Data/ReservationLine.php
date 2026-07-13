<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Data;

use Spatie\LaravelData\Data;

final class ReservationLine extends Data
{
    public function __construct(
        public string $productId,
        public ?string $variantId = null,
        public int $quantity = 1,
    ) {}
}
