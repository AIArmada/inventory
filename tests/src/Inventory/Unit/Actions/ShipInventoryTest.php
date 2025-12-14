<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Actions\ShipInventory;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\InventoryShipped;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use Illuminate\Support\Facades\Event;

class ShipInventoryTest extends InventoryTestCase
{
    protected ShipInventory $action;

    protected InventoryItem $item;

    protected InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(ShipInventory::class);
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create([
            'name' => 'Test Location',
            'code' => 'TEST',
        ]);
    }

    public function test_ships_inventory_and_creates_movement(): void
    {
        Event::fake();

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 20,
            'quantity_reserved' => 0,
        ]);

        $movement = $this->action->handle(
            $this->item,
            $this->location->id,
            5,
            'sale',
            'ORDER-123',
            'Shipped to customer',
            'user-1'
        );

        expect($movement->getMovementType())->toBe(MovementType::Shipment);
        expect($movement->quantity)->toBe(-5);
        expect($movement->reason)->toBe('sale');
        expect($movement->reference)->toBe('ORDER-123');
        expect($movement->note)->toBe('Shipped to customer');
        expect($movement->user_id)->toBe('user-1');

        Event::assertDispatched(InventoryShipped::class);
    }

    public function test_decrements_quantity_on_hand(): void
    {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 30,
            'quantity_reserved' => 0,
        ]);

        $this->action->handle($this->item, $this->location->id, 10);

        $level = $this->item->inventoryLevels()->where('location_id', $this->location->id)->first();
        expect($level->quantity_on_hand)->toBe(20);
    }

    public function test_throws_exception_when_insufficient_inventory(): void
    {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
        ]);

        $this->expectException(InsufficientInventoryException::class);

        $this->action->handle($this->item, $this->location->id, 10);
    }

    public function test_throws_exception_when_no_inventory_level(): void
    {
        $this->expectException(InsufficientInventoryException::class);

        $this->action->handle($this->item, $this->location->id, 5);
    }

    public function test_considers_reserved_quantity(): void
    {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 20,
            'quantity_reserved' => 15,
        ]);

        // Available is 20 - 15 = 5, so shipping 10 should fail
        $this->expectException(InsufficientInventoryException::class);

        $this->action->handle($this->item, $this->location->id, 10);
    }

    public function test_dispatches_inventory_shipped_event(): void
    {
        Event::fake();

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);

        $this->action->handle($this->item, $this->location->id, 10);

        Event::assertDispatched(InventoryShipped::class, function (InventoryShipped $event): bool {
            return $event->inventoryable->is($this->item);
        });
    }
}
