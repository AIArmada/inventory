<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Exceptions;

final class InvalidReservationTransition extends InventoryException
{
    public function __construct(
        string $reference,
        string $fromState,
        string $toState,
    ) {
        parent::__construct(
            "Invalid reservation transition for '{$reference}': cannot move from {$fromState} to {$toState}"
        );
    }
}
