<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\ReorderSuggestionStatus;
use AIArmada\Inventory\Enums\ReorderUrgency;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryReorderSuggestion;
use AIArmada\Inventory\Models\InventorySupplierLeadtime;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    $this->item = InventoryItem::create(['name' => 'Reorder Product']);
    $this->location = InventoryLocation::factory()->create();
});

describe('InventoryReorderSuggestion', function (): void {
    describe('relationships', function (): void {
        it('has inventoryable morph to relation', function (): void {
            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Pending,
                'current_stock' => 10,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::Normal,
                'trigger_reason' => 'Below reorder point',
            ]);

            expect($suggestion->inventoryable)->not->toBeNull();
            expect($suggestion->inventoryable->id)->toBe($this->item->id);
        });

        it('belongs to location', function (): void {
            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'location_id' => $this->location->id,
                'status' => ReorderSuggestionStatus::Pending,
                'current_stock' => 10,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::Normal,
                'trigger_reason' => 'Below reorder point',
            ]);

            expect($suggestion->location)->not->toBeNull();
            expect($suggestion->location->id)->toBe($this->location->id);
        });

        it('belongs to supplier leadtime', function (): void {
            $supplierLeadtime = InventorySupplierLeadtime::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'supplier_name' => 'Test Supplier',
                'lead_time_days' => 7,
                'lead_time_variance_days' => 2,
                'minimum_order_quantity' => 10,
                'order_multiple' => 1,
                'currency' => 'USD',
                'is_primary' => true,
                'is_active' => true,
            ]);

            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'supplier_leadtime_id' => $supplierLeadtime->id,
                'status' => ReorderSuggestionStatus::Pending,
                'current_stock' => 10,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::Normal,
                'trigger_reason' => 'Below reorder point',
            ]);

            expect($suggestion->supplierLeadtime)->not->toBeNull();
            expect($suggestion->supplierLeadtime->id)->toBe($supplierLeadtime->id);
        });
    });

    describe('scopes', function (): void {
        beforeEach(function (): void {
            InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Pending,
                'current_stock' => 5,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::Critical,
                'trigger_reason' => 'Critical stock level',
            ]);

            InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Approved,
                'current_stock' => 15,
                'reorder_point' => 20,
                'suggested_quantity' => 30,
                'urgency' => ReorderUrgency::High,
                'trigger_reason' => 'Below reorder point',
            ]);

            InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Ordered,
                'current_stock' => 10,
                'reorder_point' => 20,
                'suggested_quantity' => 40,
                'urgency' => ReorderUrgency::Normal,
                'trigger_reason' => 'Scheduled reorder',
            ]);

            InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Rejected,
                'current_stock' => 25,
                'reorder_point' => 20,
                'suggested_quantity' => 20,
                'urgency' => ReorderUrgency::Low,
                'trigger_reason' => 'EOQ suggestion',
            ]);
        });

        it('filters by model', function (): void {
            $otherItem = InventoryItem::create(['name' => 'Other Product']);
            InventoryReorderSuggestion::create([
                'inventoryable_type' => $otherItem->getMorphClass(),
                'inventoryable_id' => $otherItem->getKey(),
                'status' => ReorderSuggestionStatus::Pending,
                'current_stock' => 5,
                'reorder_point' => 10,
                'suggested_quantity' => 20,
                'urgency' => ReorderUrgency::Normal,
                'trigger_reason' => 'Test',
            ]);

            $forItem = InventoryReorderSuggestion::forModel($this->item)->get();

            expect($forItem)->toHaveCount(4);
        });

        it('filters pending suggestions', function (): void {
            $pending = InventoryReorderSuggestion::pending()->get();

            expect($pending)->toHaveCount(1);
            expect($pending->first()->status)->toBe(ReorderSuggestionStatus::Pending);
        });

        it('filters actionable suggestions', function (): void {
            $actionable = InventoryReorderSuggestion::actionable()->get();

            expect($actionable)->toHaveCount(2);
        });

        it('orders by urgency', function (): void {
            $ordered = InventoryReorderSuggestion::byUrgency()->get();

            expect($ordered->first()->urgency)->toBe(ReorderUrgency::Critical);
            expect($ordered->last()->urgency)->toBe(ReorderUrgency::Low);
        });

        it('filters critical suggestions', function (): void {
            $critical = InventoryReorderSuggestion::critical()->get();

            expect($critical)->toHaveCount(1);
            expect($critical->first()->urgency)->toBe(ReorderUrgency::Critical);
        });
    });

    describe('isActionable', function (): void {
        it('returns true for pending status', function (): void {
            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Pending,
                'current_stock' => 10,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::Normal,
                'trigger_reason' => 'Test',
            ]);

            expect($suggestion->isActionable())->toBeTrue();
        });

        it('returns true for approved status', function (): void {
            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Approved,
                'current_stock' => 10,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::Normal,
                'trigger_reason' => 'Test',
            ]);

            expect($suggestion->isActionable())->toBeTrue();
        });

        it('returns false for ordered status', function (): void {
            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Ordered,
                'current_stock' => 10,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::Normal,
                'trigger_reason' => 'Test',
            ]);

            expect($suggestion->isActionable())->toBeFalse();
        });
    });

    describe('daysUntilStockout', function (): void {
        it('returns days until expected stockout', function (): void {
            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Pending,
                'current_stock' => 10,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::High,
                'trigger_reason' => 'Test',
                'expected_stockout_date' => now()->addDays(5),
            ]);

            // Due to date boundary issues, the result may be 4 or 5 depending on time of day
            expect($suggestion->daysUntilStockout())->toBeGreaterThanOrEqual(4);
            expect($suggestion->daysUntilStockout())->toBeLessThanOrEqual(5);
        });

        it('returns negative days when past stockout date', function (): void {
            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Pending,
                'current_stock' => 0,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::Critical,
                'trigger_reason' => 'Test',
                'expected_stockout_date' => now()->subDays(3),
            ]);

            expect($suggestion->daysUntilStockout())->toBe(-3);
        });

        it('returns null when no stockout date', function (): void {
            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Pending,
                'current_stock' => 10,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::Normal,
                'trigger_reason' => 'Test',
                'expected_stockout_date' => null,
            ]);

            expect($suggestion->daysUntilStockout())->toBeNull();
        });
    });

    describe('approve', function (): void {
        it('approves pending suggestion', function (): void {
            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Pending,
                'current_stock' => 10,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::Normal,
                'trigger_reason' => 'Test',
            ]);

            $result = $suggestion->approve('admin-user');

            expect($result)->toBeTrue();
            expect($suggestion->fresh()->status)->toBe(ReorderSuggestionStatus::Approved);
            expect($suggestion->fresh()->approved_by)->toBe('admin-user');
            expect($suggestion->fresh()->approved_at)->not->toBeNull();
        });

        it('does not approve non-pending suggestion', function (): void {
            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Approved,
                'current_stock' => 10,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::Normal,
                'trigger_reason' => 'Test',
            ]);

            $result = $suggestion->approve('admin-user');

            expect($result)->toBeFalse();
        });
    });

    describe('reject', function (): void {
        it('rejects pending suggestion', function (): void {
            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Pending,
                'current_stock' => 10,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::Normal,
                'trigger_reason' => 'Test',
            ]);

            $result = $suggestion->reject('Stock manually adjusted');

            expect($result)->toBeTrue();
            expect($suggestion->fresh()->status)->toBe(ReorderSuggestionStatus::Rejected);
            expect($suggestion->fresh()->metadata['rejection_reason'])->toBe('Stock manually adjusted');
        });

        it('rejects approved suggestion', function (): void {
            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Approved,
                'current_stock' => 10,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::Normal,
                'trigger_reason' => 'Test',
            ]);

            $result = $suggestion->reject();

            expect($result)->toBeTrue();
            expect($suggestion->fresh()->status)->toBe(ReorderSuggestionStatus::Rejected);
        });

        it('does not reject non-actionable suggestion', function (): void {
            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Ordered,
                'current_stock' => 10,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::Normal,
                'trigger_reason' => 'Test',
            ]);

            $result = $suggestion->reject('Too late');

            expect($result)->toBeFalse();
        });
    });

    describe('markOrdered', function (): void {
        it('marks approved suggestion as ordered', function (): void {
            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Approved,
                'current_stock' => 10,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::Normal,
                'trigger_reason' => 'Test',
            ]);

            $result = $suggestion->markOrdered('PO-12345');

            expect($result)->toBeTrue();
            expect($suggestion->fresh()->status)->toBe(ReorderSuggestionStatus::Ordered);
            expect($suggestion->fresh()->order_id)->toBe('PO-12345');
            expect($suggestion->fresh()->ordered_at)->not->toBeNull();
        });

        it('does not mark non-approved suggestion as ordered', function (): void {
            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Pending,
                'current_stock' => 10,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::Normal,
                'trigger_reason' => 'Test',
            ]);

            $result = $suggestion->markOrdered('PO-12345');

            expect($result)->toBeFalse();
        });
    });

    describe('markReceived', function (): void {
        it('marks ordered suggestion as received', function (): void {
            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Ordered,
                'current_stock' => 10,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::Normal,
                'trigger_reason' => 'Test',
            ]);

            $result = $suggestion->markReceived();

            expect($result)->toBeTrue();
            expect($suggestion->fresh()->status)->toBe(ReorderSuggestionStatus::Received);
        });

        it('does not mark non-ordered suggestion as received', function (): void {
            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Approved,
                'current_stock' => 10,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::Normal,
                'trigger_reason' => 'Test',
            ]);

            $result = $suggestion->markReceived();

            expect($result)->toBeFalse();
        });
    });

    describe('expire', function (): void {
        it('expires actionable suggestion', function (): void {
            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Pending,
                'current_stock' => 10,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::Normal,
                'trigger_reason' => 'Test',
            ]);

            $result = $suggestion->expire();

            expect($result)->toBeTrue();
            expect($suggestion->fresh()->status)->toBe(ReorderSuggestionStatus::Expired);
        });

        it('does not expire non-actionable suggestion', function (): void {
            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Received,
                'current_stock' => 10,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::Normal,
                'trigger_reason' => 'Test',
            ]);

            $result = $suggestion->expire();

            expect($result)->toBeFalse();
        });
    });

    describe('casts', function (): void {
        it('casts status to enum', function (): void {
            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Pending->value,
                'current_stock' => 10,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::Normal->value,
                'trigger_reason' => 'Test',
            ]);

            expect($suggestion->status)->toBe(ReorderSuggestionStatus::Pending);
        });

        it('casts urgency to enum', function (): void {
            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Pending,
                'current_stock' => 10,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::Critical->value,
                'trigger_reason' => 'Test',
            ]);

            expect($suggestion->urgency)->toBe(ReorderUrgency::Critical);
        });

        it('casts datetime fields correctly', function (): void {
            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Ordered,
                'current_stock' => 10,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::Normal,
                'trigger_reason' => 'Test',
                'approved_at' => '2024-06-15 10:00:00',
                'ordered_at' => '2024-06-16 14:30:00',
            ]);

            expect($suggestion->approved_at)->toBeInstanceOf(Carbon::class);
            expect($suggestion->ordered_at)->toBeInstanceOf(Carbon::class);
        });

        it('casts calculation_details to array', function (): void {
            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Pending,
                'current_stock' => 10,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::Normal,
                'trigger_reason' => 'Test',
                'calculation_details' => ['eoq' => 100, 'safety_stock' => 15],
            ]);

            expect($suggestion->calculation_details)->toBeArray();
            expect($suggestion->calculation_details['eoq'])->toBe(100);
        });

        it('casts metadata to array', function (): void {
            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Pending,
                'current_stock' => 10,
                'reorder_point' => 20,
                'suggested_quantity' => 50,
                'urgency' => ReorderUrgency::Normal,
                'trigger_reason' => 'Test',
                'metadata' => ['source' => 'automatic', 'algorithm' => 'eoq'],
            ]);

            expect($suggestion->metadata)->toBeArray();
            expect($suggestion->metadata['source'])->toBe('automatic');
        });

        it('casts integer fields correctly', function (): void {
            $suggestion = InventoryReorderSuggestion::create([
                'inventoryable_type' => $this->item->getMorphClass(),
                'inventoryable_id' => $this->item->getKey(),
                'status' => ReorderSuggestionStatus::Pending,
                'current_stock' => '10',
                'reorder_point' => '20',
                'suggested_quantity' => '50',
                'economic_order_quantity' => '75',
                'average_daily_demand' => '5',
                'lead_time_days' => '7',
                'urgency' => ReorderUrgency::Normal,
                'trigger_reason' => 'Test',
            ]);

            expect($suggestion->current_stock)->toBeInt();
            expect($suggestion->reorder_point)->toBeInt();
            expect($suggestion->suggested_quantity)->toBeInt();
            expect($suggestion->economic_order_quantity)->toBeInt();
            expect($suggestion->average_daily_demand)->toBeInt();
            expect($suggestion->lead_time_days)->toBeInt();
        });
    });
});
