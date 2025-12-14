<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;

class InventoryMovementTest extends InventoryTestCase
{
    protected InventoryItem $item;

    protected InventoryLocation $fromLocation;

    protected InventoryLocation $toLocation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->fromLocation = InventoryLocation::factory()->create([
            'name' => 'From Location',
            'code' => 'FROM',
        ]);
        $this->toLocation = InventoryLocation::factory()->create([
            'name' => 'To Location',
            'code' => 'TO',
        ]);
    }

    public function test_can_create_movement(): void
    {
        $movement = InventoryMovement::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'to_location_id' => $this->toLocation->id,
            'type' => MovementType::Receipt->value,
            'quantity' => 10,
        ]);

        expect($movement)->toBeInstanceOf(InventoryMovement::class);
        expect($movement->quantity)->toBe(10);
    }

    public function test_get_movement_type_returns_enum(): void
    {
        $movement = InventoryMovement::factory()->create([
            'type' => MovementType::Receipt->value,
        ]);

        expect($movement->getMovementType())->toBe(MovementType::Receipt);
    }

    public function test_is_receipt_returns_true_for_receipt_type(): void
    {
        $movement = InventoryMovement::factory()->create([
            'type' => MovementType::Receipt->value,
        ]);

        expect($movement->isReceipt())->toBeTrue();
        expect($movement->isShipment())->toBeFalse();
    }

    public function test_is_shipment_returns_true_for_shipment_type(): void
    {
        $movement = InventoryMovement::factory()->create([
            'type' => MovementType::Shipment->value,
        ]);

        expect($movement->isShipment())->toBeTrue();
        expect($movement->isReceipt())->toBeFalse();
    }

    public function test_is_transfer_returns_true_for_transfer_type(): void
    {
        $movement = InventoryMovement::factory()->create([
            'type' => MovementType::Transfer->value,
        ]);

        expect($movement->isTransfer())->toBeTrue();
    }

    public function test_is_adjustment_returns_true_for_adjustment_type(): void
    {
        $movement = InventoryMovement::factory()->create([
            'type' => MovementType::Adjustment->value,
        ]);

        expect($movement->isAdjustment())->toBeTrue();
    }

    public function test_from_location_relationship(): void
    {
        $movement = InventoryMovement::factory()->create([
            'from_location_id' => $this->fromLocation->id,
            'to_location_id' => null,
            'type' => MovementType::Shipment->value,
        ]);

        expect($movement->fromLocation)->not->toBeNull();
        expect($movement->fromLocation->id)->toBe($this->fromLocation->id);
    }

    public function test_to_location_relationship(): void
    {
        $movement = InventoryMovement::factory()->create([
            'from_location_id' => null,
            'to_location_id' => $this->toLocation->id,
            'type' => MovementType::Receipt->value,
        ]);

        expect($movement->toLocation)->not->toBeNull();
        expect($movement->toLocation->id)->toBe($this->toLocation->id);
    }

    public function test_inventoryable_relationship(): void
    {
        $movement = InventoryMovement::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
        ]);

        expect($movement->inventoryable)->not->toBeNull();
        expect($movement->inventoryable->id)->toBe($this->item->id);
    }

    public function test_scope_of_type_filters_by_movement_type(): void
    {
        InventoryMovement::factory()->create(['type' => MovementType::Receipt->value]);
        InventoryMovement::factory()->create(['type' => MovementType::Receipt->value]);
        InventoryMovement::factory()->create(['type' => MovementType::Shipment->value]);

        $receipts = InventoryMovement::ofType(MovementType::Receipt)->get();

        expect($receipts)->toHaveCount(2);
    }

    public function test_scope_for_reference_filters_by_reference(): void
    {
        InventoryMovement::factory()->create(['reference' => 'ORDER-123']);
        InventoryMovement::factory()->create(['reference' => 'ORDER-123']);
        InventoryMovement::factory()->create(['reference' => 'ORDER-456']);

        $movements = InventoryMovement::forReference('ORDER-123')->get();

        expect($movements)->toHaveCount(2);
    }

    public function test_scope_at_location_filters_by_location(): void
    {
        InventoryMovement::factory()->create(['from_location_id' => $this->fromLocation->id]);
        InventoryMovement::factory()->create(['to_location_id' => $this->fromLocation->id]);
        InventoryMovement::factory()->create(['to_location_id' => $this->toLocation->id]);

        $movements = InventoryMovement::atLocation($this->fromLocation->id)->get();

        expect($movements)->toHaveCount(2);
    }

    public function test_occurred_at_is_cast_to_datetime(): void
    {
        $movement = InventoryMovement::factory()->create([
            'occurred_at' => '2025-01-01 12:00:00',
        ]);

        expect($movement->occurred_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
    }

    public function test_user_relationship(): void
    {
        $movement = new InventoryMovement();
        $relation = $movement->user();

        expect($relation)->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    }
}
