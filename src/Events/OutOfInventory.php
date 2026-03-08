<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Events;

use AIArmada\Inventory\Models\InventoryLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class OutOfInventory
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Model $inventoryable,
        public InventoryLevel $level
    ) {}

    /**
     * Get the location where inventory ran out.
     */
    public function getLocationId(): string
    {
        return $this->level->location_id;
    }
}
