<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\FilamentInventory\Actions\AdjustStockAction;
use AIArmada\FilamentInventory\Actions\ApproveReorderSuggestionAction;
use AIArmada\FilamentInventory\Actions\CycleCountAction;
use AIArmada\FilamentInventory\Actions\ReleaseAllocationAction;
use AIArmada\FilamentInventory\Actions\ReceiveStockAction;
use AIArmada\FilamentInventory\Actions\RejectReorderSuggestionAction;
use AIArmada\FilamentInventory\Actions\ShipStockAction;
use AIArmada\FilamentInventory\Actions\TransferStockAction;
use AIArmada\Inventory\Enums\ReorderSuggestionStatus;
use AIArmada\Inventory\Facades\InventoryAllocation as InventoryAllocationFacade;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryReorderSuggestion;
use AIArmada\Inventory\Services\InventoryService;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    config()->set('inventory.owner.enabled', false);
    config()->set('filament-inventory.cache.stats_ttl', 0);
});

function setFilamentInventoryOwnerResolver(?Model $owner): void
{
    app()->instance(OwnerResolverInterface::class, new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private readonly ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });
}

it('executes receive stock action and creates inventory', function (): void {
    $item = InventoryItem::create(['name' => 'Widget']);
    $location = InventoryLocation::factory()->create();

    $action = ReceiveStockAction::make();

    $callback = $action->getActionFunction();
    expect($callback)->not()->toBeNull();

    $callback($item, [
        'location_id' => $location->id,
        'quantity' => 5,
        'purchase_order' => 'PO-1',
        'supplier' => 'ACME',
        'received_at' => now(),
        'notes' => 'ok',
    ]);

    $level = InventoryLevel::query()
        ->where('inventoryable_type', $item->getMorphClass())
        ->where('inventoryable_id', $item->getKey())
        ->where('location_id', $location->id)
        ->first();

    expect($level)->not()->toBeNull();
    expect((int) $level?->quantity_on_hand)->toBe(5);
});

it('executes ship stock action with insufficient stock without throwing', function (): void {
    $item = InventoryItem::create(['name' => 'Widget']);
    $location = InventoryLocation::factory()->create();

    $action = ShipStockAction::make();

    $callback = $action->getActionFunction();
    expect($callback)->not()->toBeNull();

    $callback($item, [
        'location_id' => $location->id,
        'quantity' => 10,
        'order_number' => 'ORD-1',
        'customer' => 'Customer',
        'tracking_number' => 'TRK',
        'shipped_at' => now(),
        'notes' => 'ship',
    ]);

    expect(true)->toBeTrue();
});

it('executes transfer stock action and moves stock between locations', function (): void {
    $item = InventoryItem::create(['name' => 'Widget']);
    $from = InventoryLocation::factory()->create(['name' => 'From']);
    $to = InventoryLocation::factory()->create(['name' => 'To']);

    $inventory = app(InventoryService::class);
    $inventory->receive(model: $item, locationId: $from->id, quantity: 10);

    $action = TransferStockAction::make();
    $callback = $action->getActionFunction();
    expect($callback)->not()->toBeNull();

    $callback($item, [
        'from_location_id' => $from->id,
        'to_location_id' => $to->id,
        'quantity' => 3,
        'notes' => 'move',
    ]);

    $fromLevel = InventoryLevel::query()->where('location_id', $from->id)->first();
    $toLevel = InventoryLevel::query()->where('location_id', $to->id)->first();

    expect($fromLevel)->not()->toBeNull();
    expect($toLevel)->not()->toBeNull();

    expect((int) $fromLevel?->quantity_on_hand)->toBe(7);
    expect((int) $toLevel?->quantity_on_hand)->toBe(3);
});

it('executes adjust stock action and sets new quantity', function (): void {
    $item = InventoryItem::create(['name' => 'Widget']);
    $location = InventoryLocation::factory()->create();

    $inventory = app(InventoryService::class);
    $inventory->receive(model: $item, locationId: $location->id, quantity: 10);

    $action = AdjustStockAction::make();

    $callback = $action->getActionFunction();
    expect($callback)->not()->toBeNull();

    $callback($item, [
        'location_id' => $location->id,
        'new_quantity' => 4,
        'reason' => 'correction',
        'notes' => 'adjust',
    ]);

    $level = InventoryLevel::query()->where('location_id', $location->id)->first();
    expect((int) $level?->quantity_on_hand)->toBe(4);
});

it('executes cycle count action for both no-variance and variance cases', function (): void {
    $item = InventoryItem::create(['name' => 'Widget']);
    $location = InventoryLocation::factory()->create();

    $inventory = app(InventoryService::class);
    $inventory->receive(model: $item, locationId: $location->id, quantity: 10);

    $action = CycleCountAction::make();
    $callback = $action->getActionFunction();
    expect($callback)->not()->toBeNull();

    // variance 0 branch
    $callback($item, [
        'location_id' => $location->id,
        'system_quantity' => 10,
        'counted_quantity' => 10,
        'counter' => 'Alice',
    ]);

    // variance branch (triggers adjustment)
    $callback($item, [
        'location_id' => $location->id,
        'system_quantity' => 10,
        'counted_quantity' => 8,
        'counter' => 'Bob',
    ]);

    $level = InventoryLevel::query()->where('location_id', $location->id)->first();
    expect((int) $level?->quantity_on_hand)->toBe(8);
});

it('prevents forged cross-tenant location IDs in stock actions when owner scoping is enabled', function (): void {
    config()->set('inventory.owner.enabled', true);
    config()->set('inventory.owner.include_global', true);

    $ownerA = InventoryItem::create(['name' => 'Owner A']);
    $ownerB = InventoryItem::create(['name' => 'Owner B']);

    setFilamentInventoryOwnerResolver($ownerA);

    $item = InventoryItem::create(['name' => 'Widget']);

    $locationA = InventoryLocation::factory()->create([
        'owner_type' => $ownerA->getMorphClass(),
        'owner_id' => $ownerA->getKey(),
    ]);

    setFilamentInventoryOwnerResolver($ownerB);
    $locationB = InventoryLocation::factory()->create([
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    setFilamentInventoryOwnerResolver($ownerA);

    $receive = ReceiveStockAction::make()->getActionFunction();
    expect($receive)->not()->toBeNull();

    $receive($item, [
        'location_id' => $locationB->id,
        'quantity' => 5,
        'received_at' => now(),
    ]);

    $levelB = InventoryLevel::query()
        ->where('inventoryable_type', $item->getMorphClass())
        ->where('inventoryable_id', $item->getKey())
        ->where('location_id', $locationB->id)
        ->first();

    expect($levelB)->toBeNull();

    $inventory = app(InventoryService::class);
    $inventory->receive(model: $item, locationId: $locationA->id, quantity: 10);

    $ship = ShipStockAction::make()->getActionFunction();
    expect($ship)->not()->toBeNull();

    $ship($item, [
        'location_id' => $locationB->id,
        'quantity' => 1,
        'shipped_at' => now(),
    ]);

    $levelAAfter = InventoryLevel::query()->where('location_id', $locationA->id)->first();
    expect((int) $levelAAfter?->quantity_on_hand)->toBe(10);

    $adjust = AdjustStockAction::make()->getActionFunction();
    expect($adjust)->not()->toBeNull();

    $adjust($item, [
        'location_id' => $locationB->id,
        'new_quantity' => 1,
        'reason' => 'correction',
    ]);

    $levelAAfterAdjust = InventoryLevel::query()->where('location_id', $locationA->id)->first();
    expect((int) $levelAAfterAdjust?->quantity_on_hand)->toBe(10);

    $transfer = TransferStockAction::make()->getActionFunction();
    expect($transfer)->not()->toBeNull();

    $transfer($item, [
        'from_location_id' => $locationA->id,
        'to_location_id' => $locationB->id,
        'quantity' => 3,
    ]);

    $levelATransfer = InventoryLevel::query()->where('location_id', $locationA->id)->first();
    expect((int) $levelATransfer?->quantity_on_hand)->toBe(10);
});

it('prevents releasing allocations outside the current owner context', function (): void {
    config()->set('inventory.owner.enabled', true);
    config()->set('inventory.owner.include_global', true);

    $ownerA = InventoryItem::create(['name' => 'Owner A']);
    $ownerB = InventoryItem::create(['name' => 'Owner B']);

    $item = InventoryItem::create(['name' => 'Widget']);

    setFilamentInventoryOwnerResolver($ownerB);

    $locationB = InventoryLocation::factory()->create([
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    $inventory = app(InventoryService::class);
    $inventory->receive(model: $item, locationId: $locationB->id, quantity: 10);

    $allocations = InventoryAllocationFacade::allocate($item, 5, 'CART-1');
    /** @var InventoryAllocation $allocation */
    $allocation = $allocations->first();
    expect($allocation)->not()->toBeNull();

    $levelBefore = InventoryLevel::query()
        ->where('inventoryable_type', $item->getMorphClass())
        ->where('inventoryable_id', $item->getKey())
        ->where('location_id', $locationB->id)
        ->first();

    expect((int) $levelBefore?->quantity_reserved)->toBe(5);

    setFilamentInventoryOwnerResolver($ownerA);

    $release = ReleaseAllocationAction::make()->getActionFunction();
    expect($release)->not()->toBeNull();

    $release($allocation);

    setFilamentInventoryOwnerResolver($ownerB);

    expect(InventoryAllocation::query()->whereKey($allocation->getKey())->exists())->toBeTrue();

    $levelAfter = InventoryLevel::query()
        ->where('inventoryable_type', $item->getMorphClass())
        ->where('inventoryable_id', $item->getKey())
        ->where('location_id', $locationB->id)
        ->first();

    expect((int) $levelAfter?->quantity_reserved)->toBe(5);
});

it('prevents approving reorder suggestions outside the current owner context', function (): void {
    config()->set('inventory.owner.enabled', true);
    config()->set('inventory.owner.include_global', true);

    $ownerA = InventoryItem::create(['name' => 'Owner A']);
    $ownerB = InventoryItem::create(['name' => 'Owner B']);

    setFilamentInventoryOwnerResolver($ownerB);

    $locationB = InventoryLocation::factory()->create([
        'owner_type' => $ownerB->getMorphClass(),
        'owner_id' => $ownerB->getKey(),
    ]);

    $suggestion = InventoryReorderSuggestion::factory()->create([
        'location_id' => $locationB->id,
    ]);

    expect($suggestion->status->value)->toBe('pending');

    setFilamentInventoryOwnerResolver($ownerA);

    $approve = ApproveReorderSuggestionAction::make()->getActionFunction();
    expect($approve)->not()->toBeNull();
    $approve($suggestion);

    $suggestion->refresh();
    expect($suggestion->status->value)->toBe('pending');
    expect($suggestion->approved_at)->toBeNull();
});

it('does not change reorder suggestion when approve is a no-op', function (): void {
    config()->set('inventory.owner.enabled', false);

    $suggestion = InventoryReorderSuggestion::factory()->approved()->create();

    $approve = ApproveReorderSuggestionAction::make()->getActionFunction();
    expect($approve)->not()->toBeNull();
    $approve($suggestion);

    $suggestion->refresh();
    expect($suggestion->status->value)->toBe('approved');
});

it('does not change reorder suggestion when reject is a no-op', function (): void {
    config()->set('inventory.owner.enabled', false);

    $suggestion = InventoryReorderSuggestion::factory()->create([
        'status' => ReorderSuggestionStatus::Received,
    ]);

    $reject = RejectReorderSuggestionAction::make()->getActionFunction();
    expect($reject)->not()->toBeNull();
    $reject($suggestion);

    $suggestion->refresh();
    expect($suggestion->status->value)->toBe('received');
});
