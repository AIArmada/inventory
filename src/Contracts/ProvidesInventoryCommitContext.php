<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Contracts;

interface ProvidesInventoryCommitContext
{
    public function inventoryCartId(): ?string;

    public function inventoryOrderReference(): ?string;
}
