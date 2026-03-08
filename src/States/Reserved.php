<?php

declare(strict_types=1);

namespace AIArmada\Inventory\States;

final class Reserved extends SerialStatus
{
    public static string $name = 'reserved';

    public function label(): string
    {
        return 'Reserved';
    }

    public function color(): string
    {
        return 'warning';
    }

    public function isInStock(): bool
    {
        return true;
    }
}
