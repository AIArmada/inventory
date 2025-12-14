<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Actions\ReceiveInventory;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\InventoryReceived;
use AIArmada\Inventory\Models\InventoryLocation;
use Illuminate\Support\Facades\Event;

class ReceiveInventoryTest extends InventoryTestCase
{
    protected ReceiveInventory $action;

    protected InventoryItem $item;

    protected InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(ReceiveInventory::class);
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create([
            'name' => 'Test Location',
            'code' => 'TEST',
        ]);
    }

    public function test_receives_inventory_and_creates_movement(): void
    {
        Event::fake();

        $movement = $this->action->handle($this->item, $this->location->id, 10, 'restock', 'Test note', 'user-1');

        expect($movement->getMovementType())->toBe(MovementType::Receipt);
        expect($movement->quantity)->toBe(10);
        expect($movement->reason)->toBe('restock');
        expect($movement->note)->toBe('Test note');
        expect($movement->user_id)->toBe('user-1');

        Event::assertDispatched(InventoryReceived::class);
    }

    public function test_creates_inventory_level_if_not_exists(): void
    {
        $this->action->handle($this->item, $this->location->id, 5);

        $level = $this->item->inventoryLevels()->where('location_id', $this->location->id)->first();
        expect($level)->not->toBeNull();
        expect($level->quantity_on_hand)->toBe(5);
    }

    public function test_updates_existing_inventory_level(): void
    {
        // First receive
        $this->action->handle($this->item, $this->location->id, 5);

        // Second receive
        $this->action->handle($this->item, $this->location->id, 3);

        $level = $this->item->inventoryLevels()->where('location_id', $this->location->id)->first();
        expect($level->quantity_on_hand)->toBe(8);
    }
}