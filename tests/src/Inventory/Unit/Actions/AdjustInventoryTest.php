<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Actions\AdjustInventory;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\InventoryAdjusted;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use Illuminate\Support\Facades\Event;

class AdjustInventoryTest extends InventoryTestCase
{
    protected AdjustInventory $action;

    protected InventoryItem $item;

    protected InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(AdjustInventory::class);
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create([
            'name' => 'Test Location',
            'code' => 'TEST',
        ]);
    }

    public function test_adjusts_inventory_and_creates_movement(): void
    {
        Event::fake();

        // Create initial level
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);

        $movement = $this->action->handle($this->item, $this->location->id, 15, 'correction', 'Test note', 'user-1');

        expect($movement->getMovementType())->toBe(MovementType::Adjustment);
        expect($movement->quantity)->toBe(5); // 15 - 10 = 5
        expect($movement->reason)->toBe('correction');
        expect($movement->note)->toBe('Test note');
        expect($movement->user_id)->toBe('user-1');

        Event::assertDispatched(InventoryAdjusted::class);
    }

    public function test_creates_inventory_level_if_not_exists(): void
    {
        $movement = $this->action->handle($this->item, $this->location->id, 20);

        $level = $this->item->inventoryLevels()->where('location_id', $this->location->id)->first();
        expect($level)->not->toBeNull();
        expect($level->quantity_on_hand)->toBe(20);
    }

    public function test_handles_negative_adjustment(): void
    {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 20,
            'quantity_reserved' => 0,
        ]);

        $movement = $this->action->handle($this->item, $this->location->id, 5);

        expect($movement->quantity)->toBe(-15); // 5 - 20 = -15

        $level = $this->item->inventoryLevels()->where('location_id', $this->location->id)->first();
        expect($level->quantity_on_hand)->toBe(5);
    }

    public function test_dispatches_inventory_adjusted_event_with_correct_data(): void
    {
        Event::fake();

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);

        $this->action->handle($this->item, $this->location->id, 25);

        Event::assertDispatched(InventoryAdjusted::class, function (InventoryAdjusted $event): bool {
            return $event->oldQuantity === 10
                && $event->newQuantity === 25
                && $event->inventoryable->is($this->item);
        });
    }
}
