<?php

declare(strict_types=1);

namespace AIArmada\Inventory\States;

final class Expired extends BackorderStatus
{
    public static string $name = 'expired';

    public function label(): string
    {
        return 'Expired';
    }

    public function color(): string
    {
        return 'gray';
    }

    public function isClosed(): bool
    {
        return true;
    }
}
