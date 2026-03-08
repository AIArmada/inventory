<?php

declare(strict_types=1);

namespace AIArmada\Inventory\States;

final class Returned extends SerialStatus
{
    public static string $name = 'returned';

    public function label(): string
    {
        return 'Returned';
    }

    public function color(): string
    {
        return 'warning';
    }
}
