<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Events;

use AIArmada\Inventory\Enums\AlertStatus;
use AIArmada\Inventory\Models\InventoryLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class StockRestored
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Model $inventoryable,
        public InventoryLevel $level,
        public AlertStatus $previousStatus
    ) {}

    /**
     * Get the current available quantity.
     */
    public function getAvailable(): int
    {
        return $this->level->available;
    }

    /**
     * Get the reorder point threshold.
     */
    public function getReorderPoint(): int
    {
        return $this->level->reorder_point ?? config('inventory.default_reorder_point', 10);
    }

    /**
     * Check if stock is now above reorder point.
     */
    public function isAboveReorderPoint(): bool
    {
        return $this->getAvailable() > $this->getReorderPoint();
    }
}
