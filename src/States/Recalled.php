<?php

declare(strict_types=1);

namespace AIArmada\Inventory\States;

final class Recalled extends SerialStatus
{
    public static string $name = 'recalled';

    public function label(): string
    {
        return 'Recalled';
    }

    public function color(): string
    {
        return 'danger';
    }
}
