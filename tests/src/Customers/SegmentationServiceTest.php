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
        $this->service = new SegmentationService;
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
                    ['field' => 'accepts_marketing', 'value' => true],
                ],
            ]);

            Customer::create([
                'first_name' => 'Rebuild',
                'last_name' => 'Event',
                'email' => 'rebuild-event-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'accepts_marketing' => true,
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
                    ['field' => 'accepts_marketing', 'value' => true],
                ],
            ]);

            $customer = Customer::create([
                'first_name' => 'Evaluate',
                'last_name' => 'Me',
                'email' => 'evaluate-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'accepts_marketing' => true,
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

        it('matches is_tax_exempt condition', function (): void {
            $customer = Customer::create([
                'first_name' => 'Tax',
                'last_name' => 'Exempt',
                'email' => 'tax-exempt-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'is_tax_exempt' => true,
            ]);

            $segment = Segment::create([
                'name' => 'Tax Exempt ' . uniqid(),
                'slug' => 'tax-exempt-' . uniqid(),
                'conditions' => [
                    ['field' => 'is_tax_exempt', 'value' => true],
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

        it('matches created_days_ago condition', function (): void {
            $customer = Customer::create([
                'first_name' => 'Old',
                'last_name' => 'Customer',
                'email' => 'old-customer-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'created_at' => now()->subDays(10),
            ]);

            $segment = Segment::create([
                'name' => 'Veteran ' . uniqid(),
                'slug' => 'veteran-' . uniqid(),
                'conditions' => [
                    ['field' => 'created_days_ago', 'value' => 7],
                ],
            ]);

            expect($this->service->customerMatchesSegment($customer, $segment))->toBeTrue();
        });

        it('matches last_login_days condition', function (): void {
            $customer = Customer::create([
                'first_name' => 'Recent',
                'last_name' => 'Login',
                'email' => 'recent-login-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'last_login_at' => now()->subDays(5),
            ]);

            $segment = Segment::create([
                'name' => 'Recent Login ' . uniqid(),
                'slug' => 'recent-login-' . uniqid(),
                'conditions' => [
                    ['field' => 'last_login_days', 'value' => 10],
                ],
            ]);

            expect($this->service->customerMatchesSegment($customer, $segment))->toBeTrue();
        });

        it('matches no_login_days condition', function (): void {
            $customer = Customer::create([
                'first_name' => 'Inactive',
                'last_name' => 'User',
                'email' => 'inactive-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'last_login_at' => now()->subDays(60),
            ]);

            $segment = Segment::create([
                'name' => 'Inactive ' . uniqid(),
                'slug' => 'inactive-' . uniqid(),
                'conditions' => [
                    ['field' => 'no_login_days', 'value' => 30],
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
                'accepts_marketing' => true,
            ]);

            $customer2 = Customer::create([
                'first_name' => 'Stats',
                'last_name' => 'Two',
                'email' => 'stats-two-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'accepts_marketing' => false,
            ]);

            $segment->addCustomer($customer1);
            $segment->addCustomer($customer2);

            $stats = $this->service->getSegmentStats($segment);

            expect($stats['customer_count'])->toBe(2)
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
                ->and($stats['marketing_opted_in'])->toBe(0);
        });
    });
});
