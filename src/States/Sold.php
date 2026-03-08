<?php

declare(strict_types=1);

namespace AIArmada\Inventory\States;

final class Sold extends SerialStatus
{
    public static string $name = 'sold';

    public function label(): string
    {
        return 'Sold';
    }

    public function color(): string
    {
        return 'info';
    }
}
