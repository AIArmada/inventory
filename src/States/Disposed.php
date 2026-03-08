<?php

declare(strict_types=1);

namespace AIArmada\Inventory\States;

final class Disposed extends SerialStatus
{
    public static string $name = 'disposed';

    public function label(): string
    {
        return 'Disposed';
    }

    public function color(): string
    {
        return 'danger';
    }
}
