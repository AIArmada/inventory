<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Shipping\Policies\ShipmentPolicy;

uses(TestCase::class);

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

    it('has viewAny method', function (): void {
        expect(method_exists($this->policy, 'viewAny'))->toBeTrue();
    });

    it('has view method', function (): void {
        expect(method_exists($this->policy, 'view'))->toBeTrue();
    });

    it('has create method', function (): void {
        expect(method_exists($this->policy, 'create'))->toBeTrue();
    });

    it('has update method', function (): void {
        expect(method_exists($this->policy, 'update'))->toBeTrue();
    });

    it('has delete method', function (): void {
        expect(method_exists($this->policy, 'delete'))->toBeTrue();
    });

    it('has ship method', function (): void {
        expect(method_exists($this->policy, 'ship'))->toBeTrue();
    });

    it('has cancel method', function (): void {
        expect(method_exists($this->policy, 'cancel'))->toBeTrue();
    });

    it('has printLabel method', function (): void {
        expect(method_exists($this->policy, 'printLabel'))->toBeTrue();
    });

    it('has syncTracking method', function (): void {
        expect(method_exists($this->policy, 'syncTracking'))->toBeTrue();
    });

    it('has restore method', function (): void {
        expect(method_exists($this->policy, 'restore'))->toBeTrue();
    });

    it('has forceDelete method', function (): void {
        expect(method_exists($this->policy, 'forceDelete'))->toBeTrue();
    });
});

describe('ShippingZonePolicy', function (): void {
    it('can be instantiated', function (): void {
        $policy = new AIArmada\Shipping\Policies\ShippingZonePolicy;

        expect($policy)->toBeInstanceOf(AIArmada\Shipping\Policies\ShippingZonePolicy::class);
    });

    it('has viewAny method', function (): void {
        $policy = new AIArmada\Shipping\Policies\ShippingZonePolicy;

        expect(method_exists($policy, 'viewAny'))->toBeTrue();
    });

    it('has manageRates method', function (): void {
        $policy = new AIArmada\Shipping\Policies\ShippingZonePolicy;

        expect(method_exists($policy, 'manageRates'))->toBeTrue();
    });
});

describe('ReturnAuthorizationPolicy', function (): void {
    it('can be instantiated', function (): void {
        $policy = new AIArmada\Shipping\Policies\ReturnAuthorizationPolicy;

        expect($policy)->toBeInstanceOf(AIArmada\Shipping\Policies\ReturnAuthorizationPolicy::class);
    });

    it('has approve method', function (): void {
        $policy = new AIArmada\Shipping\Policies\ReturnAuthorizationPolicy;

        expect(method_exists($policy, 'approve'))->toBeTrue();
    });

    it('has reject method', function (): void {
        $policy = new AIArmada\Shipping\Policies\ReturnAuthorizationPolicy;

        expect(method_exists($policy, 'reject'))->toBeTrue();
    });

    it('has receive method', function (): void {
        $policy = new AIArmada\Shipping\Policies\ReturnAuthorizationPolicy;

        expect(method_exists($policy, 'receive'))->toBeTrue();
    });

    it('has complete method', function (): void {
        $policy = new AIArmada\Shipping\Policies\ReturnAuthorizationPolicy;

        expect(method_exists($policy, 'complete'))->toBeTrue();
    });
});
