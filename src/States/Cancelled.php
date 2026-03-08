<?php

declare(strict_types=1);

namespace AIArmada\Inventory\States;

final class Cancelled extends BackorderStatus
{
    public static string $name = 'cancelled';

    public function label(): string
    {
        return 'Cancelled';
    }

    public function color(): string
    {
        return 'danger';
    }

    public function isClosed(): bool
    {
        return true;
    }
}
