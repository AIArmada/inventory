<?php

declare(strict_types=1);

use AIArmada\Customers\Enums\CustomerStatus;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Models\Segment;
use AIArmada\Customers\Policies\CustomerPolicy;
use AIArmada\Customers\Policies\SegmentPolicy;

// Create a mock customer with isOwnedBy method
class MockCustomerWithOwnership extends Customer
{
    private bool $ownedByResult = true;

    public function setOwnedByResult(bool $result): void
    {
        $this->ownedByResult = $result;
    }

    public function isOwnedBy($user): bool
    {
        return $this->ownedByResult;
    }
}

// Create a mock segment with isOwnedBy method
class MockSegmentWithOwnership extends Segment
{
    private bool $ownedByResult = true;

    public function setOwnedByResult(bool $result): void
    {
        $this->ownedByResult = $result;
    }

    public function isOwnedBy($user): bool
    {
        return $this->ownedByResult;
    }
}

describe('CustomerPolicy', function (): void {
    beforeEach(function (): void {
        $this->policy = new CustomerPolicy();
        $this->user = new class
        {
            public int $id = 1;
        };
        $this->customer = Customer::create([
            'first_name' => 'Policy',
            'last_name' => 'Test',
            'email' => 'policy-' . uniqid() . '@example.com',
            'status' => CustomerStatus::Active,
            'user_id' => 1,
        ]);
    });

    describe('viewAny', function (): void {
        it('allows viewing any customers', function (): void {
            expect($this->policy->viewAny($this->user))->toBeTrue();
        });
    });

    describe('view', function (): void {
        it('allows viewing customer with matching user_id', function (): void {
            expect($this->policy->view($this->user, $this->customer))->toBeTrue();
        });

        it('allows viewing other customers by default', function (): void {
            $otherCustomer = Customer::create([
                'first_name' => 'Other',
                'last_name' => 'Customer',
                'email' => 'other-' . uniqid() . '@example.com',
                'status' => CustomerStatus::Active,
                'user_id' => 999,
            ]);

            expect($this->policy->view($this->user, $otherCustomer))->toBeTrue();
        });

        it('delegates to isOwnedBy when method exists', function (): void {
            $mockCustomer = new MockCustomerWithOwnership();
            $mockCustomer->setOwnedByResult(true);

            expect($this->policy->view($this->user, $mockCustomer))->toBeTrue();
        });

        it('returns false from isOwnedBy when not owner', function (): void {
            $mockCustomer = new MockCustomerWithOwnership();
            $mockCustomer->setOwnedByResult(false);

            expect($this->policy->view($this->user, $mockCustomer))->toBeFalse();
        });
    });

    describe('create', function (): void {
        it('allows creating customers', function (): void {
            expect($this->policy->create($this->user))->toBeTrue();
        });
    });

    describe('update', function (): void {
        it('allows updating customers', function (): void {
            expect($this->policy->update($this->user, $this->customer))->toBeTrue();
        });

        it('delegates to isOwnedBy when method exists', function (): void {
            $mockCustomer = new MockCustomerWithOwnership();
            $mockCustomer->setOwnedByResult(true);

            expect($this->policy->update($this->user, $mockCustomer))->toBeTrue();
        });

        it('returns false from isOwnedBy when not owner', function (): void {
            $mockCustomer = new MockCustomerWithOwnership();
            $mockCustomer->setOwnedByResult(false);

            expect($this->policy->update($this->user, $mockCustomer))->toBeFalse();
        });
    });

    describe('delete', function (): void {
        it('allows deleting customers', function (): void {
            expect($this->policy->delete($this->user, $this->customer))->toBeTrue();
        });

        it('delegates to isOwnedBy when method exists', function (): void {
            $mockCustomer = new MockCustomerWithOwnership();
            $mockCustomer->setOwnedByResult(true);

            expect($this->policy->delete($this->user, $mockCustomer))->toBeTrue();
        });

        it('returns false from isOwnedBy when not owner', function (): void {
            $mockCustomer = new MockCustomerWithOwnership();
            $mockCustomer->setOwnedByResult(false);

            expect($this->policy->delete($this->user, $mockCustomer))->toBeFalse();
        });
    });

    describe('restore', function (): void {
        it('delegates to update', function (): void {
            expect($this->policy->restore($this->user, $this->customer))->toBeTrue();
        });
    });

    describe('forceDelete', function (): void {
        it('delegates to delete', function (): void {
            expect($this->policy->forceDelete($this->user, $this->customer))->toBeTrue();
        });
    });

    describe('addCredit', function (): void {
        it('allows adding credit', function (): void {
            expect($this->policy->addCredit($this->user, $this->customer))->toBeTrue();
        });

        it('delegates to isOwnedBy when method exists', function (): void {
            $mockCustomer = new MockCustomerWithOwnership();
            $mockCustomer->setOwnedByResult(true);

            expect($this->policy->addCredit($this->user, $mockCustomer))->toBeTrue();
        });

        it('returns false from isOwnedBy when not owner', function (): void {
            $mockCustomer = new MockCustomerWithOwnership();
            $mockCustomer->setOwnedByResult(false);

            expect($this->policy->addCredit($this->user, $mockCustomer))->toBeFalse();
        });
    });

    describe('deductCredit', function (): void {
        it('allows deducting credit', function (): void {
            expect($this->policy->deductCredit($this->user, $this->customer))->toBeTrue();
        });

        it('delegates to isOwnedBy when method exists', function (): void {
            $mockCustomer = new MockCustomerWithOwnership();
            $mockCustomer->setOwnedByResult(true);

            expect($this->policy->deductCredit($this->user, $mockCustomer))->toBeTrue();
        });

        it('returns false from isOwnedBy when not owner', function (): void {
            $mockCustomer = new MockCustomerWithOwnership();
            $mockCustomer->setOwnedByResult(false);

            expect($this->policy->deductCredit($this->user, $mockCustomer))->toBeFalse();
        });
    });
});

describe('SegmentPolicy', function (): void {
    beforeEach(function (): void {
        $this->policy = new SegmentPolicy();
        $this->user = new class
        {
            public int $id = 1;
        };
        $this->segment = Segment::create([
            'name' => 'Policy Segment',
            'slug' => 'policy-segment-' . uniqid(),
            'is_active' => true,
            'is_automatic' => true,
        ]);
    });

    describe('viewAny', function (): void {
        it('allows viewing any segments', function (): void {
            expect($this->policy->viewAny($this->user))->toBeTrue();
        });
    });

    describe('view', function (): void {
        it('allows viewing segments', function (): void {
            expect($this->policy->view($this->user, $this->segment))->toBeTrue();
        });

        it('delegates to isOwnedBy when method exists', function (): void {
            $mockSegment = new MockSegmentWithOwnership();
            $mockSegment->setOwnedByResult(true);

            expect($this->policy->view($this->user, $mockSegment))->toBeTrue();
        });

        it('returns false from isOwnedBy when not owner', function (): void {
            $mockSegment = new MockSegmentWithOwnership();
            $mockSegment->setOwnedByResult(false);

            expect($this->policy->view($this->user, $mockSegment))->toBeFalse();
        });
    });

    describe('create', function (): void {
        it('allows creating segments', function (): void {
            expect($this->policy->create($this->user))->toBeTrue();
        });
    });

    describe('update', function (): void {
        it('allows updating segments', function (): void {
            expect($this->policy->update($this->user, $this->segment))->toBeTrue();
        });

        it('delegates to isOwnedBy when method exists', function (): void {
            $mockSegment = new MockSegmentWithOwnership();
            $mockSegment->setOwnedByResult(true);

            expect($this->policy->update($this->user, $mockSegment))->toBeTrue();
        });

        it('returns false from isOwnedBy when not owner', function (): void {
            $mockSegment = new MockSegmentWithOwnership();
            $mockSegment->setOwnedByResult(false);

            expect($this->policy->update($this->user, $mockSegment))->toBeFalse();
        });
    });

    describe('delete', function (): void {
        it('allows deleting segments', function (): void {
            expect($this->policy->delete($this->user, $this->segment))->toBeTrue();
        });

        it('delegates to isOwnedBy when method exists', function (): void {
            $mockSegment = new MockSegmentWithOwnership();
            $mockSegment->setOwnedByResult(true);

            expect($this->policy->delete($this->user, $mockSegment))->toBeTrue();
        });

        it('returns false from isOwnedBy when not owner', function (): void {
            $mockSegment = new MockSegmentWithOwnership();
            $mockSegment->setOwnedByResult(false);

            expect($this->policy->delete($this->user, $mockSegment))->toBeFalse();
        });
    });

    describe('restore', function (): void {
        it('delegates to update', function (): void {
            expect($this->policy->restore($this->user, $this->segment))->toBeTrue();
        });
    });

    describe('forceDelete', function (): void {
        it('delegates to delete', function (): void {
            expect($this->policy->forceDelete($this->user, $this->segment))->toBeTrue();
        });
    });

    describe('rebuild', function (): void {
        it('allows rebuilding automatic segments', function (): void {
            expect($this->policy->rebuild($this->user, $this->segment))->toBeTrue();
        });

        it('denies rebuilding manual segments', function (): void {
            $manualSegment = Segment::create([
                'name' => 'Manual',
                'slug' => 'manual-' . uniqid(),
                'is_automatic' => false,
            ]);

            expect($this->policy->rebuild($this->user, $manualSegment))->toBeFalse();
        });
    });
});
