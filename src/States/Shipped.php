<?php

declare(strict_types=1);

namespace AIArmada\Inventory\States;

final class Shipped extends SerialStatus
{
    public static string $name = 'shipped';

    public function label(): string
    {
        return 'Shipped';
    }

    public function color(): string
    {
        return 'primary';
    }
}
