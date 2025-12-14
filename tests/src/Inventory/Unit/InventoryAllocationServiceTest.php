<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Enums\AllocationStrategy;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Events\InventoryAllocated;
use AIArmada\Inventory\Events\InventoryReleased;
use AIArmada\Inventory\Events\LowInventoryDetected;
use AIArmada\Inventory\Events\OutOfInventory;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\InventoryAllocationService;
use AIArmada\Inventory\Services\InventoryService;
use Illuminate\Support\Facades\Event;

class InventoryAllocationServiceTest extends InventoryTestCase
{
    protected InventoryService $inventoryService;

    protected InventoryAllocationService $allocationService;

    protected InventoryItem $item;

    protected InventoryLocation $locationA;

    protected InventoryLocation $locationB;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inventory.allow_split_allocation', true);
        config()->set('inventory.allocation_strategy', 'priority');

        $this->inventoryService = app(InventoryService::class);
        $this->allocationService = app(InventoryAllocationService::class);

        $this->item = InventoryItem::create(['name' => 'Allocatable Item']);

        $this->locationA = InventoryLocation::factory()->create([
            'name' => 'A',
            'code' => 'LOC-A',
            'priority' => 90,
        ]);

        $this->locationB = InventoryLocation::factory()->create([
            'name' => 'B',
            'code' => 'LOC-B',
            'priority' => 50,
        ]);

        $this->inventoryService->receive($this->item, $this->locationA->id, 10);
        $this->inventoryService->receive($this->item, $this->locationB->id, 6);
    }

    public function test_allocates_across_locations_using_split_allocation_and_updates_reserved_quantities(): void
    {
        Event::fake();

        $allocations = $this->allocationService->allocate($this->item, 12, 'cart-1', 45);

        expect($allocations)->toHaveCount(2);
        expect($allocations->sum('quantity'))->toBe(12);

        $levelA = $this->inventoryService->getLevel($this->item, $this->locationA->id)?->fresh();
        $levelB = $this->inventoryService->getLevel($this->item, $this->locationB->id)?->fresh();

        expect($levelA?->quantity_reserved)->toBe(10);
        expect($levelB?->quantity_reserved)->toBe(2);

        Event::assertDispatched(InventoryAllocated::class);
    }

    public function test_releases_allocations_and_restores_reserved_quantities(): void
    {
        Event::fake();

        $this->allocationService->allocate($this->item, 5, 'cart-2');

        $released = $this->allocationService->release($this->item, 'cart-2');

        $levelA = $this->inventoryService->getLevel($this->item, $this->locationA->id)?->fresh();
        $levelB = $this->inventoryService->getLevel($this->item, $this->locationB->id)?->fresh();

        expect($released)->toBe(5);
        expect($levelA?->quantity_reserved)->toBe(0);
        expect($levelB?->quantity_reserved)->toBe(0);

        Event::assertDispatched(InventoryReleased::class);
    }

    public function test_commits_allocations_into_shipments_and_clears_reservations(): void
    {
        $allocations = $this->allocationService->allocate($this->item, 8, 'cart-3');

        $movements = $this->allocationService->commit('cart-3', 'ORDER-123');

        $levelA = $this->inventoryService->getLevel($this->item, $this->locationA->id)?->fresh();
        $levelB = $this->inventoryService->getLevel($this->item, $this->locationB->id)?->fresh();

        expect($movements)->toHaveCount($allocations->count());
        expect($movements[0]->type)->toBe(MovementType::Shipment->value);
        expect($levelA?->quantity_reserved)->toBe(0);
        expect($levelB?->quantity_reserved)->toBe(0);
        expect($levelA?->quantity_on_hand + $levelB?->quantity_on_hand)->toBe(8); // 16 received - 8 committed
        expect(InventoryAllocation::query()->forCart('cart-3')->count())->toBe(0);
    }

    public function test_cleans_up_expired_allocations_and_frees_reserved_stock(): void
    {
        $level = $this->inventoryService->getLevel($this->item, $this->locationA->id);

        $allocation = InventoryAllocation::create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->locationA->id,
            'level_id' => $level?->id,
            'cart_id' => 'expired-cart',
            'quantity' => 3,
            'expires_at' => now()->subMinute(),
        ]);

        $level?->incrementReserved($allocation->quantity);

        $removed = $this->allocationService->cleanupExpired();

        $level?->refresh();

        expect($removed)->toBe(1);
        expect($level?->quantity_reserved)->toBe(0);
        expect(InventoryAllocation::query()->forCart('expired-cart')->count())->toBe(0);
    }

    public function test_throws_exception_for_zero_or_negative_quantity(): void
    {
        expect(fn () => $this->allocationService->allocate($this->item, 0, 'cart-1'))
            ->toThrow(InvalidArgumentException::class, 'Quantity must be positive');

        expect(fn () => $this->allocationService->allocate($this->item, -5, 'cart-1'))
            ->toThrow(InvalidArgumentException::class, 'Quantity must be positive');
    }

    public function test_throws_exception_when_insufficient_inventory(): void
    {
        expect(fn () => $this->allocationService->allocate($this->item, 100, 'cart-1'))
            ->toThrow(InvalidArgumentException::class, 'Insufficient inventory');
    }

    public function test_releases_existing_allocations_before_new_allocation(): void
    {
        $this->allocationService->allocate($this->item, 5, 'cart-realloc');

        $levelA = $this->inventoryService->getLevel($this->item, $this->locationA->id)?->fresh();
        expect($levelA?->quantity_reserved)->toBe(5);

        // Allocate again with same cart - should release first
        $this->allocationService->allocate($this->item, 3, 'cart-realloc');

        $levelA = $this->inventoryService->getLevel($this->item, $this->locationA->id)?->fresh();
        expect($levelA?->quantity_reserved)->toBe(3);
    }

    public function test_release_all_for_cart_releases_all_allocations(): void
    {
        // Create a second item and allocations
        $item2 = InventoryItem::create(['name' => 'Second Item']);
        $this->inventoryService->receive($item2, $this->locationA->id, 10);

        $this->allocationService->allocate($this->item, 5, 'cart-release-all');
        $this->allocationService->allocate($item2, 3, 'cart-release-all');

        expect(InventoryAllocation::query()->forCart('cart-release-all')->count())->toBe(2);

        $released = $this->allocationService->releaseAllForCart('cart-release-all');

        expect($released)->toBe(8);
        expect(InventoryAllocation::query()->forCart('cart-release-all')->count())->toBe(0);
    }

    public function test_extend_allocations_updates_expiry(): void
    {
        $this->allocationService->allocate($this->item, 5, 'cart-extend', 10);

        $originalExpiry = InventoryAllocation::query()
            ->forCart('cart-extend')
            ->first()
            ?->expires_at;

        $updated = $this->allocationService->extendAllocations('cart-extend', 60);

        expect($updated)->toBe(1);

        $newExpiry = InventoryAllocation::query()
            ->forCart('cart-extend')
            ->first()
            ?->expires_at;

        expect($newExpiry->gt($originalExpiry))->toBeTrue();
    }

    public function test_get_allocations_for_cart_returns_active_allocations(): void
    {
        $this->allocationService->allocate($this->item, 5, 'cart-get');

        $allocations = $this->allocationService->getAllocationsForCart('cart-get');

        expect($allocations)->toHaveCount(1);
    }

    public function test_get_allocations_for_model_and_cart(): void
    {
        $this->allocationService->allocate($this->item, 5, 'cart-model');

        $allocations = $this->allocationService->getAllocations($this->item, 'cart-model');

        expect($allocations)->toHaveCount(1);
    }

    public function test_has_available_inventory(): void
    {
        expect($this->allocationService->hasAvailableInventory($this->item, 10))->toBeTrue();
        expect($this->allocationService->hasAvailableInventory($this->item, 16))->toBeTrue();
        expect($this->allocationService->hasAvailableInventory($this->item, 17))->toBeFalse();
    }

    public function test_get_total_available(): void
    {
        $available = $this->allocationService->getTotalAvailable($this->item);

        expect($available)->toBe(16); // 10 + 6 from setup
    }

    public function test_validate_availability_returns_available_true_when_all_items_available(): void
    {
        $result = $this->allocationService->validateAvailability([
            ['model' => $this->item, 'quantity' => 5],
        ]);

        expect($result['available'])->toBeTrue();
        expect($result['issues'])->toBeEmpty();
    }

    public function test_validate_availability_returns_issues_when_insufficient(): void
    {
        $result = $this->allocationService->validateAvailability([
            ['model' => $this->item, 'quantity' => 50],
        ]);

        expect($result['available'])->toBeFalse();
        expect($result['issues'])->toHaveCount(1);
        expect($result['issues'][0]['requested'])->toBe(50);
        expect($result['issues'][0]['available'])->toBe(16);
    }

    public function test_get_strategy_returns_default_from_config(): void
    {
        config()->set('inventory.allocation_strategy', 'fifo');

        $strategy = $this->allocationService->getStrategy($this->item);

        expect($strategy)->toBe(AllocationStrategy::FIFO);
    }

    public function test_release_allocation_releases_single_allocation(): void
    {
        Event::fake();

        $allocations = $this->allocationService->allocate($this->item, 5, 'cart-single-release');
        $allocation = $allocations->first();

        $released = $this->allocationService->releaseAllocation($allocation);

        expect($released)->toBe(5);
        expect(InventoryAllocation::find($allocation->id))->toBeNull();

        Event::assertDispatched(InventoryReleased::class);
    }

    public function test_single_location_strategy_does_not_split(): void
    {
        config()->set('inventory.allocation_strategy', 'single_location');

        $allocations = $this->allocationService->allocate($this->item, 8, 'cart-single-loc');

        expect($allocations)->toHaveCount(1);
        expect($allocations->first()->quantity)->toBe(8);
    }

    public function test_allocate_uses_fifo_strategy(): void
    {
        config()->set('inventory.allocation_strategy', 'fifo');

        // Location A was created first, so it should be used first
        $allocations = $this->allocationService->allocate($this->item, 5, 'cart-fifo');

        expect($allocations)->toHaveCount(1);
        expect($allocations->first()->location_id)->toBe($this->locationA->id);
    }

    public function test_allocate_uses_least_stock_strategy(): void
    {
        config()->set('inventory.allocation_strategy', 'least_stock');

        // LeastStock allocates from locations with MOST available stock first to balance
        $allocations = $this->allocationService->allocate($this->item, 5, 'cart-least');

        // Location A has 10, Location B has 6, so A should be used first
        expect($allocations->first()->location_id)->toBe($this->locationA->id);
    }

    public function test_commit_dispatches_out_of_inventory_event_when_stock_depleted(): void
    {
        Event::fake();
        config()->set('inventory.events.low_inventory', true);
        config()->set('inventory.events.out_of_inventory', true);

        // Allocate and commit all stock from location A
        $this->allocationService->allocate($this->item, 10, 'cart-deplete');
        $this->allocationService->commit('cart-deplete', 'ORD-DEPLETE');

        Event::assertDispatched(OutOfInventory::class);
    }

    public function test_commit_dispatches_low_inventory_event_when_below_threshold(): void
    {
        Event::fake();
        config()->set('inventory.events.low_inventory', true);

        // Set low stock threshold
        $level = $this->inventoryService->getLevel($this->item, $this->locationA->id);
        $level->update(['reorder_point' => 5]);

        // Allocate 8 of 10, leaving 2 which is below reorder_point of 5
        $this->allocationService->allocate($this->item, 8, 'cart-low');
        $this->allocationService->commit('cart-low', 'ORD-LOW');

        Event::assertDispatched(LowInventoryDetected::class);
    }

    public function test_split_allocation_disabled_requires_single_location(): void
    {
        config()->set('inventory.allow_split_allocation', false);

        // Can't allocate 12 because neither location has enough (A has 10, B has 6)
        expect(fn () => $this->allocationService->allocate($this->item, 12, 'cart-no-split'))
            ->toThrow(InvalidArgumentException::class);
    }

    public function test_release_returns_zero_when_no_allocations(): void
    {
        $released = $this->allocationService->release($this->item, 'non-existent-cart');

        expect($released)->toBe(0);
    }
}
