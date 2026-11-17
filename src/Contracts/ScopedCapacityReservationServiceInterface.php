<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Contracts;

use AIArmada\Inventory\Data\ScopedCapacityLine;
use AIArmada\Inventory\Data\ScopedCapacityOutcome;

interface ScopedCapacityReservationServiceInterface
{
    /** @param list<ScopedCapacityLine> $lines */
    public function reserve(string $reference, array $lines, int $ttlSeconds): ScopedCapacityOutcome;

    public function commit(string $reference, string $orderId): ScopedCapacityOutcome;

    public function release(string $reference): ScopedCapacityOutcome;

    public function extend(string $reference, int $ttlSeconds): ScopedCapacityOutcome;

    public function find(string $reference): ScopedCapacityOutcome;

    public function available(string $resourceKey, string $scopeKey): ?int;

    public function setCapacity(string $resourceKey, string $scopeKey, int $capacity): void;
}
