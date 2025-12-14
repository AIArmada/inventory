<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Enums\BatchStatus;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Strategies\AllocationContext;
use AIArmada\Inventory\Strategies\FefoStrategy;

class FefoStrategyTest extends InventoryTestCase
{
    protected FefoStrategy $strategy;

    protected InventoryItem $item;

    protected InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->strategy = new FefoStrategy();
        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create([
            'name' => 'Test Location',
            'code' => 'TEST',
        ]);
    }

    public function test_name_returns_correct_value(): void
    {
        expect($this->strategy->name())->toBe('fefo');
    }

    public function test_label_returns_correct_value(): void
    {
        expect($this->strategy->label())->toBe('First Expired, First Out');
    }

    public function test_description_returns_correct_value(): void
    {
        expect($this->strategy->description())->toContain('Allocates inventory from batches with the earliest expiry dates first');
    }

    public function test_allocate_returns_empty_array_when_no_batches(): void
    {
        $allocations = $this->strategy->allocate($this->item, 10);

        expect($allocations)->toBeEmpty();
    }

    public function test_can_fulfill_returns_false_when_no_batches(): void
    {
        $canFulfill = $this->strategy->canFulfill($this->item, 10);

        expect($canFulfill)->toBeFalse();
    }

    public function test_get_recommended_order_returns_empty_collection_when_no_batches(): void
    {
        $order = $this->strategy->getRecommendedOrder($this->item);

        expect($order)->toBeEmpty();
    }

    public function test_allocate_with_context_location_filter(): void
    {
        $context = new AllocationContext();
        $context->locationId = $this->location->id;

        $allocations = $this->strategy->allocate($this->item, 10, $context);

        expect($allocations)->toBeEmpty();
    }

    public function test_can_fulfill_with_context_location_filter(): void
    {
        $context = new AllocationContext();
        $context->locationId = $this->location->id;

        $canFulfill = $this->strategy->canFulfill($this->item, 10, $context);

        expect($canFulfill)->toBeFalse();
    }

    public function test_get_recommended_order_with_context_location_filter(): void
    {
        $context = new AllocationContext();
        $context->locationId = $this->location->id;

        $order = $this->strategy->getRecommendedOrder($this->item, $context);

        expect($order)->toBeEmpty();
    }

    public function test_get_recommended_order_returns_fefo_ordered_batches(): void
    {
        $batch1 = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'status' => BatchStatus::Active->value,
            'expires_at' => now()->addDays(30),
        ]);

        $batch2 = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'status' => BatchStatus::Active->value,
            'expires_at' => now()->addDays(10),
        ]);

        $order = $this->strategy->getRecommendedOrder($this->item);

        expect($order)->toHaveCount(2);
        expect($order->first()->id)->toBe($batch2->id); // Expires sooner
    }

    public function test_context_default_values(): void
    {
        $context = new AllocationContext();

        expect($context->locationId)->toBeNull();
        expect($context->excludeExpiringSoon)->toBeFalse();
        expect($context->minDaysToExpiry)->toBe(7);
    }

    public function test_context_can_set_location(): void
    {
        $context = new AllocationContext();
        $context->locationId = 'test-location-id';

        expect($context->locationId)->toBe('test-location-id');
    }

    public function test_context_can_exclude_expiring_soon(): void
    {
        $context = new AllocationContext();
        $context->excludeExpiringSoon = true;
        $context->minDaysToExpiry = 14;

        expect($context->excludeExpiringSoon)->toBeTrue();
        expect($context->minDaysToExpiry)->toBe(14);
    }
}