<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Actions;

use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Services\InventoryService;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Receive inventory at a location.
 */
final class ReceiveInventory
{
    use AsAction;

    public function __construct(
        private readonly InventoryService $inventoryService,
    ) {}

    /**
     * Receive inventory at a location.
     */
    public function handle(
        Model $model,
        string $locationId,
        int $quantity,
        ?string $reason = null,
        ?string $note = null,
        ?string $userId = null
    ): InventoryMovement {
        return $this->inventoryService->receive(
            model: $model,
            locationId: $locationId,
            quantity: $quantity,
            reason: $reason,
            note: $note,
            userId: $userId
        );
    }
}
