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
 * Transfer inventory between locations.
 */
final class TransferInventory
{
    use AsAction;

    public function __construct(
        private readonly InventoryService $inventoryService,
    ) {}

    /**
     * Transfer inventory between locations.
     *
     * @throws InsufficientInventoryException
     */
    public function handle(
        Model $model,
        string $fromLocationId,
        string $toLocationId,
        int $quantity,
        ?string $note = null,
        ?string $userId = null
    ): InventoryMovement {
        try {
            return $this->inventoryService->transfer(
                model: $model,
                fromLocationId: $fromLocationId,
                toLocationId: $toLocationId,
                quantity: $quantity,
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
