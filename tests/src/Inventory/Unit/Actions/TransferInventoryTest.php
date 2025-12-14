<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Actions\TransferInventory;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\InventoryTransferred;
use AIArmada\Inventory\Exceptions\InsufficientInventoryException;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use Illuminate\Support\Facades\Event;

class TransferInventoryTest extends InventoryTestCase
{
    protected TransferInventory $action;

    protected InventoryItem $item;

    protected InventoryLocation $fromLocation;

    protected InventoryLocation $toLocation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(TransferInventory::class);
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

    public function test_transfers_inventory_between_locations(): void
    {
        Event::fake();

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->fromLocation->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);

        $movements = $this->action->handle(
            $this->item,
            $this->fromLocation->id,
            $this->toLocation->id,
            20,
            'Transfer note',
            'user-1'
        );

        expect($movements)->toHaveKeys(['from', 'to']);
        expect($movements['from']->getMovementType())->toBe(MovementType::Transfer);
        expect($movements['from']->quantity)->toBe(-20);
        expect($movements['to']->getMovementType())->toBe(MovementType::Transfer);
        expect($movements['to']->quantity)->toBe(20);

        Event::assertDispatched(InventoryTransferred::class);
    }

    public function test_updates_source_location_quantity(): void
    {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->fromLocation->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
        ]);

        $this->action->handle($this->item, $this->fromLocation->id, $this->toLocation->id, 30);

        $fromLevel = InventoryLevel::where('location_id', $this->fromLocation->id)->first();
        expect($fromLevel->quantity_on_hand)->toBe(70);
    }

    public function test_updates_destination_location_quantity(): void
    {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->fromLocation->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
        ]);

        $this->action->handle($this->item, $this->fromLocation->id, $this->toLocation->id, 30);

        $toLevel = InventoryLevel::where('location_id', $this->toLocation->id)->first();
        expect($toLevel)->not->toBeNull();
        expect($toLevel->quantity_on_hand)->toBe(30);
    }

    public function test_creates_destination_level_if_not_exists(): void
    {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->fromLocation->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 0,
        ]);

        $this->action->handle($this->item, $this->fromLocation->id, $this->toLocation->id, 10);

        $toLevel = InventoryLevel::where('location_id', $this->toLocation->id)->first();
        expect($toLevel)->not->toBeNull();
    }

    public function test_throws_exception_when_insufficient_inventory(): void
    {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->fromLocation->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
        ]);

        $this->expectException(InsufficientInventoryException::class);

        $this->action->handle($this->item, $this->fromLocation->id, $this->toLocation->id, 50);
    }

    public function test_throws_exception_when_no_source_level(): void
    {
        $this->expectException(InsufficientInventoryException::class);

        $this->action->handle($this->item, $this->fromLocation->id, $this->toLocation->id, 10);
    }

    public function test_considers_reserved_quantity_at_source(): void
    {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->fromLocation->id,
            'quantity_on_hand' => 50,
            'quantity_reserved' => 40,
        ]);

        // Available is 50 - 40 = 10, so transferring 20 should fail
        $this->expectException(InsufficientInventoryException::class);

        $this->action->handle($this->item, $this->fromLocation->id, $this->toLocation->id, 20);
    }

    public function test_dispatches_inventory_transferred_event(): void
    {
        Event::fake();

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->fromLocation->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
        ]);

        $this->action->handle($this->item, $this->fromLocation->id, $this->toLocation->id, 25);

        Event::assertDispatched(InventoryTransferred::class, function (InventoryTransferred $event): bool {
            return $event->inventoryable->is($this->item);
        });
    }
}
