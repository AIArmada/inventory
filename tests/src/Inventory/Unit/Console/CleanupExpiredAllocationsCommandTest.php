<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;

class CleanupExpiredAllocationsCommandTest extends InventoryTestCase
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
            'quantity_reserved' => 10,
        ]);
    }

    public function test_cleanup_command_removes_expired_allocations(): void
    {
        // Create expired allocation
        InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-expired',
            'quantity' => 5,
            'expires_at' => now()->subHour(),
        ]);

        // Create active allocation
        InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-active',
            'quantity' => 5,
            'expires_at' => now()->addHour(),
        ]);

        $this->artisan('inventory:cleanup-allocations')
            ->expectsOutput('Cleaning up expired inventory allocations...')
            ->assertSuccessful();

        // Expired should be gone, active should remain
        expect(InventoryAllocation::where('cart_id', 'cart-expired')->exists())->toBeFalse();
        expect(InventoryAllocation::where('cart_id', 'cart-active')->exists())->toBeTrue();
    }

    public function test_cleanup_command_dry_run_shows_count(): void
    {
        // Create expired allocations
        InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-expired-1',
            'quantity' => 5,
            'expires_at' => now()->subHour(),
        ]);
        InventoryAllocation::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'level_id' => $this->level->id,
            'cart_id' => 'cart-expired-2',
            'quantity' => 3,
            'expires_at' => now()->subMinutes(30),
        ]);

        $this->artisan('inventory:cleanup-allocations', ['--dry-run' => true])
            ->expectsOutput('Running in dry-run mode (no changes will be made)')
            ->expectsOutput('Would clean up 2 expired allocations.')
            ->assertSuccessful();

        // Both should still exist since dry run
        expect(InventoryAllocation::where('cart_id', 'cart-expired-1')->exists())->toBeTrue();
        expect(InventoryAllocation::where('cart_id', 'cart-expired-2')->exists())->toBeTrue();
    }

    public function test_cleanup_command_handles_no_expired_allocations(): void
    {
        $this->artisan('inventory:cleanup-allocations')
            ->expectsOutput('Cleaning up expired inventory allocations...')
            ->expectsOutput('Cleaned up 0 expired allocations.')
            ->assertSuccessful();
    }
}
