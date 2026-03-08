<?php

declare(strict_types=1);

namespace AIArmada\Inventory\States;

final class Pending extends BackorderStatus
{
    public static string $name = 'pending';

    public function label(): string
    {
        return 'Pending';
    }

    public function color(): string
    {
        return 'warning';
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
