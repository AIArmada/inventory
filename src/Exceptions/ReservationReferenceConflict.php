<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Exceptions;

final class ReservationReferenceConflict extends InventoryException
{
    public function __construct(
        string $reference,
        string $message,
    ) {
        parent::__construct("Reservation reference '{$reference}' conflict: {$message}");
    }
}
