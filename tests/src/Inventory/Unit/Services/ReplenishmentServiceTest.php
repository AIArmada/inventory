<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\ReorderSuggestionStatus;
use AIArmada\Inventory\Enums\ReorderUrgency;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryReorderSuggestion;
use AIArmada\Inventory\Models\InventorySupplierLeadtime;
use AIArmada\Inventory\Services\DemandForecastService;
use AIArmada\Inventory\Services\ReplenishmentService;

beforeEach(function (): void {
    $this->location = InventoryLocation::factory()->create(['is_active' => true]);
    $this->product = InventoryItem::create(['name' => 'Test Product']);
    $this->demandForecastService = new DemandForecastService();
    $this->service = new ReplenishmentService($this->demandForecastService);
});

describe('generateSuggestions', function (): void {
    it('generates suggestions for items below reorder point', function (): void {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'reorder_point' => 20,
            'safety_stock' => 10,
            'alert_status' => AIArmada\Inventory\Enums\AlertStatus::LowStock->value,
        ]);

        $suggestions = $this->service->generateSuggestions();

        expect($suggestions)->toHaveCount(1);
        expect($suggestions->first()->inventoryable_id)->toBe($this->product->id);
    });

    it('filters by location when provided', function (): void {
        $otherLocation = InventoryLocation::factory()->create();

        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $otherLocation->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'reorder_point' => 20,
        ]);

        $suggestions = $this->service->generateSuggestions($this->location->id);

        expect($suggestions)->toHaveCount(0);
    });

    it('skips items with existing actionable suggestions', function (): void {
        InventoryLevel::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 5,
            'quantity_reserved' => 0,
            'reorder_point' => 20,
        ]);

        InventoryReorderSuggestion::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'status' => ReorderSuggestionStatus::Pending,
        ]);

        $suggestions = $this->service->generateSuggestions();

        expect($suggestions)->toHaveCount(0);
    });
});

describe('createSuggestion', function (): void {
    it('creates suggestion with calculated values', function (): void {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'safety_stock' => 20,
            'lead_time_days' => 7,
        ]);

        $suggestion = $this->service->createSuggestion($this->product, $level);

        expect($suggestion)->toBeInstanceOf(InventoryReorderSuggestion::class);
        expect($suggestion->status)->toBe(ReorderSuggestionStatus::Pending);
        expect($suggestion->current_stock)->toBe(10);
        expect($suggestion->lead_time_days)->toBe(7);
    });

    it('uses supplier lead time when available', function (): void {
        $supplier = InventorySupplierLeadtime::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'lead_time_days' => 14,
            'is_primary' => true,
            'is_active' => true,
        ]);

        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 0,
            'safety_stock' => 20,
            'lead_time_days' => 7,
        ]);

        $suggestion = $this->service->createSuggestion($this->product, $level);

        expect($suggestion->lead_time_days)->toBe(14);
        expect($suggestion->supplier_leadtime_id)->toBe($supplier->id);
    });

    it('calculates urgency based on days until stockout', function (): void {
        $level = InventoryLevel::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'location_id' => $this->location->id,
            'quantity_on_hand' => 1,
            'quantity_reserved' => 0,
            'safety_stock' => 50,
            'lead_time_days' => 7,
        ]);

        $suggestion = $this->service->createSuggestion($this->product, $level);

        expect($suggestion->urgency)->toBeInstanceOf(ReorderUrgency::class);
    });
});

describe('calculateEOQ', function (): void {
    it('calculates economic order quantity', function (): void {
        $supplier = InventorySupplierLeadtime::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'unit_cost_minor' => 10000,
            'minimum_order_quantity' => 10,
        ]);

        $eoq = $this->service->calculateEOQ($this->product, 10, $supplier);

        expect($eoq)->toBeGreaterThanOrEqual(10);
    });

    it('returns minimum order quantity when demand is zero', function (): void {
        $supplier = InventorySupplierLeadtime::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'minimum_order_quantity' => 25,
        ]);

        $eoq = $this->service->calculateEOQ($this->product, 0, $supplier);

        expect($eoq)->toBe(25);
    });

    it('works without supplier', function (): void {
        $eoq = $this->service->calculateEOQ($this->product, 10);

        expect($eoq)->toBeGreaterThanOrEqual(1);
    });
});

describe('getPrimarySupplier', function (): void {
    it('returns primary supplier when available', function (): void {
        $primary = InventorySupplierLeadtime::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'is_primary' => true,
            'is_active' => true,
        ]);

        InventorySupplierLeadtime::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'is_primary' => false,
            'is_active' => true,
        ]);

        $supplier = $this->service->getPrimarySupplier($this->product);

        expect($supplier->id)->toBe($primary->id);
    });

    it('returns fastest supplier when no primary', function (): void {
        $fast = InventorySupplierLeadtime::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'is_primary' => false,
            'is_active' => true,
            'lead_time_days' => 3,
        ]);

        InventorySupplierLeadtime::factory()->create([
            'inventoryable_type' => $this->product->getMorphClass(),
            'inventoryable_id' => $this->product->id,
            'is_primary' => false,
            'is_active' => true,
            'lead_time_days' => 7,
        ]);

        $supplier = $this->service->getPrimarySupplier($this->product);

        expect($supplier->id)->toBe($fast->id);
    });

    it('returns null when no suppliers', function (): void {
        $supplier = $this->service->getPrimarySupplier($this->product);

        expect($supplier)->toBeNull();
    });
});

describe('getPendingSuggestions', function (): void {
    it('returns pending suggestions ordered by urgency', function (): void {
        $product1 = InventoryItem::create(['name' => 'Product 1']);
        $product2 = InventoryItem::create(['name' => 'Product 2']);

        $urgent = InventoryReorderSuggestion::factory()
            ->forModel($product1)
            ->create([
                'status' => ReorderSuggestionStatus::Pending,
                'urgency' => ReorderUrgency::Critical,
            ]);

        $normal = InventoryReorderSuggestion::factory()
            ->forModel($product2)
            ->create([
                'status' => ReorderSuggestionStatus::Pending,
                'urgency' => ReorderUrgency::Normal,
            ]);

        $suggestions = $this->service->getPendingSuggestions();

        expect($suggestions)->toHaveCount(2);
        expect($suggestions->first()->id)->toBe($urgent->id);
    });
});

describe('getCriticalSuggestions', function (): void {
    it('returns only critical suggestions', function (): void {
        $product1 = InventoryItem::create(['name' => 'Product 1']);
        $product2 = InventoryItem::create(['name' => 'Product 2']);

        InventoryReorderSuggestion::factory()
            ->forModel($product1)
            ->create([
                'status' => ReorderSuggestionStatus::Pending,
                'urgency' => ReorderUrgency::Critical,
            ]);

        InventoryReorderSuggestion::factory()
            ->forModel($product2)
            ->create([
                'status' => ReorderSuggestionStatus::Pending,
                'urgency' => ReorderUrgency::Normal,
            ]);

        $suggestions = $this->service->getCriticalSuggestions();

        expect($suggestions)->toHaveCount(1);
        expect($suggestions->first()->urgency)->toBe(ReorderUrgency::Critical);
    });
});

describe('approve', function (): void {
    it('approves a suggestion', function (): void {
        $suggestion = InventoryReorderSuggestion::factory()->create([
            'status' => ReorderSuggestionStatus::Pending,
        ]);

        $result = $this->service->approve($suggestion, 'admin@example.com');

        expect($result)->toBeTrue();
        expect($suggestion->fresh()->status)->toBe(ReorderSuggestionStatus::Approved);
    });
});

describe('bulkApprove', function (): void {
    it('approves multiple suggestions', function (): void {
        $suggestions = InventoryReorderSuggestion::factory()->count(3)->create([
            'status' => ReorderSuggestionStatus::Pending,
        ]);

        $approved = $this->service->bulkApprove($suggestions, 'admin@example.com');

        expect($approved)->toBe(3);
    });
});

describe('expireOld', function (): void {
    it('expires old actionable suggestions', function (): void {
        InventoryReorderSuggestion::factory()->create([
            'status' => ReorderSuggestionStatus::Pending,
            'created_at' => now()->subDays(20),
        ]);

        InventoryReorderSuggestion::factory()->create([
            'status' => ReorderSuggestionStatus::Pending,
            'created_at' => now()->subDays(5),
        ]);

        $expired = $this->service->expireOld(14);

        expect($expired)->toBe(1);
    });
});

describe('getStatistics', function (): void {
    it('returns replenishment statistics', function (): void {
        // Get initial counts
        $initialStats = $this->service->getStatistics();

        InventoryReorderSuggestion::factory()->create([
            'status' => ReorderSuggestionStatus::Pending,
            'urgency' => ReorderUrgency::Critical,
        ]);

        InventoryReorderSuggestion::factory()->create([
            'status' => ReorderSuggestionStatus::Approved,
        ]);

        InventoryReorderSuggestion::factory()->create([
            'status' => ReorderSuggestionStatus::Ordered,
        ]);

        $stats = $this->service->getStatistics();

        expect($stats)->toHaveKey('pending');
        expect($stats)->toHaveKey('approved');
        expect($stats)->toHaveKey('ordered');
        expect($stats)->toHaveKey('critical');
        expect($stats)->toHaveKey('total_value');
        expect($stats['pending'])->toBeGreaterThanOrEqual($initialStats['pending'] + 1);
        expect($stats['approved'])->toBeGreaterThanOrEqual($initialStats['approved'] + 1);
        expect($stats['ordered'])->toBeGreaterThanOrEqual($initialStats['ordered'] + 1);
        expect($stats['critical'])->toBeGreaterThanOrEqual($initialStats['critical'] + 1);
    });
});
