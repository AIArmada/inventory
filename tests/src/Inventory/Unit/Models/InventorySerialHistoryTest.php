<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\SerialEventType;
use AIArmada\Inventory\Enums\SerialStatus;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventorySerial;
use AIArmada\Inventory\Models\InventorySerialHistory;

beforeEach(function (): void {
    $this->item = InventoryItem::create(['name' => 'Serialized Product']);
    $this->location = InventoryLocation::factory()->create();
    $this->serial = InventorySerial::factory()->create([
        'inventoryable_type' => $this->item->getMorphClass(),
        'inventoryable_id' => $this->item->getKey(),
        'location_id' => $this->location->id,
        'serial_number' => 'SN-HISTORY-TEST',
    ]);
});

describe('InventorySerialHistory', function (): void {
    describe('relationships', function (): void {
        it('belongs to serial', function (): void {
            $history = InventorySerialHistory::create([
                'serial_id' => $this->serial->id,
                'event_type' => SerialEventType::Received->value,
                'occurred_at' => now(),
            ]);

            expect($history->serial)->toBeInstanceOf(InventorySerial::class);
            expect($history->serial->id)->toBe($this->serial->id);
        });

        it('belongs to from location', function (): void {
            $history = InventorySerialHistory::create([
                'serial_id' => $this->serial->id,
                'event_type' => SerialEventType::Transferred->value,
                'from_location_id' => $this->location->id,
                'occurred_at' => now(),
            ]);

            expect($history->fromLocation)->toBeInstanceOf(InventoryLocation::class);
            expect($history->fromLocation->id)->toBe($this->location->id);
        });

        it('belongs to to location', function (): void {
            $toLocation = InventoryLocation::factory()->create();
            $history = InventorySerialHistory::create([
                'serial_id' => $this->serial->id,
                'event_type' => SerialEventType::Transferred->value,
                'to_location_id' => $toLocation->id,
                'occurred_at' => now(),
            ]);

            expect($history->toLocation)->toBeInstanceOf(InventoryLocation::class);
            expect($history->toLocation->id)->toBe($toLocation->id);
        });

        it('has morph to related entity', function (): void {
            $history = InventorySerialHistory::create([
                'serial_id' => $this->serial->id,
                'event_type' => SerialEventType::Sold->value,
                'related_to_type' => $this->item->getMorphClass(),
                'related_to_id' => $this->item->getKey(),
                'occurred_at' => now(),
            ]);

            expect($history->relatedTo)->not->toBeNull();
            expect($history->relatedTo->id)->toBe($this->item->id);
        });
    });

    describe('getEventTypeEnum', function (): void {
        it('returns event type as enum', function (): void {
            $history = InventorySerialHistory::create([
                'serial_id' => $this->serial->id,
                'event_type' => SerialEventType::Received->value,
                'occurred_at' => now(),
            ]);

            expect($history->getEventTypeEnum())->toBe(SerialEventType::Received);
        });

        it('returns correct enum for each type', function (): void {
            foreach (SerialEventType::cases() as $eventType) {
                $history = InventorySerialHistory::create([
                    'serial_id' => $this->serial->id,
                    'event_type' => $eventType->value,
                    'occurred_at' => now(),
                ]);

                expect($history->getEventTypeEnum())->toBe($eventType);
            }
        });
    });

    describe('scopeOfType', function (): void {
        it('filters by event type', function (): void {
            InventorySerialHistory::create([
                'serial_id' => $this->serial->id,
                'event_type' => SerialEventType::Received->value,
                'occurred_at' => now(),
            ]);

            InventorySerialHistory::create([
                'serial_id' => $this->serial->id,
                'event_type' => SerialEventType::Sold->value,
                'occurred_at' => now(),
            ]);

            $received = InventorySerialHistory::ofType(SerialEventType::Received)->get();
            $sold = InventorySerialHistory::ofType(SerialEventType::Sold)->get();

            expect($received)->toHaveCount(1);
            expect($sold)->toHaveCount(1);
            expect($received->first()->event_type)->toBe(SerialEventType::Received->value);
        });
    });

    describe('scopeBetweenDates', function (): void {
        it('filters by date range', function (): void {
            InventorySerialHistory::create([
                'serial_id' => $this->serial->id,
                'event_type' => SerialEventType::Received->value,
                'occurred_at' => now()->subDays(10),
            ]);

            InventorySerialHistory::create([
                'serial_id' => $this->serial->id,
                'event_type' => SerialEventType::Transferred->value,
                'occurred_at' => now()->subDays(5),
            ]);

            InventorySerialHistory::create([
                'serial_id' => $this->serial->id,
                'event_type' => SerialEventType::Sold->value,
                'occurred_at' => now(),
            ]);

            $filtered = InventorySerialHistory::betweenDates(
                now()->subDays(7),
                now()->subDays(3)
            )->get();

            expect($filtered)->toHaveCount(1);
            expect($filtered->first()->event_type)->toBe(SerialEventType::Transferred->value);
        });
    });

    describe('fillable attributes', function (): void {
        it('can create with all fillable attributes', function (): void {
            $toLocation = InventoryLocation::factory()->create();

            $history = InventorySerialHistory::create([
                'serial_id' => $this->serial->id,
                'event_type' => SerialEventType::Transferred->value,
                'previous_status' => SerialStatus::Available->value,
                'new_status' => SerialStatus::Reserved->value,
                'from_location_id' => $this->location->id,
                'to_location_id' => $toLocation->id,
                'reference' => 'ORDER-123',
                'user_id' => 'user-456',
                'actor_name' => 'John Doe',
                'notes' => 'Transferred for order fulfillment',
                'metadata' => ['reason' => 'priority_shipment'],
                'occurred_at' => now(),
            ]);

            expect($history->serial_id)->toBe($this->serial->id);
            expect($history->event_type)->toBe(SerialEventType::Transferred->value);
            expect($history->previous_status)->toBe(SerialStatus::Available->value);
            expect($history->new_status)->toBe(SerialStatus::Reserved->value);
            expect($history->from_location_id)->toBe($this->location->id);
            expect($history->to_location_id)->toBe($toLocation->id);
            expect($history->reference)->toBe('ORDER-123');
            expect($history->user_id)->toBe('user-456');
            expect($history->actor_name)->toBe('John Doe');
            expect($history->notes)->toBe('Transferred for order fulfillment');
            expect($history->metadata)->toBe(['reason' => 'priority_shipment']);
        });
    });

    describe('casts', function (): void {
        it('casts occurred_at to datetime', function (): void {
            $history = InventorySerialHistory::create([
                'serial_id' => $this->serial->id,
                'event_type' => SerialEventType::Received->value,
                'occurred_at' => '2024-01-15 10:30:00',
            ]);

            expect($history->occurred_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
        });

        it('casts metadata to array', function (): void {
            $history = InventorySerialHistory::create([
                'serial_id' => $this->serial->id,
                'event_type' => SerialEventType::Received->value,
                'occurred_at' => now(),
                'metadata' => ['key' => 'value', 'nested' => ['a' => 1]],
            ]);

            expect($history->metadata)->toBeArray();
            expect($history->metadata['key'])->toBe('value');
            expect($history->metadata['nested']['a'])->toBe(1);
        });
    });
});
