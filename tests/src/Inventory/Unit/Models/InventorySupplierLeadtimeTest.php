<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Models\InventorySupplierLeadtime;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->item = InventoryItem::create(['name' => 'Supplier Product']);
});

describe('InventorySupplierLeadtime', function (): void {
    describe('relationships', function (): void {
        it('has inventoryable morph to relation', function (): void {
            $leadtime = InventorySupplierLeadtime::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'supplier_name' => 'Test Supplier',
                'lead_time_days' => 7,
                'lead_time_variance_days' => 2,
                'minimum_order_quantity' => 10,
                'order_multiple' => 5,
                'currency' => 'USD',
                'is_primary' => false,
                'is_active' => true,
            ]);

            expect($leadtime->inventoryable)->not->toBeNull();
            expect($leadtime->inventoryable->id)->toBe($this->item->id);
        });
    });

    describe('scopes', function (): void {
        beforeEach(function (): void {
            InventorySupplierLeadtime::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'supplier_name' => 'Primary Supplier',
                'lead_time_days' => 5,
                'lead_time_variance_days' => 1,
                'minimum_order_quantity' => 10,
                'order_multiple' => 1,
                'unit_cost_minor' => 1000,
                'currency' => 'USD',
                'is_primary' => true,
                'is_active' => true,
            ]);

            InventorySupplierLeadtime::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'supplier_name' => 'Secondary Supplier',
                'lead_time_days' => 10,
                'lead_time_variance_days' => 3,
                'minimum_order_quantity' => 20,
                'order_multiple' => 10,
                'unit_cost_minor' => 800,
                'currency' => 'USD',
                'is_primary' => false,
                'is_active' => true,
            ]);

            InventorySupplierLeadtime::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'supplier_name' => 'Inactive Supplier',
                'lead_time_days' => 3,
                'lead_time_variance_days' => 0,
                'minimum_order_quantity' => 5,
                'order_multiple' => 1,
                'unit_cost_minor' => 1200,
                'currency' => 'USD',
                'is_primary' => false,
                'is_active' => false,
            ]);
        });

        it('filters by model', function (): void {
            $otherItem = InventoryItem::create(['name' => 'Other Product']);
            InventorySupplierLeadtime::create([
                'inventoryable_type' => $otherItem->getMorphClass(),
                'inventoryable_id' => $otherItem->getKey(),
                'supplier_name' => 'Other Supplier',
                'lead_time_days' => 7,
                'lead_time_variance_days' => 2,
                'minimum_order_quantity' => 10,
                'order_multiple' => 1,
                'currency' => 'USD',
                'is_primary' => true,
                'is_active' => true,
            ]);

            $forItem = InventorySupplierLeadtime::forModel($this->item)->get();

            expect($forItem)->toHaveCount(3);
        });

        it('filters active suppliers', function (): void {
            $active = InventorySupplierLeadtime::active()->get();

            expect($active)->toHaveCount(2);
        });

        it('filters primary suppliers', function (): void {
            $primary = InventorySupplierLeadtime::primary()->get();

            expect($primary)->toHaveCount(1);
            expect($primary->first()->supplier_name)->toBe('Primary Supplier');
        });

        it('orders by lead time ascending', function (): void {
            $ordered = InventorySupplierLeadtime::orderedByLeadTime()->get();

            expect($ordered->first()->lead_time_days)->toBe(3);
            expect($ordered->last()->lead_time_days)->toBe(10);
        });

        it('orders by cost ascending', function (): void {
            $ordered = InventorySupplierLeadtime::orderedByCost()->get();

            expect($ordered->first()->unit_cost_minor)->toBe(800);
            expect($ordered->last()->unit_cost_minor)->toBe(1200);
        });
    });

    describe('maxLeadTimeDays', function (): void {
        it('calculates max lead time including variance', function (): void {
            $leadtime = InventorySupplierLeadtime::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'supplier_name' => 'Test Supplier',
                'lead_time_days' => 7,
                'lead_time_variance_days' => 3,
                'minimum_order_quantity' => 10,
                'order_multiple' => 1,
                'currency' => 'USD',
                'is_primary' => false,
                'is_active' => true,
            ]);

            expect($leadtime->maxLeadTimeDays())->toBe(10);
        });
    });

    describe('minLeadTimeDays', function (): void {
        it('calculates min lead time (optimistic)', function (): void {
            $leadtime = InventorySupplierLeadtime::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'supplier_name' => 'Test Supplier',
                'lead_time_days' => 7,
                'lead_time_variance_days' => 2,
                'minimum_order_quantity' => 10,
                'order_multiple' => 1,
                'currency' => 'USD',
                'is_primary' => false,
                'is_active' => true,
            ]);

            expect($leadtime->minLeadTimeDays())->toBe(5);
        });

        it('returns at least 1 day', function (): void {
            $leadtime = InventorySupplierLeadtime::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'supplier_name' => 'Fast Supplier',
                'lead_time_days' => 1,
                'lead_time_variance_days' => 3,
                'minimum_order_quantity' => 10,
                'order_multiple' => 1,
                'currency' => 'USD',
                'is_primary' => false,
                'is_active' => true,
            ]);

            expect($leadtime->minLeadTimeDays())->toBe(1);
        });
    });

    describe('roundToOrderMultiple', function (): void {
        it('rounds up to order multiple', function (): void {
            $leadtime = InventorySupplierLeadtime::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'supplier_name' => 'Test Supplier',
                'lead_time_days' => 7,
                'lead_time_variance_days' => 0,
                'minimum_order_quantity' => 10,
                'order_multiple' => 5,
                'currency' => 'USD',
                'is_primary' => false,
                'is_active' => true,
            ]);

            expect($leadtime->roundToOrderMultiple(12))->toBe(15);
            expect($leadtime->roundToOrderMultiple(15))->toBe(15);
            expect($leadtime->roundToOrderMultiple(16))->toBe(20);
        });

        it('respects minimum order quantity', function (): void {
            $leadtime = InventorySupplierLeadtime::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'supplier_name' => 'Test Supplier',
                'lead_time_days' => 7,
                'lead_time_variance_days' => 0,
                'minimum_order_quantity' => 20,
                'order_multiple' => 5,
                'currency' => 'USD',
                'is_primary' => false,
                'is_active' => true,
            ]);

            expect($leadtime->roundToOrderMultiple(5))->toBe(20);
        });

        it('handles order multiple of 1', function (): void {
            $leadtime = InventorySupplierLeadtime::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'supplier_name' => 'Flexible Supplier',
                'lead_time_days' => 7,
                'lead_time_variance_days' => 0,
                'minimum_order_quantity' => 5,
                'order_multiple' => 1,
                'currency' => 'USD',
                'is_primary' => false,
                'is_active' => true,
            ]);

            expect($leadtime->roundToOrderMultiple(7))->toBe(7);
            expect($leadtime->roundToOrderMultiple(3))->toBe(5); // Minimum applies
        });
    });

    describe('calculateOrderCost', function (): void {
        it('calculates order cost correctly', function (): void {
            $leadtime = InventorySupplierLeadtime::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'supplier_name' => 'Test Supplier',
                'lead_time_days' => 7,
                'lead_time_variance_days' => 0,
                'minimum_order_quantity' => 10,
                'order_multiple' => 1,
                'unit_cost_minor' => 500, // $5.00
                'currency' => 'USD',
                'is_primary' => false,
                'is_active' => true,
            ]);

            expect($leadtime->calculateOrderCost(10))->toBe(5000); // 10 * 500
            expect($leadtime->calculateOrderCost(25))->toBe(12500); // 25 * 500
        });

        it('returns null when no unit cost', function (): void {
            $leadtime = InventorySupplierLeadtime::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'supplier_name' => 'No Cost Supplier',
                'lead_time_days' => 7,
                'lead_time_variance_days' => 0,
                'minimum_order_quantity' => 10,
                'order_multiple' => 1,
                'unit_cost_minor' => null,
                'currency' => 'USD',
                'is_primary' => false,
                'is_active' => true,
            ]);

            expect($leadtime->calculateOrderCost(10))->toBeNull();
        });

        it('applies order multiple when calculating cost', function (): void {
            $leadtime = InventorySupplierLeadtime::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'supplier_name' => 'Test Supplier',
                'lead_time_days' => 7,
                'lead_time_variance_days' => 0,
                'minimum_order_quantity' => 10,
                'order_multiple' => 10,
                'unit_cost_minor' => 100,
                'currency' => 'USD',
                'is_primary' => false,
                'is_active' => true,
            ]);

            // Ordering 12 rounds up to 20, cost = 20 * 100 = 2000
            expect($leadtime->calculateOrderCost(12))->toBe(2000);
        });
    });

    describe('markAsPrimary', function (): void {
        it('marks supplier as primary', function (): void {
            $primary = InventorySupplierLeadtime::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'supplier_name' => 'Primary Supplier',
                'lead_time_days' => 5,
                'lead_time_variance_days' => 1,
                'minimum_order_quantity' => 10,
                'order_multiple' => 1,
                'currency' => 'USD',
                'is_primary' => true,
                'is_active' => true,
            ]);

            $secondary = InventorySupplierLeadtime::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'supplier_name' => 'Secondary Supplier',
                'lead_time_days' => 10,
                'lead_time_variance_days' => 2,
                'minimum_order_quantity' => 20,
                'order_multiple' => 1,
                'currency' => 'USD',
                'is_primary' => false,
                'is_active' => true,
            ]);

            $result = $secondary->markAsPrimary();

            expect($result)->toBeTrue();
            expect($secondary->fresh()->is_primary)->toBeTrue();
            expect($primary->fresh()->is_primary)->toBeFalse();
        });
    });

    describe('recordOrder', function (): void {
        it('records order placement timestamp', function (): void {
            $leadtime = InventorySupplierLeadtime::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'supplier_name' => 'Test Supplier',
                'lead_time_days' => 7,
                'lead_time_variance_days' => 0,
                'minimum_order_quantity' => 10,
                'order_multiple' => 1,
                'currency' => 'USD',
                'is_primary' => false,
                'is_active' => true,
                'last_order_at' => null,
            ]);

            $result = $leadtime->recordOrder();

            expect($result)->toBeTrue();
            expect($leadtime->fresh()->last_order_at)->not->toBeNull();
        });
    });

    describe('recordReceipt', function (): void {
        it('records order receipt timestamp', function (): void {
            $leadtime = InventorySupplierLeadtime::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'supplier_name' => 'Test Supplier',
                'lead_time_days' => 7,
                'lead_time_variance_days' => 0,
                'minimum_order_quantity' => 10,
                'order_multiple' => 1,
                'currency' => 'USD',
                'is_primary' => false,
                'is_active' => true,
                'last_received_at' => null,
            ]);

            $result = $leadtime->recordReceipt();

            expect($result)->toBeTrue();
            expect($leadtime->fresh()->last_received_at)->not->toBeNull();
        });
    });

    describe('casts', function (): void {
        it('casts boolean fields correctly', function (): void {
            $leadtime = InventorySupplierLeadtime::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'supplier_name' => 'Test Supplier',
                'lead_time_days' => 7,
                'lead_time_variance_days' => 0,
                'minimum_order_quantity' => 10,
                'order_multiple' => 1,
                'currency' => 'USD',
                'is_primary' => 1,
                'is_active' => 0,
            ]);

            expect($leadtime->is_primary)->toBeBool();
            expect($leadtime->is_primary)->toBeTrue();
            expect($leadtime->is_active)->toBeBool();
            expect($leadtime->is_active)->toBeFalse();
        });

        it('casts datetime fields correctly', function (): void {
            $leadtime = InventorySupplierLeadtime::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'supplier_name' => 'Test Supplier',
                'lead_time_days' => 7,
                'lead_time_variance_days' => 0,
                'minimum_order_quantity' => 10,
                'order_multiple' => 1,
                'currency' => 'USD',
                'is_primary' => false,
                'is_active' => true,
                'last_order_at' => '2024-06-15 10:00:00',
                'last_received_at' => '2024-06-20 14:30:00',
            ]);

            expect($leadtime->last_order_at)->toBeInstanceOf(Carbon::class);
            expect($leadtime->last_received_at)->toBeInstanceOf(Carbon::class);
        });

        it('casts metadata to array', function (): void {
            $leadtime = InventorySupplierLeadtime::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'supplier_name' => 'Test Supplier',
                'lead_time_days' => 7,
                'lead_time_variance_days' => 0,
                'minimum_order_quantity' => 10,
                'order_multiple' => 1,
                'currency' => 'USD',
                'is_primary' => false,
                'is_active' => true,
                'metadata' => ['contact' => 'John Doe', 'email' => 'john@supplier.com'],
            ]);

            expect($leadtime->metadata)->toBeArray();
            expect($leadtime->metadata['contact'])->toBe('John Doe');
        });

        it('casts integer fields correctly', function (): void {
            $leadtime = InventorySupplierLeadtime::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'supplier_name' => 'Test Supplier',
                'lead_time_days' => '7',
                'lead_time_variance_days' => '2',
                'minimum_order_quantity' => '10',
                'order_multiple' => '5',
                'unit_cost_minor' => '1500',
                'currency' => 'USD',
                'is_primary' => false,
                'is_active' => true,
            ]);

            expect($leadtime->lead_time_days)->toBeInt();
            expect($leadtime->lead_time_variance_days)->toBeInt();
            expect($leadtime->minimum_order_quantity)->toBeInt();
            expect($leadtime->order_multiple)->toBeInt();
            expect($leadtime->unit_cost_minor)->toBeInt();
        });
    });
});
