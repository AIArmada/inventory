<?php

declare(strict_types=1);

namespace AIArmada\Inventory\States;

final class Lost extends SerialStatus
{
    public static string $name = 'lost';

    public function label(): string
    {
        return 'Lost';
    }

    public function color(): string
    {
        return 'danger';
    }
}
