<?php

declare(strict_types=1);

use AIArmada\Shipping\Models\ReturnAuthorization;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Models\ShippingZone;
use AIArmada\Shipping\Policies\ReturnAuthorizationPolicy;
use AIArmada\Shipping\Policies\ShipmentPolicy;
use AIArmada\Shipping\Policies\ShippingZonePolicy;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Helper: Create user with permission checking
function createUserWithPermissions(array $permissions): Authenticatable
{
    return new class($permissions) implements Authenticatable
    {
        public function __construct(private array $permissions) {}

        public function getAuthIdentifier(): mixed
        {
            return 'user-123';
        }

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return '';
        }

        public function getAuthPasswordName(): string
        {
            return '';
        }

        public function hasPermissionTo(string $permission): bool
        {
            return in_array($permission, $this->permissions, true);
        }
    };
}

// Helper: Create user without permission system (fallback to true)
function createUserWithoutPermissions(): Authenticatable
{
    return new class implements Authenticatable
    {
        public function getAuthIdentifier(): mixed
        {
            return 'user-123';
        }

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return '';
        }

        public function getAuthPasswordName(): string
        {
            return '';
        }
    };
}

// Helper: Create user with can() method (Laravel Gate fallback)
function createUserWithCan(array $abilities): Authenticatable
{
    return new class($abilities) implements Authenticatable
    {
        public function __construct(private array $abilities) {}

        public function getAuthIdentifier(): mixed
        {
            return 'user-456';
        }

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return '';
        }

        public function getAuthPasswordName(): string
        {
            return '';
        }

        public function can(string $ability): bool
        {
            return in_array($ability, $this->abilities, true);
        }
    };
}

// ============================================
// ShipmentPolicy Tests
// ============================================

describe('ShipmentPolicy', function (): void {
    beforeEach(function (): void {
        $this->policy = new ShipmentPolicy;
    });

    it('can be instantiated', function (): void {
        expect($this->policy)->toBeInstanceOf(ShipmentPolicy::class);
    });

    // viewAny tests
    it('allows viewAny when user has permission', function (): void {
        $user = createUserWithPermissions(['shipping.shipments.view']);
        expect($this->policy->viewAny($user))->toBeTrue();
    });

    it('denies viewAny when user lacks permission', function (): void {
        $user = createUserWithPermissions([]);
        expect($this->policy->viewAny($user))->toBeFalse();
    });

    it('denies viewAny when no permission system exists', function (): void {
        $user = createUserWithoutPermissions();
        expect($this->policy->viewAny($user))->toBeFalse();
    });

    it('uses can() method as fallback for viewAny', function (): void {
        $user = createUserWithCan(['shipping.shipments.view']);
        expect($this->policy->viewAny($user))->toBeTrue();
    });

    // view tests
    it('allows view when user has permission', function (): void {
        $user = createUserWithPermissions(['shipping.shipments.view']);
        $shipment = Mockery::mock(Shipment::class)->makePartial();
        expect($this->policy->view($user, $shipment))->toBeTrue();
    });

    it('allows view when record belongs to the resolved owner even without permission', function (): void {
        config(['shipping.features.owner.enabled' => true]);

        $user = createUserWithPermissions([]);

        $owner = Mockery::mock(\Illuminate\Database\Eloquent\Model::class);
        $owner->shouldReceive('getMorphClass')->andReturn('TestOwner');
        $owner->shouldReceive('getKey')->andReturn('owner-123');

        app()->instance(\AIArmada\CommerceSupport\Contracts\OwnerResolverInterface::class, new class($owner) implements \AIArmada\CommerceSupport\Contracts\OwnerResolverInterface
        {
            public function __construct(private readonly ?\Illuminate\Database\Eloquent\Model $owner) {}

            public function resolve(): ?\Illuminate\Database\Eloquent\Model
            {
                return $this->owner;
            }
        });

        $shipment = Mockery::mock(Shipment::class)->makePartial();
        $shipment->shouldReceive('belongsToOwner')->with($owner)->andReturnTrue();

        try {
            expect($this->policy->view($user, $shipment))->toBeTrue();
        } finally {
            app()->forgetInstance(\AIArmada\CommerceSupport\Contracts\OwnerResolverInterface::class);
            config(['shipping.features.owner.enabled' => false]);
        }
    });

    it('denies view when neither owner nor has permission', function (): void {
        $user = createUserWithPermissions([]);
        $shipment = Mockery::mock(Shipment::class)->makePartial();
        $shipment->shouldReceive('getAttribute')
            ->with('owner_type')
            ->andReturn(null);
        $shipment->shouldReceive('getAttribute')
            ->with('owner_id')
            ->andReturn(null);

        expect($this->policy->view($user, $shipment))->toBeFalse();
    });

    // create tests
    it('allows create when user has permission', function (): void {
        $user = createUserWithPermissions(['shipping.shipments.create']);
        expect($this->policy->create($user))->toBeTrue();
    });

    it('denies create when user lacks permission', function (): void {
        $user = createUserWithPermissions([]);
        expect($this->policy->create($user))->toBeFalse();
    });

    // update tests
    it('denies update for terminal shipment', function (): void {
        $user = createUserWithPermissions(['shipping.shipments.update']);
        $shipment = Mockery::mock(Shipment::class)->makePartial();
        $shipment->shouldReceive('isTerminal')->andReturn(true);

        expect($this->policy->update($user, $shipment))->toBeFalse();
    });

    it('allows update when user has permission and shipment is not terminal', function (): void {
        $user = createUserWithPermissions(['shipping.shipments.update']);
        $shipment = Mockery::mock(Shipment::class)->makePartial();
        $shipment->shouldReceive('isTerminal')->andReturn(false);

        expect($this->policy->update($user, $shipment))->toBeTrue();
    });

    it('denies update when shipment is not terminal but user lacks permission', function (): void {
        $user = createUserWithPermissions([]);
        $shipment = Mockery::mock(Shipment::class)->makePartial();
        $shipment->shouldReceive('isTerminal')->andReturn(false);

        expect($this->policy->update($user, $shipment))->toBeFalse();
    });

    // delete tests
    it('denies delete for non-cancellable shipment', function (): void {
        $user = createUserWithPermissions(['shipping.shipments.delete']);
        $shipment = Mockery::mock(Shipment::class)->makePartial();
        $shipment->shouldReceive('isCancellable')->andReturn(false);

        expect($this->policy->delete($user, $shipment))->toBeFalse();
    });

    it('allows delete when user has permission and shipment is cancellable', function (): void {
        $user = createUserWithPermissions(['shipping.shipments.delete']);
        $shipment = Mockery::mock(Shipment::class)->makePartial();
        $shipment->shouldReceive('isCancellable')->andReturn(true);

        expect($this->policy->delete($user, $shipment))->toBeTrue();
    });

    // ship tests
    it('denies ship for non-pending shipment', function (): void {
        $user = createUserWithPermissions(['shipping.shipments.ship']);
        $shipment = Mockery::mock(Shipment::class)->makePartial();
        $shipment->shouldReceive('isPending')->andReturn(false);

        expect($this->policy->ship($user, $shipment))->toBeFalse();
    });

    it('allows ship when user has permission and shipment is pending', function (): void {
        $user = createUserWithPermissions(['shipping.shipments.ship']);
        $shipment = Mockery::mock(Shipment::class)->makePartial();
        $shipment->shouldReceive('isPending')->andReturn(true);

        expect($this->policy->ship($user, $shipment))->toBeTrue();
    });

    // cancel tests
    it('denies cancel for non-cancellable shipment', function (): void {
        $user = createUserWithPermissions(['shipping.shipments.cancel']);
        $shipment = Mockery::mock(Shipment::class)->makePartial();
        $shipment->shouldReceive('isCancellable')->andReturn(false);

        expect($this->policy->cancel($user, $shipment))->toBeFalse();
    });

    it('allows cancel when user has permission and shipment is cancellable', function (): void {
        $user = createUserWithPermissions(['shipping.shipments.cancel']);
        $shipment = Mockery::mock(Shipment::class)->makePartial();
        $shipment->shouldReceive('isCancellable')->andReturn(true);

        expect($this->policy->cancel($user, $shipment))->toBeTrue();
    });

    // printLabel tests
    it('denies printLabel when no tracking number', function (): void {
        $user = createUserWithPermissions(['shipping.shipments.print-label']);
        $shipment = Mockery::mock(Shipment::class)->makePartial();
        $shipment->shouldReceive('getAttribute')
            ->with('tracking_number')
            ->andReturn(null);

        expect($this->policy->printLabel($user, $shipment))->toBeFalse();
    });

    it('allows printLabel when user has permission and tracking number exists', function (): void {
        $user = createUserWithPermissions(['shipping.shipments.print-label']);
        $shipment = Mockery::mock(Shipment::class)->makePartial();
        $shipment->shouldReceive('getAttribute')
            ->with('tracking_number')
            ->andReturn('TRACK123');

        expect($this->policy->printLabel($user, $shipment))->toBeTrue();
    });

    // syncTracking tests
    it('denies syncTracking when no tracking number', function (): void {
        $user = createUserWithPermissions(['shipping.shipments.sync-tracking']);
        $shipment = Mockery::mock(Shipment::class)->makePartial();
        $shipment->shouldReceive('getAttribute')
            ->with('tracking_number')
            ->andReturn(null);

        expect($this->policy->syncTracking($user, $shipment))->toBeFalse();
    });

    it('allows syncTracking when user has permission', function (): void {
        $user = createUserWithPermissions(['shipping.shipments.sync-tracking']);
        $shipment = Mockery::mock(Shipment::class)->makePartial();
        $shipment->shouldReceive('getAttribute')
            ->with('tracking_number')
            ->andReturn('TRACK123');

        expect($this->policy->syncTracking($user, $shipment))->toBeTrue();
    });
});

// ============================================
// ShippingZonePolicy Tests
// ============================================

describe('ShippingZonePolicy', function (): void {
    beforeEach(function (): void {
        $this->policy = new ShippingZonePolicy;
    });

    it('can be instantiated', function (): void {
        expect($this->policy)->toBeInstanceOf(ShippingZonePolicy::class);
    });

    it('allows viewAny when user has permission', function (): void {
        $user = createUserWithPermissions(['shipping.zones.view']);
        expect($this->policy->viewAny($user))->toBeTrue();
    });

    it('denies viewAny when user lacks permission', function (): void {
        $user = createUserWithPermissions([]);
        expect($this->policy->viewAny($user))->toBeFalse();
    });

    it('allows view when user has permission', function (): void {
        $user = createUserWithPermissions(['shipping.zones.view']);
        $zone = Mockery::mock(ShippingZone::class)->makePartial();
        expect($this->policy->view($user, $zone))->toBeTrue();
    });

    it('allows create when user has permission', function (): void {
        $user = createUserWithPermissions(['shipping.zones.create']);
        expect($this->policy->create($user))->toBeTrue();
    });

    it('denies create when user lacks permission', function (): void {
        $user = createUserWithPermissions([]);
        expect($this->policy->create($user))->toBeFalse();
    });

    it('allows update when user has permission', function (): void {
        $user = createUserWithPermissions(['shipping.zones.update']);
        $zone = Mockery::mock(ShippingZone::class)->makePartial();
        expect($this->policy->update($user, $zone))->toBeTrue();
    });

    it('denies delete when zone has active rates', function (): void {
        $user = createUserWithPermissions(['shipping.zones.delete']);

        $ratesRelation = Mockery::mock(HasMany::class);
        $ratesRelation->shouldReceive('exists')->andReturn(true);

        $zone = Mockery::mock(ShippingZone::class)->makePartial();
        $zone->shouldReceive('rates')->andReturn($ratesRelation);

        expect($this->policy->delete($user, $zone))->toBeFalse();
    });

    it('allows delete when zone has no rates and user has permission', function (): void {
        $user = createUserWithPermissions(['shipping.zones.delete']);

        $ratesRelation = Mockery::mock(HasMany::class);
        $ratesRelation->shouldReceive('exists')->andReturn(false);

        $zone = Mockery::mock(ShippingZone::class)->makePartial();
        $zone->shouldReceive('rates')->andReturn($ratesRelation);

        expect($this->policy->delete($user, $zone))->toBeTrue();
    });

    it('denies delete when zone has no rates but user lacks permission', function (): void {
        $user = createUserWithPermissions([]);

        $ratesRelation = Mockery::mock(HasMany::class);
        $ratesRelation->shouldReceive('exists')->andReturn(false);

        $zone = Mockery::mock(ShippingZone::class)->makePartial();
        $zone->shouldReceive('rates')->andReturn($ratesRelation);

        expect($this->policy->delete($user, $zone))->toBeFalse();
    });

    it('allows manageRates when user has permission', function (): void {
        $user = createUserWithPermissions(['shipping.zones.manage-rates']);
        $zone = Mockery::mock(ShippingZone::class)->makePartial();
        expect($this->policy->manageRates($user, $zone))->toBeTrue();
    });

    it('denies manageRates when user lacks permission', function (): void {
        $user = createUserWithPermissions([]);
        $zone = Mockery::mock(ShippingZone::class)->makePartial();
        expect($this->policy->manageRates($user, $zone))->toBeFalse();
    });
});

// ============================================
// ReturnAuthorizationPolicy Tests
// ============================================

describe('ReturnAuthorizationPolicy', function (): void {
    beforeEach(function (): void {
        $this->policy = new ReturnAuthorizationPolicy;
    });

    it('can be instantiated', function (): void {
        expect($this->policy)->toBeInstanceOf(ReturnAuthorizationPolicy::class);
    });

    // viewAny tests
    it('allows viewAny when user has permission', function (): void {
        $user = createUserWithPermissions(['shipping.returns.view']);
        expect($this->policy->viewAny($user))->toBeTrue();
    });

    it('denies viewAny when user lacks permission', function (): void {
        $user = createUserWithPermissions([]);
        expect($this->policy->viewAny($user))->toBeFalse();
    });

    // view tests
    it('allows view when user has permission', function (): void {
        $user = createUserWithPermissions(['shipping.returns.view']);
        $rma = Mockery::mock(ReturnAuthorization::class)->makePartial();
        expect($this->policy->view($user, $rma))->toBeTrue();
    });

    it('allows view when record belongs to the resolved owner even without permission', function (): void {
        config(['shipping.features.owner.enabled' => true]);

        $user = createUserWithPermissions([]);

        $owner = Mockery::mock(\Illuminate\Database\Eloquent\Model::class);
        $owner->shouldReceive('getMorphClass')->andReturn('TestOwner');
        $owner->shouldReceive('getKey')->andReturn('owner-123');

        app()->instance(\AIArmada\CommerceSupport\Contracts\OwnerResolverInterface::class, new class($owner) implements \AIArmada\CommerceSupport\Contracts\OwnerResolverInterface
        {
            public function __construct(private readonly ?\Illuminate\Database\Eloquent\Model $owner) {}

            public function resolve(): ?\Illuminate\Database\Eloquent\Model
            {
                return $this->owner;
            }
        });

        $rma = Mockery::mock(ReturnAuthorization::class)->makePartial();
        $rma->shouldReceive('belongsToOwner')->with($owner)->andReturnTrue();

        try {
            expect($this->policy->view($user, $rma))->toBeTrue();
        } finally {
            app()->forgetInstance(\AIArmada\CommerceSupport\Contracts\OwnerResolverInterface::class);
            config(['shipping.features.owner.enabled' => false]);
        }
    });

    it('denies view when neither owner nor has permission', function (): void {
        $user = createUserWithPermissions([]);
        $rma = Mockery::mock(ReturnAuthorization::class)->makePartial();
        $rma->shouldReceive('getAttribute')
            ->with('owner_type')
            ->andReturn(null);
        $rma->shouldReceive('getAttribute')
            ->with('owner_id')
            ->andReturn(null);

        expect($this->policy->view($user, $rma))->toBeFalse();
    });

    // create tests
    it('allows create when user has permission', function (): void {
        $user = createUserWithPermissions(['shipping.returns.create']);
        expect($this->policy->create($user))->toBeTrue();
    });

    // update tests
    it('denies update when RMA is completed', function (): void {
        $user = createUserWithPermissions(['shipping.returns.update']);
        $rma = Mockery::mock(ReturnAuthorization::class)->makePartial();
        $rma->shouldReceive('isCompleted')->andReturn(true);
        $rma->shouldReceive('isCancelled')->andReturn(false);

        expect($this->policy->update($user, $rma))->toBeFalse();
    });

    it('denies update when RMA is cancelled', function (): void {
        $user = createUserWithPermissions(['shipping.returns.update']);
        $rma = Mockery::mock(ReturnAuthorization::class)->makePartial();
        $rma->shouldReceive('isCompleted')->andReturn(false);
        $rma->shouldReceive('isCancelled')->andReturn(true);

        expect($this->policy->update($user, $rma))->toBeFalse();
    });

    it('allows update when RMA is active and user has permission', function (): void {
        $user = createUserWithPermissions(['shipping.returns.update']);
        $rma = Mockery::mock(ReturnAuthorization::class)->makePartial();
        $rma->shouldReceive('isCompleted')->andReturn(false);
        $rma->shouldReceive('isCancelled')->andReturn(false);

        expect($this->policy->update($user, $rma))->toBeTrue();
    });

    // delete tests
    it('denies delete when RMA is not pending', function (): void {
        $user = createUserWithPermissions(['shipping.returns.delete']);
        $rma = Mockery::mock(ReturnAuthorization::class)->makePartial();
        $rma->shouldReceive('isPending')->andReturn(false);

        expect($this->policy->delete($user, $rma))->toBeFalse();
    });

    it('allows delete when RMA is pending and user has permission', function (): void {
        $user = createUserWithPermissions(['shipping.returns.delete']);
        $rma = Mockery::mock(ReturnAuthorization::class)->makePartial();
        $rma->shouldReceive('isPending')->andReturn(true);

        expect($this->policy->delete($user, $rma))->toBeTrue();
    });

    // approve tests
    it('denies approve for non-pending RMA', function (): void {
        $user = createUserWithPermissions(['shipping.returns.approve']);
        $rma = Mockery::mock(ReturnAuthorization::class)->makePartial();
        $rma->shouldReceive('isPending')->andReturn(false);

        expect($this->policy->approve($user, $rma))->toBeFalse();
    });

    it('allows approve when user has permission and RMA is pending', function (): void {
        $user = createUserWithPermissions(['shipping.returns.approve']);
        $rma = Mockery::mock(ReturnAuthorization::class)->makePartial();
        $rma->shouldReceive('isPending')->andReturn(true);

        expect($this->policy->approve($user, $rma))->toBeTrue();
    });

    // reject tests
    it('denies reject for non-pending RMA', function (): void {
        $user = createUserWithPermissions(['shipping.returns.reject']);
        $rma = Mockery::mock(ReturnAuthorization::class)->makePartial();
        $rma->shouldReceive('isPending')->andReturn(false);

        expect($this->policy->reject($user, $rma))->toBeFalse();
    });

    it('allows reject when user has permission and RMA is pending', function (): void {
        $user = createUserWithPermissions(['shipping.returns.reject']);
        $rma = Mockery::mock(ReturnAuthorization::class)->makePartial();
        $rma->shouldReceive('isPending')->andReturn(true);

        expect($this->policy->reject($user, $rma))->toBeTrue();
    });

    // receive tests
    it('denies receive for non-approved RMA', function (): void {
        $user = createUserWithPermissions(['shipping.returns.receive']);
        $rma = Mockery::mock(ReturnAuthorization::class)->makePartial();
        $rma->shouldReceive('isApproved')->andReturn(false);

        expect($this->policy->receive($user, $rma))->toBeFalse();
    });

    it('allows receive when user has permission and RMA is approved', function (): void {
        $user = createUserWithPermissions(['shipping.returns.receive']);
        $rma = Mockery::mock(ReturnAuthorization::class)->makePartial();
        $rma->shouldReceive('isApproved')->andReturn(true);

        expect($this->policy->receive($user, $rma))->toBeTrue();
    });

    // complete tests
    it('denies complete for non-received RMA', function (): void {
        $user = createUserWithPermissions(['shipping.returns.complete']);
        $rma = Mockery::mock(ReturnAuthorization::class)->makePartial();
        $rma->shouldReceive('isReceived')->andReturn(false);

        expect($this->policy->complete($user, $rma))->toBeFalse();
    });

    it('allows complete when user has permission and RMA is received', function (): void {
        $user = createUserWithPermissions(['shipping.returns.complete']);
        $rma = Mockery::mock(ReturnAuthorization::class)->makePartial();
        $rma->shouldReceive('isReceived')->andReturn(true);

        expect($this->policy->complete($user, $rma))->toBeTrue();
    });
});
