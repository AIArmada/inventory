<?php

declare(strict_types=1);

namespace AIArmada\Inventory\States;

final class Available extends SerialStatus
{
    public static string $name = 'available';

    public function label(): string
    {
        return 'Available';
    }

    public function color(): string
    {
        return 'success';
    }

    public function isAllocatable(): bool
    {
        return true;
    }

    public function isInStock(): bool
    {
        return true;
    }
}
