<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Actions;

use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\InventoryTransferred;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Transfer inventory between locations.
 */
final class TransferInventory
{
    use AsAction;

    public function __construct(
        private readonly CheckLowInventory $checkLowInventory,
    ) {}

    /**
     * Transfer inventory between locations.
     *
     * @return array{from: InventoryMovement, to: InventoryMovement}
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
    ): array {
        return DB::transaction(function () use ($model, $fromLocationId, $toLocationId, $quantity, $note, $userId): array {
            // Lock source location
            $fromLevel = InventoryLevel::where('inventoryable_type', $model->getMorphClass())
                ->where('inventoryable_id', $model->getKey())
                ->where('location_id', $fromLocationId)
                ->lockForUpdate()
                ->first();

            $available = $fromLevel?->quantity_available ?? 0;

            if (! $fromLevel || $available < $quantity) {
                throw new InsufficientInventoryException(
                    "Insufficient inventory at source location {$fromLocationId}. Available: {$available}, requested: {$quantity}",
                    $model->getKey(),
                    $quantity,
                    $available
                );
            }

            // Get or create destination level
            $toLevel = $this->getOrCreateLevel($model, $toLocationId);

            // Update source
            $fromPrevious = $fromLevel->quantity_on_hand;
            $fromLevel->quantity_on_hand -= $quantity;
            $fromLevel->save();

            // Update destination
            $toPrevious = $toLevel->quantity_on_hand;
            $toLevel->quantity_on_hand += $quantity;
            $toLevel->save();

            // Create movements
            $fromMovement = InventoryMovement::create([
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
                'from_location_id' => $fromLocationId,
                'to_location_id' => $toLocationId,
                'type' => MovementType::Transfer->value,
                'quantity' => -$quantity,
                'note' => $note,
                'user_id' => $userId,
                'occurred_at' => now(),
            ]);

            $toMovement = InventoryMovement::create([
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
                'from_location_id' => $fromLocationId,
                'to_location_id' => $toLocationId,
                'type' => MovementType::Transfer->value,
                'quantity' => $quantity,
                'note' => $note,
                'user_id' => $userId,
                'occurred_at' => now(),
            ]);

            Event::dispatch(new InventoryTransferred($model, $fromLevel, $toLevel, $fromMovement));

            $this->checkLowInventory->handle($model, $fromLevel);
            $this->checkLowInventory->handle($model, $toLevel);

            return ['from' => $fromMovement, 'to' => $toMovement];
        });
    }

    private function getOrCreateLevel(Model $model, string $locationId): InventoryLevel
    {
        return InventoryLevel::firstOrCreate(
            [
                'inventoryable_type' => $model->getMorphClass(),
                'inventoryable_id' => $model->getKey(),
                'location_id' => $locationId,
            ],
            [
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
                'quantity_available' => 0,
                'reorder_point' => config('inventory.default_reorder_point', 10),
                'reorder_quantity' => config('inventory.default_reorder_quantity', 50),
            ]
        );
    }
}
