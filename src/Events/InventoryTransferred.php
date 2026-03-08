<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Events;

use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class InventoryTransferred
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Model $inventoryable,
        public InventoryLevel $fromLevel,
        public InventoryLevel $toLevel,
        public InventoryMovement $movement
    ) {}
}
