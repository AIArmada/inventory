<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Events;

use AIArmada\Inventory\Models\InventoryBatch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class BatchRecalled
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  Collection<int, InventoryBatch>  $batches
     */
    public function __construct(
        public Collection $batches,
        public string $reason,
        public Model $inventoryable
    ) {}

    /**
     * Get the total quantity affected.
     */
    public function getTotalQuantityAffected(): int
    {
        return $this->batches->sum('quantity_on_hand');
    }

    /**
     * Get batch numbers.
     *
     * @return array<string>
     */
    public function getBatchNumbers(): array
    {
        return $this->batches->pluck('batch_number')->toArray();
    }
}
