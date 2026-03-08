<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Events;

use AIArmada\Inventory\Enums\AlertStatus;
use AIArmada\Inventory\Models\InventoryLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SafetyStockBreached
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
     * Get the safety stock threshold.
     */
    public function getSafetyStock(): int
    {
        return $this->level->safety_stock ?? 0;
    }

    /**
     * Get the deficit below safety stock.
     */
    public function getDeficit(): int
    {
        return max(0, $this->getSafetyStock() - $this->getAvailable());
    }
}
