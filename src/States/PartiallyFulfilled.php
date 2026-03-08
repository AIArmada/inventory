<?php

declare(strict_types=1);

namespace AIArmada\Inventory\States;

final class PartiallyFulfilled extends BackorderStatus
{
    public static string $name = 'partially_fulfilled';

    public function label(): string
    {
        return 'Partially Fulfilled';
    }

    public function color(): string
    {
        return 'info';
    }

    public function isOpen(): bool
    {
        return true;
    }

    public function canFulfill(): bool
    {
        return true;
    }

    public function canCancel(): bool
    {
        return true;
    }
}
