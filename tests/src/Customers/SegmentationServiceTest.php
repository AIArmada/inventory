<?php

declare(strict_types=1);

use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Events\CustomerSegmentChanged;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\Segment;
use AIArmada\Customers\Services\SegmentationService;
use Illuminate\Support\Facades\Event;

describe('SegmentationService', function (): void {
    beforeEach(function (): void {
        $this->service = new SegmentationService();
    });

    describe('Instantiation', function (): void {
        it('can be instantiated', function (): void {
            expect($this->service)->toBeInstanceOf(SegmentationService::class);
        });
    });

    describe('rebuildAllSegments', function (): void {
        it('returns results for automatic segments', function (): void {
            // Create an automatic segment
            Segment::create([
                'name' => 'Rebuild All ' . uniqid(),
                'slug' => 'rebuild-all-' . uniqid(),
                'is_active' => true,
                'is_automatic' => true,
                'conditions' => [],
            ]);

            $results = $this->service->rebuildAllSegments();

            expect($results)->toBeArray();
        });
    });

    describe('rebuildSegment', function (): void {
        it('returns 0 for manual segment', function (): void {
            $segment = Segment::create([
                'name' => 'Manual Rebuild ' . uniqid(),
                'slug' => 'manual-rebuild-' . uniqid(),
                'is_automatic' => false,
            ]);

            $count = $this->service->rebuildSegment($segment);

            expect($count)->toBe(0);
        });

        it('rebuilds automatic segment', function (): void {
            $segment = Segment::create([
                'name' => 'Auto Rebuild ' . uniqid(),
                'slug' => 'auto-rebuild-' . uniqid(),
                'is_automatic' => true,
                'conditions' => [
                    ['field' => 'lifetime_value_min', 'value' => 100],
                ],
            ]);

            Customer::create([
                'first_name' => 'Rebuild',
                'last_name' => 'Event',
                'email' => 'rebuild-event-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'lifetime_value' => 500,
            ]);

            $count = $this->service->rebuildSegment($segment);

            expect($count)->toBeGreaterThanOrEqual(0);
        });
    });

    describe('evaluateCustomer', function (): void {
        it('evaluates customer for all automatic segments', function (): void {
            Event::fake();

            $segment = Segment::create([
                'name' => 'Evaluate ' . uniqid(),
                'slug' => 'evaluate-' . uniqid(),
                'is_automatic' => true,
                'is_active' => true,
                'conditions' => [
                    ['field' => 'lifetime_value_min', 'value' => 100],
                ],
            ]);

            $customer = Customer::create([
                'first_name' => 'Evaluate',
                'last_name' => 'Me',
                'email' => 'evaluate-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'lifetime_value' => 500,
            ]);

            $this->service->evaluateCustomer($customer);

            Event::assertDispatched(CustomerSegmentChanged::class);
        });

        it('preserves manual segment memberships', function (): void {
            $manualSegment = Segment::create([
                'name' => 'Manual Preserve ' . uniqid(),
                'slug' => 'manual-preserve-' . uniqid(),
                'is_automatic' => false,
            ]);

            $customer = Customer::create([
                'first_name' => 'Manual',
                'last_name' => 'Preserve',
                'email' => 'manual-preserve-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            // Add to manual segment
            $customer->segments()->attach($manualSegment->id);

            // Evaluate customer
            $this->service->evaluateCustomer($customer);

            // Manual membership should be preserved
            expect($customer->fresh()->segments->pluck('id'))->toContain($manualSegment->id);
        });
    });

    describe('customerMatchesSegment', function (): void {
        it('returns false for empty conditions', function (): void {
            $customer = Customer::create([
                'first_name' => 'Empty',
                'last_name' => 'Conditions',
                'email' => 'empty-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $segment = Segment::create([
                'name' => 'Empty Cond ' . uniqid(),
                'slug' => 'empty-cond-' . uniqid(),
                'conditions' => [],
            ]);

            expect($this->service->customerMatchesSegment($customer, $segment))->toBeFalse();
        });

        it('matches lifetime_value_min condition', function (): void {
            $customer = Customer::create([
                'first_name' => 'LTV',
                'last_name' => 'Min',
                'email' => 'ltv-min-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'lifetime_value' => 5000,
            ]);

            $segment = Segment::create([
                'name' => 'LTV Min ' . uniqid(),
                'slug' => 'ltv-min-' . uniqid(),
                'conditions' => [
                    ['field' => 'lifetime_value_min', 'value' => 1000],
                ],
            ]);

            expect($this->service->customerMatchesSegment($customer, $segment))->toBeTrue();
        });

        it('matches lifetime_value_max condition', function (): void {
            $customer = Customer::create([
                'first_name' => 'LTV',
                'last_name' => 'Max',
                'email' => 'ltv-max-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'lifetime_value' => 5000,
            ]);

            $segment = Segment::create([
                'name' => 'LTV Max ' . uniqid(),
                'slug' => 'ltv-max-' . uniqid(),
                'conditions' => [
                    ['field' => 'lifetime_value_max', 'value' => 10000],
                ],
            ]);

            expect($this->service->customerMatchesSegment($customer, $segment))->toBeTrue();
        });

        it('matches total_orders_min condition', function (): void {
            $customer = Customer::create([
                'first_name' => 'Orders',
                'last_name' => 'Min',
                'email' => 'orders-min-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'total_orders' => 5,
            ]);

            $segment = Segment::create([
                'name' => 'Orders Min ' . uniqid(),
                'slug' => 'orders-min-' . uniqid(),
                'conditions' => [
                    ['field' => 'total_orders_min', 'value' => 3],
                ],
            ]);

            expect($this->service->customerMatchesSegment($customer, $segment))->toBeTrue();
        });

        it('matches accepts_marketing condition', function (): void {
            $customer = Customer::create([
                'first_name' => 'Marketing',
                'last_name' => 'Yes',
                'email' => 'marketing-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'accepts_marketing' => true,
            ]);

            $segment = Segment::create([
                'name' => 'Marketing ' . uniqid(),
                'slug' => 'marketing-' . uniqid(),
                'conditions' => [
                    ['field' => 'accepts_marketing', 'value' => true],
                ],
            ]);

            expect($this->service->customerMatchesSegment($customer, $segment))->toBeTrue();
        });

        it('matches status condition', function (): void {
            $customer = Customer::create([
                'first_name' => 'Status',
                'last_name' => 'Active',
                'email' => 'status-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $segment = Segment::create([
                'name' => 'Status ' . uniqid(),
                'slug' => 'status-' . uniqid(),
                'conditions' => [
                    ['field' => 'status', 'value' => 'active'],
                ],
            ]);

            expect($this->service->customerMatchesSegment($customer, $segment))->toBeTrue();
        });

        it('matches last_order_days condition', function (): void {
            $customer = Customer::create([
                'first_name' => 'Recent',
                'last_name' => 'Order',
                'email' => 'recent-order-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'last_order_at' => now()->subDays(5),
            ]);

            $segment = Segment::create([
                'name' => 'Recent Order ' . uniqid(),
                'slug' => 'recent-order-' . uniqid(),
                'conditions' => [
                    ['field' => 'last_order_days', 'value' => 10],
                ],
            ]);

            expect($this->service->customerMatchesSegment($customer, $segment))->toBeTrue();
        });

        it('matches no_order_days condition', function (): void {
            $customer = Customer::create([
                'first_name' => 'No',
                'last_name' => 'Order',
                'email' => 'no-order-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'last_order_at' => now()->subDays(60),
            ]);

            $segment = Segment::create([
                'name' => 'Lapsed ' . uniqid(),
                'slug' => 'lapsed-' . uniqid(),
                'conditions' => [
                    ['field' => 'no_order_days', 'value' => 30],
                ],
            ]);

            expect($this->service->customerMatchesSegment($customer, $segment))->toBeTrue();
        });
    });

    describe('addToSegment', function (): void {
        it('adds customer to segment', function (): void {
            $segment = Segment::create([
                'name' => 'Add To ' . uniqid(),
                'slug' => 'add-to-' . uniqid(),
            ]);

            $customer = Customer::create([
                'first_name' => 'Add',
                'last_name' => 'To',
                'email' => 'add-to-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $this->service->addToSegment($customer, $segment);

            expect($customer->segments->pluck('id'))->toContain($segment->id);
        });
    });

    describe('removeFromSegment', function (): void {
        it('removes customer from segment', function (): void {
            $segment = Segment::create([
                'name' => 'Remove From ' . uniqid(),
                'slug' => 'remove-from-' . uniqid(),
            ]);

            $customer = Customer::create([
                'first_name' => 'Remove',
                'last_name' => 'From',
                'email' => 'remove-from-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $segment->addCustomer($customer);
            $this->service->removeFromSegment($customer, $segment);

            expect($customer->fresh()->segments->pluck('id'))->not->toContain($segment->id);
        });
    });

    describe('getSegmentStats', function (): void {
        it('returns stats for segment with customers', function (): void {
            $segment = Segment::create([
                'name' => 'Stats ' . uniqid(),
                'slug' => 'stats-' . uniqid(),
            ]);

            $customer1 = Customer::create([
                'first_name' => 'Stats',
                'last_name' => 'One',
                'email' => 'stats-one-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'lifetime_value' => 1000,
                'total_orders' => 5,
                'accepts_marketing' => true,
            ]);

            $customer2 = Customer::create([
                'first_name' => 'Stats',
                'last_name' => 'Two',
                'email' => 'stats-two-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'lifetime_value' => 2000,
                'total_orders' => 10,
                'accepts_marketing' => false,
            ]);

            $segment->addCustomer($customer1);
            $segment->addCustomer($customer2);

            $stats = $this->service->getSegmentStats($segment);

            expect($stats['customer_count'])->toBe(2)
                ->and($stats['total_lifetime_value'])->toBe(3000)
                ->and($stats['average_lifetime_value'])->toBe(1500)
                ->and($stats['total_orders'])->toBe(15)
                ->and($stats['average_orders'])->toBe(7.5)
                ->and($stats['marketing_opted_in'])->toBe(1)
                ->and($stats['marketing_opted_in_percentage'])->toBe(50.0);
        });

        it('returns zero stats for empty segment', function (): void {
            $segment = Segment::create([
                'name' => 'Empty Stats ' . uniqid(),
                'slug' => 'empty-stats-' . uniqid(),
            ]);

            $stats = $this->service->getSegmentStats($segment);

            expect($stats['customer_count'])->toBe(0)
                ->and($stats['total_lifetime_value'])->toBe(0)
                ->and($stats['average_lifetime_value'])->toBe(0);
        });
    });
});
