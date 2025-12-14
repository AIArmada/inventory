<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Exports\ExportableInterface;
use AIArmada\Inventory\Exports\MovementExport;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    $this->item = InventoryItem::create(['name' => 'Test Product']);
    $this->location = InventoryLocation::factory()->create(['name' => 'Test Location']);
});

describe('MovementExport', function (): void {
    it('implements ExportableInterface', function (): void {
        $export = new MovementExport();
        expect($export)->toBeInstanceOf(ExportableInterface::class);
    });

    it('returns correct headers', function (): void {
        $export = new MovementExport();
        $headers = $export->getHeaders();

        expect($headers)->toBeArray();
        expect($headers)->toContain('ID');
        expect($headers)->toContain('Type');
        expect($headers)->toContain('Quantity');
        expect($headers)->toContain('Occurred At');
    });

    it('generates filename with date', function (): void {
        $export = new MovementExport();
        $filename = $export->getFilename();

        expect($filename)->toStartWith('movements-');
        expect($filename)->toContain(CarbonImmutable::now()->format('Y-m-d'));
    });

    it('generates filename with movement type suffix', function (): void {
        $export = new MovementExport(
            movementType: MovementType::Transfer,
        );
        $filename = $export->getFilename();

        expect($filename)->toStartWith('movements-transfer');
    });

    it('exports movements within date range', function (): void {
        $now = CarbonImmutable::now();

        // Movement within range
        InventoryMovement::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'type' => MovementType::Receipt->value,
            'from_location_id' => null,
            'to_location_id' => $this->location->id,
            'quantity' => 100,
            'occurred_at' => $now->subDays(5),
        ]);

        // Movement outside range
        InventoryMovement::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'type' => MovementType::Receipt->value,
            'from_location_id' => null,
            'to_location_id' => $this->location->id,
            'quantity' => 50,
            'occurred_at' => $now->subMonths(3),
        ]);

        $export = new MovementExport(
            startDate: $now->subMonth(),
            endDate: $now,
        );

        $rows = iterator_to_array($export->getRows());

        expect($rows)->toHaveCount(1);
        expect($rows[0][6])->toBe(100); // Quantity column
    });

    it('filters by movement type', function (): void {
        InventoryMovement::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'type' => MovementType::Receipt->value,
            'from_location_id' => null,
            'to_location_id' => $this->location->id,
            'quantity' => 100,
            'occurred_at' => CarbonImmutable::now(),
        ]);

        $location2 = InventoryLocation::factory()->create(['name' => 'Location 2']);
        InventoryMovement::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'type' => MovementType::Transfer->value,
            'from_location_id' => $this->location->id,
            'to_location_id' => $location2->id,
            'quantity' => 25,
            'occurred_at' => CarbonImmutable::now(),
        ]);

        $export = new MovementExport(
            movementType: MovementType::Transfer,
        );

        $rows = iterator_to_array($export->getRows());

        expect($rows)->toHaveCount(1);
        expect($rows[0][1])->toBe(MovementType::Transfer->value);
    });

    it('filters by location', function (): void {
        $location2 = InventoryLocation::factory()->create(['name' => 'Location 2']);

        InventoryMovement::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'type' => MovementType::Receipt->value,
            'from_location_id' => null,
            'to_location_id' => $this->location->id,
            'quantity' => 100,
            'occurred_at' => CarbonImmutable::now(),
        ]);

        InventoryMovement::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'type' => MovementType::Receipt->value,
            'from_location_id' => null,
            'to_location_id' => $location2->id,
            'quantity' => 50,
            'occurred_at' => CarbonImmutable::now(),
        ]);

        $export = new MovementExport(
            locationId: $this->location->id,
        );

        $rows = iterator_to_array($export->getRows());

        expect($rows)->toHaveCount(1);
        expect($rows[0][5])->toBe($this->location->name); // To Location column
    });

    it('handles null locations gracefully', function (): void {
        InventoryMovement::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'type' => MovementType::Adjustment->value,
            'from_location_id' => null,
            'to_location_id' => null,
            'quantity' => 100,
            'occurred_at' => CarbonImmutable::now(),
        ]);

        $export = new MovementExport();
        $rows = iterator_to_array($export->getRows());

        expect($rows)->toHaveCount(1);
        expect($rows[0][4])->toBe('-'); // From Location
        expect($rows[0][5])->toBe('-'); // To Location
    });

    it('uses default date range when not specified', function (): void {
        $export = new MovementExport();

        // Create a movement now - should be included
        InventoryMovement::factory()->create([
            'inventoryable_type' => $this->item->getMorphClass(),
            'inventoryable_id' => $this->item->getKey(),
            'type' => MovementType::Receipt->value,
            'to_location_id' => $this->location->id,
            'quantity' => 50,
            'occurred_at' => CarbonImmutable::now(),
        ]);

        $rows = iterator_to_array($export->getRows());
        expect($rows)->toHaveCount(1);
    });
});
