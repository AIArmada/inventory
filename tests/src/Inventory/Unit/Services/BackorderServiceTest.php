<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Enums\BackorderPriority;
use AIArmada\Inventory\Enums\BackorderStatus;
use AIArmada\Inventory\Models\InventoryBackorder;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\BackorderService;

class BackorderServiceTest extends InventoryTestCase
{
    protected BackorderService $service;

    protected InventoryItem $item;

    protected InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new BackorderService;
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create(['is_active' => true]);
    }

    public function test_create_backorder(): void
    {
        $backorder = $this->service->create(
            $this->item,
            10,
            $this->location->id,
            'order-123',
            'customer-456',
            BackorderPriority::High,
            now()->addDays(7),
            'Urgent order'
        );

        expect($backorder)->toBeInstanceOf(InventoryBackorder::class);
        expect($backorder->inventoryable_id)->toBe($this->item->id);
        expect($backorder->quantity_requested)->toBe(10);
        expect($backorder->status)->toBe(BackorderStatus::Pending);
        expect($backorder->priority)->toBe(BackorderPriority::High);
    }

    public function test_fulfill_backorder(): void
    {
        $backorder = InventoryBackorder::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'status' => BackorderStatus::Pending,
        ]);

        $result = $this->service->fulfill($backorder, 5);

        expect($result)->toBeTrue();
        expect($backorder->fresh()->quantity_fulfilled)->toBe(5);
    }

    public function test_cancel_backorder(): void
    {
        $backorder = InventoryBackorder::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'status' => BackorderStatus::Pending,
        ]);

        $result = $this->service->cancel($backorder, 5, 'Out of stock');

        expect($result)->toBeTrue();
        expect($backorder->fresh()->quantity_cancelled)->toBe(5);
    }

    public function test_get_open_backorders(): void
    {
        InventoryBackorder::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => BackorderStatus::Pending,
        ]);
        InventoryBackorder::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => BackorderStatus::PartiallyFulfilled,
        ]);
        InventoryBackorder::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => BackorderStatus::Fulfilled,
        ]);

        $openBackorders = $this->service->getOpenBackorders($this->item);

        expect($openBackorders)->toHaveCount(2);
    }

    public function test_get_all_open_backorders(): void
    {
        InventoryBackorder::factory()->count(3)->create([
            'status' => BackorderStatus::Pending,
        ]);
        InventoryBackorder::factory()->create([
            'status' => BackorderStatus::Fulfilled,
        ]);

        $allOpen = $this->service->getAllOpenBackorders();

        expect($allOpen)->toHaveCount(3);
    }

    public function test_get_overdue_backorders(): void
    {
        InventoryBackorder::factory()->create([
            'status' => BackorderStatus::Pending,
            'promised_at' => now()->subDay(),
        ]);
        InventoryBackorder::factory()->create([
            'status' => BackorderStatus::Pending,
            'promised_at' => now()->addDays(7),
        ]);

        $overdue = $this->service->getOverdueBackorders();

        expect($overdue)->toHaveCount(1);
    }

    public function test_get_backorders_due_within(): void
    {
        InventoryBackorder::factory()->create([
            'status' => BackorderStatus::Pending,
            'promised_at' => now()->addDays(3),
        ]);
        InventoryBackorder::factory()->create([
            'status' => BackorderStatus::Pending,
            'promised_at' => now()->addDays(10),
        ]);

        $dueWithin7Days = $this->service->getBackordersDueWithin(7);

        expect($dueWithin7Days)->toHaveCount(1);
    }

    public function test_auto_fulfill(): void
    {
        InventoryBackorder::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_requested' => 5,
            'quantity_fulfilled' => 0,
            'status' => BackorderStatus::Pending,
            'priority' => BackorderPriority::High,
        ]);
        InventoryBackorder::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'status' => BackorderStatus::Pending,
            'priority' => BackorderPriority::Normal,
        ]);

        $result = $this->service->autoFulfill($this->item, 8, $this->location->id);

        expect($result['fulfilled'])->toBe(8);
        expect($result['backorders_updated'])->toBeGreaterThanOrEqual(1);
    }

    public function test_escalate_overdue(): void
    {
        InventoryBackorder::factory()->create([
            'status' => BackorderStatus::Pending,
            'priority' => BackorderPriority::Normal,
            'promised_at' => now()->subDay(),
        ]);
        InventoryBackorder::factory()->create([
            'status' => BackorderStatus::Pending,
            'priority' => BackorderPriority::Urgent,
            'promised_at' => now()->subDay(),
        ]);

        $escalated = $this->service->escalateOverdue();

        expect($escalated)->toBe(1);
    }

    public function test_expire_old(): void
    {
        InventoryBackorder::factory()->create([
            'status' => BackorderStatus::Pending,
            'requested_at' => now()->subDays(100),
        ]);
        InventoryBackorder::factory()->create([
            'status' => BackorderStatus::Pending,
            'requested_at' => now()->subDays(10),
        ]);

        $expired = $this->service->expireOld(90);

        expect($expired)->toBe(1);
    }

    public function test_get_statistics(): void
    {
        InventoryBackorder::factory()->create([
            'status' => BackorderStatus::Pending,
            'priority' => BackorderPriority::High,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
        ]);
        InventoryBackorder::factory()->create([
            'status' => BackorderStatus::PartiallyFulfilled,
            'priority' => BackorderPriority::Normal,
            'quantity_requested' => 20,
            'quantity_fulfilled' => 5,
        ]);

        $stats = $this->service->getStatistics();

        expect($stats)->toHaveKeys(['total_open', 'total_quantity', 'overdue', 'by_priority']);
        expect($stats['total_open'])->toBe(2);
    }

    public function test_get_fulfillable_backorders(): void
    {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 60,
            'quantity_reserved' => 10, // quantity_available = 50
        ]);

        InventoryBackorder::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_requested' => 10,
            'quantity_fulfilled' => 0,
            'status' => BackorderStatus::Pending,
        ]);

        $fulfillable = $this->service->getFulfillableBackorders();

        expect($fulfillable)->toHaveCount(1);
    }

    public function test_update_promised_date(): void
    {
        $backorder = InventoryBackorder::factory()->create([
            'promised_at' => now()->addDays(7),
        ]);

        $newDate = now()->addDays(14);
        $result = $this->service->updatePromisedDate($backorder, $newDate);

        expect($result)->toBeTrue();
        expect($backorder->fresh()->promised_at->toDateString())->toBe($newDate->toDateString());
    }

    public function test_get_total_backordered_quantity(): void
    {
        InventoryBackorder::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'quantity_requested' => 10,
            'quantity_fulfilled' => 2,
            'quantity_cancelled' => 0,
            'status' => BackorderStatus::Pending,
        ]);
        InventoryBackorder::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'quantity_requested' => 20,
            'quantity_fulfilled' => 5,
            'quantity_cancelled' => 3,
            'status' => BackorderStatus::PartiallyFulfilled,
        ]);

        $total = $this->service->getTotalBackorderedQuantity($this->item);

        // (10-2-0) + (20-5-3) = 8 + 12 = 20
        expect($total)->toBe(20);
    }
}
