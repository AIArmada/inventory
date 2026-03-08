<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Events;

use AIArmada\Inventory\Models\InventoryBatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class BatchCreated
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
     * Get the quantity received.
     */
    public function getQuantityReceived(): int
    {
        return $this->batch->quantity_received;
    }
}
