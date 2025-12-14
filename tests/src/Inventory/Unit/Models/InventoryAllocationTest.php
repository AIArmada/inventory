<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use Carbon\Carbon;

class InventoryAllocationTest extends InventoryTestCase
{
    protected InventoryItem $item;

    protected InventoryLocation $location;

    protected InventoryLevel $level;

    protected function setUp(): void
    {
        parent::setUp();

        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create();
        $this->level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 100,
            'quantity_reserved' => 0,
        ]);
    }

    public function test_can_create_allocation(): void
    {
        $allocation = InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-123',
            'quantity' => 10,
            'expires_at' => now()->addHour(),
        ]);

        expect($allocation)->toBeInstanceOf(InventoryAllocation::class);
        expect($allocation->cart_id)->toBe('cart-123');
        expect($allocation->quantity)->toBe(10);
    }

    public function test_location_relationship(): void
    {
        $allocation = InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-1',
            'expires_at' => now()->addHour(),
        ]);

        expect($allocation->location)->not->toBeNull();
        expect($allocation->location->id)->toBe($this->location->id);
    }

    public function test_level_relationship(): void
    {
        $allocation = InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-1',
            'expires_at' => now()->addHour(),
        ]);

        expect($allocation->level)->not->toBeNull();
        expect($allocation->level->id)->toBe($this->level->id);
    }

    public function test_inventoryable_relationship(): void
    {
        $allocation = InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-1',
            'expires_at' => now()->addHour(),
        ]);

        expect($allocation->inventoryable)->not->toBeNull();
        expect($allocation->inventoryable->id)->toBe($this->item->id);
    }

    public function test_scope_for_cart(): void
    {
        InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-A',
            'expires_at' => now()->addHour(),
        ]);
        InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-B',
            'expires_at' => now()->addHour(),
        ]);

        $allocations = InventoryAllocation::forCart('cart-A')->get();

        expect($allocations)->toHaveCount(1);
        expect($allocations->first()->cart_id)->toBe('cart-A');
    }

    public function test_scope_expired(): void
    {
        InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-1',
            'expires_at' => Carbon::now()->subHour(),
        ]);
        InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-2',
            'expires_at' => Carbon::now()->addHour(),
        ]);

        $expired = InventoryAllocation::expired()->get();

        expect($expired)->toHaveCount(1);
        expect($expired->first()->cart_id)->toBe('cart-1');
    }

    public function test_scope_active(): void
    {
        InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-1',
            'expires_at' => Carbon::now()->addHour(),
        ]);
        InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-2',
            'expires_at' => Carbon::now()->subHour(),
        ]);

        $active = InventoryAllocation::active()->get();

        expect($active)->toHaveCount(1);
        expect($active->first()->cart_id)->toBe('cart-1');
    }

    public function test_scope_at_location(): void
    {
        $location2 = InventoryLocation::factory()->create();
        $level2 = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location2->id,
        ]);

        InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-1',
            'expires_at' => now()->addHour(),
        ]);
        InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location2->id,
            'level_id' => $level2->id,
            'cart_id' => 'cart-2',
            'expires_at' => now()->addHour(),
        ]);

        $atLocation = InventoryAllocation::atLocation($this->location->id)->get();

        expect($atLocation)->toHaveCount(1);
        expect($atLocation->first()->cart_id)->toBe('cart-1');
    }

    public function test_is_expired_returns_true_when_expired(): void
    {
        $allocation = InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-1',
            'expires_at' => Carbon::now()->subMinutes(5),
        ]);

        expect($allocation->isExpired())->toBeTrue();
    }

    public function test_is_expired_returns_false_when_not_expired(): void
    {
        $allocation = InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-1',
            'expires_at' => Carbon::now()->addHour(),
        ]);

        expect($allocation->isExpired())->toBeFalse();
    }

    public function test_is_active_returns_true_when_not_expired(): void
    {
        $allocation = InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-1',
            'expires_at' => Carbon::now()->addHour(),
        ]);

        expect($allocation->isActive())->toBeTrue();
    }

    public function test_is_active_returns_false_when_expired(): void
    {
        $allocation = InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-1',
            'expires_at' => Carbon::now()->subMinutes(5),
        ]);

        expect($allocation->isActive())->toBeFalse();
    }

    public function test_extend_updates_expires_at(): void
    {
        $allocation = InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-1',
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        $allocation->extend(120);

        $allocation->refresh();
        expect($allocation->expires_at->isAfter(now()->addMinutes(110)))->toBeTrue();
    }

    public function test_casts_are_correct(): void
    {
        $allocation = InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-1',
            'quantity' => 10,
            'expires_at' => Carbon::now()->addHour(),
        ]);

        expect($allocation->quantity)->toBeInt();
        expect($allocation->expires_at)->toBeInstanceOf(Carbon::class);
    }
}
