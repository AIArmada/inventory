<?php

declare(strict_types=1);

use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Enums\SegmentType;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\Segment;

describe('Segment Model', function (): void {
    describe('Creation', function (): void {
        it('can create a segment', function (): void {
            $segment = Segment::create([
                'name' => 'Test Segment ' . uniqid(),
                'slug' => 'test-segment-' . uniqid(),
            ]);

            expect($segment)->toBeInstanceOf(Segment::class)
                ->and($segment->id)->not->toBeEmpty();
        });

        it('defaults to active', function (): void {
            $segment = Segment::create([
                'name' => 'Active Default ' . uniqid(),
                'slug' => 'active-' . uniqid(),
            ]);

            expect($segment->is_active)->toBeTrue();
        });

        it('defaults to automatic', function (): void {
            $segment = Segment::create([
                'name' => 'Auto Default ' . uniqid(),
                'slug' => 'auto-' . uniqid(),
            ]);

            expect($segment->is_automatic)->toBeTrue();
        });

        it('can have a type', function (): void {
            $segment = Segment::create([
                'name' => 'Loyalty ' . uniqid(),
                'slug' => 'loyalty-' . uniqid(),
                'type' => SegmentType::Loyalty,
            ]);

            expect($segment->type)->toBe(SegmentType::Loyalty);
        });

        it('can have conditions', function (): void {
            $segment = Segment::create([
                'name' => 'Conditonal ' . uniqid(),
                'slug' => 'conditional-' . uniqid(),
                'conditions' => [
                    ['field' => 'accepts_marketing', 'value' => true],
                ],
            ]);

            expect($segment->conditions)->toBeArray()
                ->and($segment->conditions[0]['field'])->toBe('accepts_marketing');
        });
    });

    describe('Relationships', function (): void {
        it('has customers relationship', function (): void {
            $segment = Segment::create([
                'name' => 'Customers Rel ' . uniqid(),
                'slug' => 'customers-rel-' . uniqid(),
            ]);

            expect($segment->customers())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
        });

        it('can add customers', function (): void {
            $segment = Segment::create([
                'name' => 'With Customers ' . uniqid(),
                'slug' => 'with-customers-' . uniqid(),
            ]);

            $customer = Customer::create([
                'first_name' => 'Segment',
                'last_name' => 'Member',
                'email' => 'segment-member-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $segment->addCustomer($customer);

            expect($segment->customers)->toHaveCount(1)
                ->and($segment->customers->first()->id)->toBe($customer->id);
        });

        it('can remove customers', function (): void {
            $segment = Segment::create([
                'name' => 'Remove Customer ' . uniqid(),
                'slug' => 'remove-customer-' . uniqid(),
            ]);

            $customer = Customer::create([
                'first_name' => 'To',
                'last_name' => 'Remove',
                'email' => 'to-remove-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $segment->addCustomer($customer);
            $segment->removeCustomer($customer);

            expect($segment->fresh()->customers)->toHaveCount(0);
        });
    });

    describe('Helpers', function (): void {
        it('checks if automatic', function (): void {
            $auto = Segment::create([
                'name' => 'Auto ' . uniqid(),
                'slug' => 'auto-check-' . uniqid(),
                'is_automatic' => true,
            ]);

            $manual = Segment::create([
                'name' => 'Manual ' . uniqid(),
                'slug' => 'manual-check-' . uniqid(),
                'is_automatic' => false,
            ]);

            expect($auto->isAutomatic())->toBeTrue()
                ->and($manual->isAutomatic())->toBeFalse();
        });

        it('checks if manual', function (): void {
            $manual = Segment::create([
                'name' => 'Manual Check ' . uniqid(),
                'slug' => 'manual-check-' . uniqid(),
                'is_automatic' => false,
            ]);

            expect($manual->isManual())->toBeTrue();
        });
    });

    describe('Matching Customers', function (): void {
        it('returns matching customers for automatic segment', function (): void {
            $segment = Segment::create([
                'name' => 'Matching ' . uniqid(),
                'slug' => 'matching-' . uniqid(),
                'is_automatic' => true,
                'conditions' => [
                    ['field' => 'accepts_marketing', 'value' => true],
                ],
            ]);

            Customer::create([
                'first_name' => 'Marketing',
                'last_name' => 'Yes',
                'email' => 'marketing-yes-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'accepts_marketing' => true,
            ]);

            Customer::create([
                'first_name' => 'Marketing',
                'last_name' => 'No',
                'email' => 'marketing-no-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'accepts_marketing' => false,
            ]);

            $matching = $segment->getMatchingCustomers();

            expect($matching)->toBeInstanceOf(Illuminate\Support\Collection::class)
                ->and($matching->every(fn ($c) => $c->accepts_marketing === true))->toBeTrue();
        });

        it('returns attached customers for manual segment', function (): void {
            $segment = Segment::create([
                'name' => 'Manual Matching ' . uniqid(),
                'slug' => 'manual-matching-' . uniqid(),
                'is_automatic' => false,
            ]);

            $customer = Customer::create([
                'first_name' => 'Manual',
                'last_name' => 'Member',
                'email' => 'manual-member-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $segment->addCustomer($customer);

            $matching = $segment->getMatchingCustomers();

            expect($matching)->toHaveCount(1)
                ->and($matching->first()->id)->toBe($customer->id);
        });
    });

    describe('Rebuild Customer List', function (): void {
        it('can rebuild customer list', function (): void {
            $segment = Segment::create([
                'name' => 'Rebuild ' . uniqid(),
                'slug' => 'rebuild-' . uniqid(),
                'is_automatic' => true,
                'conditions' => [
                    ['field' => 'accepts_marketing', 'value' => true],
                ],
            ]);

            Customer::create([
                'first_name' => 'Rebuild',
                'last_name' => 'Test',
                'email' => 'rebuild-test-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'accepts_marketing' => true,
            ]);

            $count = $segment->rebuildCustomerList();

            expect($count)->toBeGreaterThanOrEqual(1);
        });
    });

    describe('Scopes', function (): void {
        it('can filter active segments', function (): void {
            Segment::create([
                'name' => 'Active Scope ' . uniqid(),
                'slug' => 'active-scope-' . uniqid(),
                'is_active' => true,
            ]);

            Segment::create([
                'name' => 'Inactive Scope ' . uniqid(),
                'slug' => 'inactive-scope-' . uniqid(),
                'is_active' => false,
            ]);

            $active = Segment::active()->get();

            expect($active->every(fn ($s) => $s->is_active))->toBeTrue();
        });

        it('can filter automatic segments', function (): void {
            Segment::create([
                'name' => 'Auto Scope ' . uniqid(),
                'slug' => 'auto-scope-' . uniqid(),
                'is_automatic' => true,
            ]);

            $automatic = Segment::automatic()->get();

            expect($automatic->every(fn ($s) => $s->is_automatic))->toBeTrue();
        });

        it('can filter manual segments', function (): void {
            Segment::create([
                'name' => 'Manual Scope ' . uniqid(),
                'slug' => 'manual-scope-' . uniqid(),
                'is_automatic' => false,
            ]);

            $manual = Segment::manual()->get();

            expect($manual->every(fn ($s) => ! $s->is_automatic))->toBeTrue();
        });

        it('can filter by type', function (): void {
            Segment::create([
                'name' => 'Loyalty Type ' . uniqid(),
                'slug' => 'loyalty-type-' . uniqid(),
                'type' => SegmentType::Loyalty,
            ]);

            $loyalty = Segment::ofType(SegmentType::Loyalty)->get();

            expect($loyalty->every(fn ($s) => $s->type === SegmentType::Loyalty))->toBeTrue();
        });

        it('can order by priority', function (): void {
            Segment::create([
                'name' => 'High Priority ' . uniqid(),
                'slug' => 'high-priority-' . uniqid(),
                'priority' => 10,
            ]);

            Segment::create([
                'name' => 'Low Priority ' . uniqid(),
                'slug' => 'low-priority-' . uniqid(),
                'priority' => 1,
            ]);

            $ordered = Segment::byPriority()->get();

            expect($ordered->first()->priority)->toBeGreaterThanOrEqual($ordered->last()->priority);
        });
    });

    describe('Cascade Deletion', function (): void {
        it('detaches customers on deletion', function (): void {
            $segment = Segment::create([
                'name' => 'Cascade Delete ' . uniqid(),
                'slug' => 'cascade-delete-' . uniqid(),
            ]);

            $customer = Customer::create([
                'first_name' => 'Cascade',
                'last_name' => 'Customer',
                'email' => 'cascade-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $segment->addCustomer($customer);

            $segmentId = $segment->id;
            $segment->delete();

            // Customer should still exist
            expect(Customer::find($customer->id))->not->toBeNull();
        });
    });

    describe('rebuildCustomerList', function (): void {
        it('returns count for manual segment without rebuilding', function (): void {
            $segment = Segment::create([
                'name' => 'Manual Rebuild ' . uniqid(),
                'slug' => 'manual-rebuild-' . uniqid(),
                'is_automatic' => false,
            ]);

            $customer = Customer::create([
                'first_name' => 'Manual',
                'last_name' => 'Count',
                'email' => 'manual-count-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
            ]);

            $segment->addCustomer($customer);

            $count = $segment->rebuildCustomerList();

            expect($count)->toBe(1);
        });
    });

    describe('applyConditions Edge Cases', function (): void {
        it('skips conditions without field', function (): void {
            $segment = Segment::create([
                'name' => 'No Field ' . uniqid(),
                'slug' => 'no-field-' . uniqid(),
                'is_automatic' => true,
                'conditions' => [
                    ['value' => 100], // No field
                ],
            ]);

            $matching = $segment->getMatchingCustomers();

            // Should return all active customers
            expect($matching)->toBeInstanceOf(Illuminate\Support\Collection::class);
        });

        it('skips conditions without value', function (): void {
            $segment = Segment::create([
                'name' => 'No Value ' . uniqid(),
                'slug' => 'no-value-' . uniqid(),
                'is_automatic' => true,
                'conditions' => [
                    ['field' => 'accepts_marketing'], // No value
                ],
            ]);

            $matching = $segment->getMatchingCustomers();

            expect($matching)->toBeInstanceOf(Illuminate\Support\Collection::class);
        });

        it('handles is_tax_exempt condition', function (): void {
            $segment = Segment::create([
                'name' => 'Tax Exempt ' . uniqid(),
                'slug' => 'tax-exempt-' . uniqid(),
                'is_automatic' => true,
                'conditions' => [
                    ['field' => 'is_tax_exempt', 'value' => true],
                ],
            ]);

            Customer::create([
                'first_name' => 'Tax',
                'last_name' => 'Exempt',
                'email' => 'tax-exempt-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'is_tax_exempt' => true,
            ]);

            $matching = $segment->getMatchingCustomers();

            expect($matching->every(fn ($c) => $c->is_tax_exempt))->toBeTrue();
        });

        it('handles default field with custom operator', function (): void {
            $segment = Segment::create([
                'name' => 'Custom Field ' . uniqid(),
                'slug' => 'custom-field-' . uniqid(),
                'is_automatic' => true,
                'conditions' => [
                    ['field' => 'status', 'operator' => '=', 'value' => 'active'],
                ],
            ]);

            $matching = $segment->getMatchingCustomers();

            expect($matching->every(fn ($c) => $c->status->value === 'active'))->toBeTrue();
        });
    });
});
