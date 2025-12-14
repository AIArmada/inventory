<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Events\InventoryReleased;

class InventoryReleasedTest extends InventoryTestCase
{
    protected InventoryItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->item = InventoryItem::create(['name' => 'Test Item']);
    }

    public function test_event_stores_properties_correctly(): void
    {
        $event = new InventoryReleased($this->item, 5, 'cart-123');

        expect($event->inventoryable)->toBe($this->item);
        expect($event->quantity)->toBe(5);
        expect($event->cartId)->toBe('cart-123');
    }

    public function test_get_event_type_returns_correct_value(): void
    {
        $event = new InventoryReleased($this->item, 5, 'cart-123');

        expect($event->getEventType())->toBe('inventory.released');
    }
}
