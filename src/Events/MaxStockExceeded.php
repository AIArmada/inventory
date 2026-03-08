<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Events;

use AIArmada\Inventory\Models\InventoryLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MaxStockExceeded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Model $inventoryable,
        public InventoryLevel $level
    ) {}

    /**
     * Get the current on-hand quantity.
     */
    public function getOnHand(): int
    {
        return $this->level->quantity_on_hand;
    }

    /**
     * Get the maximum stock threshold.
     */
    public function getMaxStock(): int
    {
        return $this->level->max_stock ?? 0;
    }

    /**
     * Get the overage amount.
     */
    public function getOverage(): int
    {
        return max(0, $this->getOnHand() - $this->getMaxStock());
    }
}
