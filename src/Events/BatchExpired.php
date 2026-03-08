<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Events;

use AIArmada\Inventory\Models\InventoryBatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class BatchExpired
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public InventoryBatch $batch,
        public Model $inventoryable
    ) {}

    /**
     * Get the batch number.
     */
    public function getBatchNumber(): string
    {
        return $this->batch->batch_number;
    }

    /**
     * Get the remaining quantity.
     */
    public function getRemainingQuantity(): int
    {
        return $this->batch->quantity_on_hand;
    }
}
