<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\SerializedInventoryItem;
use AIArmada\Inventory\Enums\SerialCondition;
use AIArmada\Inventory\Enums\SerialStatus;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventorySerial;

beforeEach(function (): void {
    $this->item = SerializedInventoryItem::create(['name' => 'Serialized Product']);
    $this->location = InventoryLocation::factory()->create();
});

describe('HasSerialNumbers trait', function (): void {
    describe('serials relationship', function (): void {
        it('returns morph many relationship', function (): void {
            expect($this->item->serials())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\MorphMany::class);
        });

        it('returns serials for the model', function (): void {
            $serial = InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
            ]);

            expect($this->item->serials)->toHaveCount(1);
            expect($this->item->serials->first()->id)->toBe($serial->id);
        });
    });

    describe('availableSerials', function (): void {
        it('returns only available serials', function (): void {
            InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'status' => SerialStatus::Available->value,
            ]);

            InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'status' => SerialStatus::Sold->value,
            ]);

            expect($this->item->availableSerials())->toHaveCount(1);
        });
    });

    describe('sellableSerials', function (): void {
        it('returns sellable serials', function (): void {
            InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'status' => SerialStatus::Available->value,
            ]);

            expect($this->item->sellableSerials())->toHaveCount(1);
        });
    });

    describe('serialsAtLocation', function (): void {
        it('returns serials at specific location', function (): void {
            InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
            ]);

            $otherLocation = InventoryLocation::factory()->create();
            InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $otherLocation->id,
            ]);

            expect($this->item->serialsAtLocation($this->location->id))->toHaveCount(1);
        });
    });

    describe('serialsByStatus', function (): void {
        it('returns serials filtered by status', function (): void {
            InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'status' => SerialStatus::Sold->value,
            ]);

            InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'status' => SerialStatus::Available->value,
            ]);

            expect($this->item->serialsByStatus(SerialStatus::Sold))->toHaveCount(1);
        });
    });

    describe('serialsByCondition', function (): void {
        it('returns serials filtered by condition', function (): void {
            InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'condition' => SerialCondition::New->value,
            ]);

            InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'condition' => SerialCondition::Refurbished->value,
            ]);

            expect($this->item->serialsByCondition(SerialCondition::New))->toHaveCount(1);
        });
    });

    describe('registerSerial', function (): void {
        it('creates a new serial', function (): void {
            $serial = $this->item->registerSerial(
                'SN-12345',
                $this->location->id,
            );

            expect($serial)->toBeInstanceOf(InventorySerial::class);
            expect($serial->serial_number)->toBe('SN-12345');
            expect($serial->status)->toBe(SerialStatus::Available->value);
            expect($serial->condition)->toBe(SerialCondition::New->value);
        });

        it('creates serial with all parameters', function (): void {
            $warrantyDate = now()->addYear();

            $serial = $this->item->registerSerial(
                'SN-67890',
                $this->location->id,
                'batch-123',
                SerialCondition::Refurbished,
                5000,
                $warrantyDate,
            );

            expect($serial->batch_id)->toBe('batch-123');
            expect($serial->condition)->toBe(SerialCondition::Refurbished->value);
            expect($serial->unit_cost_minor)->toBe(5000);
            expect($serial->warranty_expires_at->toDateString())->toBe($warrantyDate->toDateString());
        });
    });

    describe('registerSerials', function (): void {
        it('creates multiple serials', function (): void {
            $serials = $this->item->registerSerials(
                ['SN-001', 'SN-002', 'SN-003'],
                $this->location->id,
            );

            expect($serials)->toHaveCount(3);
            expect($this->item->serials)->toHaveCount(3);
        });
    });

    describe('serialCountsByStatus', function (): void {
        it('returns counts grouped by status', function (): void {
            InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'status' => SerialStatus::Available->value,
            ]);

            InventorySerial::factory()->count(2)->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'status' => SerialStatus::Sold->value,
            ]);

            $counts = $this->item->serialCountsByStatus();

            expect($counts)->toBeArray();
            expect($counts[SerialStatus::Available->value])->toBe(1);
            expect($counts[SerialStatus::Sold->value])->toBe(2);
        });
    });

    describe('serialCountsByCondition', function (): void {
        it('returns counts grouped by condition', function (): void {
            InventorySerial::factory()->count(3)->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'condition' => SerialCondition::New->value,
            ]);

            InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'condition' => SerialCondition::Refurbished->value,
            ]);

            $counts = $this->item->serialCountsByCondition();

            expect($counts)->toBeArray();
            expect($counts[SerialCondition::New->value])->toBe(3);
            expect($counts[SerialCondition::Refurbished->value])->toBe(1);
        });
    });

    describe('totalSerialCount', function (): void {
        it('returns total count of serials', function (): void {
            InventorySerial::factory()->count(5)->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
            ]);

            expect($this->item->totalSerialCount())->toBe(5);
        });
    });

    describe('availableSerialCount', function (): void {
        it('returns count of available serials', function (): void {
            InventorySerial::factory()->count(3)->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'status' => SerialStatus::Available->value,
            ]);

            InventorySerial::factory()->count(2)->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'status' => SerialStatus::Sold->value,
            ]);

            expect($this->item->availableSerialCount())->toBe(3);
        });
    });

    describe('sellableSerialCount', function (): void {
        it('returns count of sellable serials', function (): void {
            InventorySerial::factory()->count(2)->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'status' => SerialStatus::Available->value,
            ]);

            expect($this->item->sellableSerialCount())->toBe(2);
        });
    });

    describe('hasAvailableSerial', function (): void {
        it('returns true when available serials exist', function (): void {
            InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'status' => SerialStatus::Available->value,
            ]);

            expect($this->item->hasAvailableSerial())->toBeTrue();
        });

        it('returns false when no available serials', function (): void {
            InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'status' => SerialStatus::Sold->value,
            ]);

            expect($this->item->hasAvailableSerial())->toBeFalse();
        });
    });

    describe('getNextAvailableSerial', function (): void {
        it('returns oldest available serial (FIFO)', function (): void {
            $oldSerial = InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'status' => SerialStatus::Available->value,
                'received_at' => now()->subDays(10),
            ]);

            InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'status' => SerialStatus::Available->value,
                'received_at' => now()->subDay(),
            ]);

            expect($this->item->getNextAvailableSerial()->id)->toBe($oldSerial->id);
        });

        it('filters by location when provided', function (): void {
            $otherLocation = InventoryLocation::factory()->create();

            $serial = InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'status' => SerialStatus::Available->value,
            ]);

            InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $otherLocation->id,
                'status' => SerialStatus::Available->value,
            ]);

            expect($this->item->getNextAvailableSerial($this->location->id)->id)->toBe($serial->id);
        });

        it('returns null when no available serial', function (): void {
            expect($this->item->getNextAvailableSerial())->toBeNull();
        });
    });

    describe('findSerial', function (): void {
        it('finds serial by serial number', function (): void {
            $serial = InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'serial_number' => 'UNIQUE-SN-123',
            ]);

            expect($this->item->findSerial('UNIQUE-SN-123')->id)->toBe($serial->id);
        });

        it('returns null when not found', function (): void {
            expect($this->item->findSerial('NONEXISTENT'))->toBeNull();
        });
    });

    describe('hasSerial', function (): void {
        it('returns true when serial exists', function (): void {
            InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'serial_number' => 'CHECK-SN-999',
            ]);

            expect($this->item->hasSerial('CHECK-SN-999'))->toBeTrue();
        });

        it('returns false when serial does not exist', function (): void {
            expect($this->item->hasSerial('MISSING'))->toBeFalse();
        });
    });

    describe('totalSerialValue', function (): void {
        it('returns sum of all serial costs', function (): void {
            InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'unit_cost_minor' => 1000,
            ]);

            InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'unit_cost_minor' => 2500,
            ]);

            expect($this->item->totalSerialValue())->toBe(3500);
        });
    });

    describe('availableSerialValue', function (): void {
        it('returns sum of available serial costs', function (): void {
            InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'status' => SerialStatus::Available->value,
                'unit_cost_minor' => 1000,
            ]);

            InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'status' => SerialStatus::Sold->value,
                'unit_cost_minor' => 2000,
            ]);

            expect($this->item->availableSerialValue())->toBe(1000);
        });
    });

    describe('serialsWithExpiringWarranty', function (): void {
        it('returns serials with warranty expiring within days', function (): void {
            $expiringSerial = InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'warranty_expires_at' => now()->addDays(15),
            ]);

            InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'warranty_expires_at' => now()->addDays(60),
            ]);

            $expiring = $this->item->serialsWithExpiringWarranty(30);

            expect($expiring)->toHaveCount(1);
            expect($expiring->first()->id)->toBe($expiringSerial->id);
        });

        it('excludes already expired warranties', function (): void {
            InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'warranty_expires_at' => now()->subDay(),
            ]);

            expect($this->item->serialsWithExpiringWarranty())->toHaveCount(0);
        });
    });

    describe('serialsUnderWarranty', function (): void {
        it('returns serials currently under warranty', function (): void {
            InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'warranty_expires_at' => now()->addYear(),
            ]);

            InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'warranty_expires_at' => now()->subDay(),
            ]);

            InventorySerial::factory()->create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'warranty_expires_at' => null,
            ]);

            expect($this->item->serialsUnderWarranty())->toHaveCount(1);
        });
    });
});
