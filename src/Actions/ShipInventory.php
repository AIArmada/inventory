<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Actions;

use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Exceptions\InsufficientStockException;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Services\InventoryService;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Ship inventory from a location.
 */
final class ShipInventory
{
    use AsAction;

    public function __construct(
        private readonly InventoryService $inventoryService,
    ) {}

    /**
     * Ship inventory from a location.
     *
     * @throws InsufficientInventoryException
     */
    public function handle(
        Model $model,
        string $locationId,
        int $quantity,
        ?string $reason = null,
        ?string $reference = null,
        ?string $note = null,
        ?string $userId = null
    ): InventoryMovement {
        try {
            return $this->inventoryService->ship(
                model: $model,
                locationId: $locationId,
                quantity: $quantity,
                reason: $reason,
                reference: $reference,
                note: $note,
                userId: $userId
            );
        } catch (InsufficientStockException $exception) {
            throw new InsufficientInventoryException(
                $exception->getMessage(),
                (string) $model->getKey(),
                $quantity,
                $exception->available
            );
        }
    }
}
