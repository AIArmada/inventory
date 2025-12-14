<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Models\InventoryStandardCost;

class InventoryStandardCostTest extends InventoryTestCase
{
    protected InventoryItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->item = InventoryItem::create(['name' => 'Test Item']);
    }

    public function test_create(): void
    {
        $cost = InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'standard_cost_minor' => 1000,
            'effective_from' => now(),
        ]);

        expect($cost)->toBeInstanceOf(InventoryStandardCost::class);
        expect($cost->standard_cost_minor)->toBe(1000);
    }

    public function test_inventoryable_relationship(): void
    {
        $cost = InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
        ]);

        expect($cost->inventoryable)->toBeInstanceOf(InventoryItem::class);
        expect($cost->inventoryable->id)->toBe($this->item->id);
    }

    public function test_scope_for_model(): void
    {
        InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
        ]);
        InventoryStandardCost::factory()->create(); // Other item

        $costs = InventoryStandardCost::forModel($this->item)->get();

        expect($costs)->toHaveCount(1);
    }

    public function test_scope_current(): void
    {
        InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'effective_from' => now()->subMonth(),
            'effective_to' => null,
        ]);
        InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'effective_from' => now()->subMonths(2),
            'effective_to' => now()->subMonth(),
        ]);

        $current = InventoryStandardCost::forModel($this->item)->current()->get();

        expect($current)->toHaveCount(1);
    }

    public function test_scope_effective_at(): void
    {
        InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'standard_cost_minor' => 500,
            'effective_from' => now()->subMonths(3),
            'effective_to' => now()->subMonth(),
        ]);
        InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'standard_cost_minor' => 600,
            'effective_from' => now()->subMonth(),
        ]);

        $cost = InventoryStandardCost::forModel($this->item)
            ->effectiveAt(now()->subMonths(2))
            ->first();

        expect($cost->standard_cost_minor)->toBe(500);
    }

    public function test_scope_future(): void
    {
        InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'effective_from' => now()->addMonth(),
        ]);
        InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'effective_from' => now()->subMonth(),
        ]);

        $future = InventoryStandardCost::forModel($this->item)->future()->get();

        expect($future)->toHaveCount(1);
    }

    public function test_scope_expired(): void
    {
        InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'effective_from' => now()->subMonths(2),
            'effective_to' => now()->subMonth(),
        ]);
        InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'effective_from' => now()->subMonth(),
            'effective_to' => null,
        ]);

        $expired = InventoryStandardCost::forModel($this->item)->expired()->get();

        expect($expired)->toHaveCount(1);
    }

    public function test_is_current(): void
    {
        $current = InventoryStandardCost::factory()->create([
            'effective_from' => now()->subMonth(),
            'effective_to' => null,
        ]);

        $expired = InventoryStandardCost::factory()->create([
            'effective_from' => now()->subMonths(2),
            'effective_to' => now()->subMonth(),
        ]);

        expect($current->isCurrent())->toBeTrue();
        expect($expired->isCurrent())->toBeFalse();
    }

    public function test_is_future(): void
    {
        $future = InventoryStandardCost::factory()->create([
            'effective_from' => now()->addMonth(),
        ]);

        $current = InventoryStandardCost::factory()->create([
            'effective_from' => now()->subMonth(),
        ]);

        expect($future->isFuture())->toBeTrue();
        expect($current->isFuture())->toBeFalse();
    }

    public function test_is_expired(): void
    {
        $expired = InventoryStandardCost::factory()->create([
            'effective_from' => now()->subMonths(2),
            'effective_to' => now()->subMonth(),
        ]);

        $current = InventoryStandardCost::factory()->create([
            'effective_from' => now()->subMonth(),
            'effective_to' => null,
        ]);

        expect($expired->isExpired())->toBeTrue();
        expect($current->isExpired())->toBeFalse();
    }

    public function test_expire(): void
    {
        $cost = InventoryStandardCost::factory()->create([
            'effective_from' => now()->subMonth(),
            'effective_to' => null,
        ]);

        $result = $cost->expire();

        expect($result)->toBeTrue();
        expect($cost->fresh()->effective_to)->not->toBeNull();
        expect($cost->fresh()->isExpired())->toBeTrue();
    }

    public function test_casts(): void
    {
        $cost = InventoryStandardCost::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'standard_cost_minor' => 1000,
            'effective_from' => now(),
            'metadata' => ['key' => 'value'],
        ]);

        $cost = $cost->fresh();

        expect($cost->standard_cost_minor)->toBeInt();
        expect($cost->effective_from)->toBeInstanceOf(Illuminate\Support\Carbon::class);
        expect($cost->metadata)->toBeArray();
    }
}
