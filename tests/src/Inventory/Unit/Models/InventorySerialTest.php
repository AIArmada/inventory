<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Commerce\Tests\Inventory\InventoryTestCase;
use AIArmada\Inventory\Enums\SerialCondition;
use AIArmada\Inventory\Enums\SerialStatus;
use AIArmada\Inventory\Models\InventoryBatch;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventorySerial;
use AIArmada\Inventory\Models\InventorySerialHistory;

class InventorySerialTest extends InventoryTestCase
{
    protected InventoryItem $item;

    protected InventoryLocation $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->item = InventoryItem::create(['name' => 'Test Item']);
        $this->location = InventoryLocation::factory()->create();
    }

    public function test_can_create_serial(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'serial_number' => 'SN-12345',
            'status' => SerialStatus::Available->value,
            'condition' => SerialCondition::New->value,
        ]);

        expect($serial)->toBeInstanceOf(InventorySerial::class);
        expect($serial->serial_number)->toBe('SN-12345');
    }

    public function test_inventoryable_relationship(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
        ]);

        expect($serial->inventoryable)->not->toBeNull();
        expect($serial->inventoryable->id)->toBe($this->item->id);
    }

    public function test_location_relationship(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
        ]);

        expect($serial->location)->not->toBeNull();
        expect($serial->location->id)->toBe($this->location->id);
    }

    public function test_batch_relationship(): void
    {
        $batch = InventoryBatch::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
        ]);

        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'batch_id' => $batch->id,
        ]);

        expect($serial->batch)->not->toBeNull();
        expect($serial->batch->id)->toBe($batch->id);
    }

    public function test_get_status_enum(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::Available->value,
        ]);

        expect($serial->getStatusEnum())->toBe(SerialStatus::Available);
    }

    public function test_get_condition_enum(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'condition' => SerialCondition::New->value,
        ]);

        expect($serial->getConditionEnum())->toBe(SerialCondition::New);
    }

    public function test_is_warranty_active_returns_true_when_future(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'warranty_expires_at' => now()->addYear(),
        ]);

        expect($serial->is_warranty_active)->toBeTrue();
    }

    public function test_is_warranty_active_returns_false_when_past(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'warranty_expires_at' => now()->subDay(),
        ]);

        expect($serial->is_warranty_active)->toBeFalse();
    }

    public function test_is_warranty_active_returns_false_when_null(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'warranty_expires_at' => null,
        ]);

        expect($serial->is_warranty_active)->toBeFalse();
    }

    public function test_days_until_warranty_expires(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'warranty_expires_at' => now()->addDays(30),
        ]);

        expect($serial->days_until_warranty_expires)->toBeGreaterThanOrEqual(29);
        expect($serial->days_until_warranty_expires)->toBeLessThanOrEqual(30);
    }

    public function test_is_under_warranty_method(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'warranty_expires_at' => now()->addYear(),
        ]);

        expect($serial->isUnderWarranty())->toBeTrue();
    }

    public function test_warranty_days_remaining_method(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'warranty_expires_at' => now()->addDays(45),
        ]);

        expect($serial->warrantyDaysRemaining())->toBeGreaterThanOrEqual(44);
        expect($serial->warrantyDaysRemaining())->toBeLessThanOrEqual(45);
    }

    public function test_is_available(): void
    {
        $available = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::Available->value,
            'condition' => SerialCondition::New->value,
        ]);

        $sold = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::Sold->value,
            'condition' => SerialCondition::New->value,
        ]);

        expect($available->isAvailable())->toBeTrue();
        expect($sold->isAvailable())->toBeFalse();
    }

    public function test_scope_with_status(): void
    {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::Available->value,
        ]);
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::Sold->value,
        ]);

        $available = InventorySerial::withStatus(SerialStatus::Available)->get();

        expect($available)->toHaveCount(1);
    }

    public function test_scope_available(): void
    {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::Available->value,
        ]);
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::Sold->value,
        ]);

        $available = InventorySerial::available()->get();

        expect($available)->toHaveCount(1);
    }

    public function test_scope_in_stock(): void
    {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::Available->value,
        ]);
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::Reserved->value,
        ]);
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::Sold->value,
        ]);

        $inStock = InventorySerial::inStock()->get();

        expect($inStock)->toHaveCount(2);
    }

    public function test_scope_at_location(): void
    {
        $location2 = InventoryLocation::factory()->create();

        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $this->location->id,
        ]);
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'location_id' => $location2->id,
        ]);

        $atLocation = InventorySerial::atLocation($this->location->id)->get();

        expect($atLocation)->toHaveCount(1);
    }

    public function test_scope_with_condition(): void
    {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'condition' => SerialCondition::New->value,
        ]);
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'condition' => SerialCondition::Used->value,
        ]);

        $newCondition = InventorySerial::withCondition(SerialCondition::New)->get();

        expect($newCondition)->toHaveCount(1);
    }

    public function test_scope_sellable(): void
    {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::Available->value,
            'condition' => SerialCondition::New->value,
        ]);
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::Available->value,
            'condition' => SerialCondition::Damaged->value,
        ]);

        $sellable = InventorySerial::sellable()->get();

        expect($sellable)->toHaveCount(1);
    }

    public function test_scope_under_warranty(): void
    {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'warranty_expires_at' => now()->addYear(),
        ]);
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'warranty_expires_at' => now()->subDay(),
        ]);

        $underWarranty = InventorySerial::underWarranty()->get();

        expect($underWarranty)->toHaveCount(1);
    }

    public function test_scope_warranty_expiring_soon(): void
    {
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'warranty_expires_at' => now()->addDays(15),
        ]);
        InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'warranty_expires_at' => now()->addDays(60),
        ]);

        $expiringSoon = InventorySerial::warrantyExpiringSoon(30)->get();

        expect($expiringSoon)->toHaveCount(1);
    }

    public function test_history_relationship(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
        ]);

        InventorySerialHistory::create([
            'serial_id' => $serial->id,
            'event_type' => 'received',
            'from_location_id' => null,
            'to_location_id' => $this->location->id,
            'occurred_at' => now(),
        ]);

        expect($serial->history)->toHaveCount(1);
    }

    public function test_deleting_serial_cascades_to_history(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
        ]);

        $history = InventorySerialHistory::create([
            'serial_id' => $serial->id,
            'event_type' => 'received',
            'occurred_at' => now(),
        ]);
        $historyId = $history->id;

        $serial->delete();

        expect(InventorySerialHistory::find($historyId))->toBeNull();
    }

    public function test_casts_are_correct(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'unit_cost_minor' => 5000,
            'warranty_expires_at' => now()->addYear(),
            'metadata' => ['key' => 'value'],
        ]);

        expect($serial->unit_cost_minor)->toBeInt();
        expect($serial->warranty_expires_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
        expect($serial->metadata)->toBeArray();
    }

    public function test_assigned_to_relationship(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'assigned_to_type' => $this->item->getMorphClass(),
            'assigned_to_id' => $this->item->getKey(),
        ]);

        expect($serial->assignedTo)->not->toBeNull();
    }

    public function test_can_transition_to(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::Available->value,
        ]);

        // Available can transition to Reserved
        expect($serial->canTransitionTo(SerialStatus::Reserved))->toBeTrue();
    }

    public function test_transition_to_changes_status(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::Available->value,
        ]);

        $result = $serial->transitionTo(SerialStatus::Reserved);

        expect($result)->toBe($serial);
        expect($serial->fresh()->status)->toBe(SerialStatus::Reserved->value);
    }

    public function test_transition_to_throws_on_invalid_transition(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'status' => SerialStatus::Sold->value,
        ]);

        expect(fn () => $serial->transitionTo(SerialStatus::Available))
            ->toThrow(InvalidArgumentException::class);
    }

    public function test_days_until_warranty_expires_with_null(): void
    {
        $serial = InventorySerial::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'warranty_expires_at' => null,
        ]);

        expect($serial->days_until_warranty_expires)->toBeNull();
    }
}
