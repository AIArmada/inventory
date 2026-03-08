<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Actions;

use AIArmada\Inventory\Events\LowInventoryDetected;
use AIArmada\Inventory\Models\InventoryLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Check and dispatch low inventory events.
 */
final class CheckLowInventory
{
    use AsAction;

    /**
     * Check if inventory is below reorder point and dispatch event.
     */
    public function handle(Model $model, InventoryLevel $level): bool
    {
        $reorderPoint = $level->reorder_point ?? config('inventory.default_reorder_point', 10);
        $available = $level->quantity_available ?? ($level->quantity_on_hand - ($level->quantity_reserved ?? 0));

        if ($available <= $reorderPoint) {
            Event::dispatch(new LowInventoryDetected($model, $level));

            return true;
        }

        return false;
    }
}
