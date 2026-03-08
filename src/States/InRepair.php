<?php

declare(strict_types=1);

namespace AIArmada\Inventory\States;

final class InRepair extends SerialStatus
{
    public static string $name = 'in_repair';

    public function label(): string
    {
        return 'In Repair';
    }

    public function color(): string
    {
        return 'gray';
    }
}
