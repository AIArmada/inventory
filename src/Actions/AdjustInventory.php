<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Actions;

use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Services\InventoryService;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Adjust inventory to a specific quantity at a location.
 */
final class AdjustInventory
{
    use AsAction;

    public function __construct(
        private readonly InventoryService $inventoryService,
    ) {}

    /**
     * Adjust inventory to a specific quantity at a location.
     */
    public function handle(
        Model $model,
        string $locationId,
        int $newQuantity,
        ?string $reason = null,
        ?string $note = null,
        ?string $userId = null
    ): InventoryMovement {
        return $this->inventoryService->adjust(
            model: $model,
            locationId: $locationId,
            newQuantity: $newQuantity,
            reason: $reason,
            note: $note,
            userId: $userId
        );
    }
}
