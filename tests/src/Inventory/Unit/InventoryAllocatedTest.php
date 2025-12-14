<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Events\InventoryAllocated;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLocation;
use Illuminate\Database\Eloquent\Collection;

class InventoryAllocatedTest extends InventoryTestCase
{
    protected InventoryItem $item;

    protected InventoryLocation $location1;

    protected InventoryLocation $location2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location1 = InventoryLocation::factory()->create([
            'name' => 'Location 1',
            'code' => 'LOC1',
        ]);
        $this->location2 = InventoryLocation::factory()->create([
            'name' => 'Location 2',
            'code' => 'LOC2',
        ]);
    }

    public function test_event_stores_properties_correctly(): void
    {
        $allocations = new Collection([
            InventoryAllocation::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location1->id,
                'quantity' => 5,
                'cart_id' => 'cart-123',
            ]),
        ]);

        $event = new InventoryAllocated(
            $this->item,
            $allocations,
            'cart-123'
        );

        expect($event->inventoryable)->toBe($this->item);
        expect($event->allocations)->toBe($allocations);
        expect($event->cartId)->toBe('cart-123');
    }

    public function test_get_event_type_returns_correct_value(): void
    {
        $allocations = new Collection();
        $event = new InventoryAllocated($this->item, $allocations, 'cart-123');

        expect($event->getEventType())->toBe('inventory.allocated');
    }

    public function test_get_total_quantity_sums_allocations(): void
    {
        $allocations = new Collection([
            InventoryAllocation::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location1->id,
                'quantity' => 5,
                'cart_id' => 'cart-123',
            ]),
            InventoryAllocation::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location2->id,
                'quantity' => 3,
                'cart_id' => 'cart-123',
            ]),
        ]);

        $event = new InventoryAllocated($this->item, $allocations, 'cart-123');

        expect($event->getTotalQuantity())->toBe(8);
    }

    public function test_get_location_count_returns_unique_locations(): void
    {
        $allocations = new Collection([
            InventoryAllocation::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location1->id,
                'quantity' => 5,
                'cart_id' => 'cart-123',
            ]),
            InventoryAllocation::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location1->id,
                'quantity' => 3,
                'cart_id' => 'cart-123',
            ]),
            InventoryAllocation::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location2->id,
                'quantity' => 2,
                'cart_id' => 'cart-123',
            ]),
        ]);

        $event = new InventoryAllocated($this->item, $allocations, 'cart-123');

        expect($event->getLocationCount())->toBe(2);
    }
}