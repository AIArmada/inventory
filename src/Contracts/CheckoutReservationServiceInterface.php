<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Contracts;

use AIArmada\Inventory\Data\ReservationLine;
use AIArmada\Inventory\Data\ReservationOutcome;

interface CheckoutReservationServiceInterface
{
    /** @param list<ReservationLine> $lines */
    public function reserve(string $reference, array $lines, int $ttlSeconds): ReservationOutcome;

    public function release(string $reference): ReservationOutcome;

    public function commit(string $reference, string $orderId): ReservationOutcome;

    public function extend(string $reference, int $ttlSeconds): ReservationOutcome;

    public function find(string $reference): ReservationOutcome;
}
