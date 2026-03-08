<?php

declare(strict_types=1);

namespace AIArmada\Inventory\States;

final class Fulfilled extends BackorderStatus
{
    public static string $name = 'fulfilled';

    public function label(): string
    {
        return 'Fulfilled';
    }

    public function color(): string
    {
        return 'success';
    }

    public function isClosed(): bool
    {
        return true;
    }
}
