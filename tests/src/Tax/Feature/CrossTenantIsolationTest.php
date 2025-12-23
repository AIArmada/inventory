<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use AIArmada\Tax\Services\TaxCalculator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(function (): void {
    bindTaxOwner(null);
});

function bindTaxOwner(?Model $owner): void
{
    app()->bind(OwnerResolverInterface::class, fn () => new class($owner) implements OwnerResolverInterface
    {
        public function __construct(private ?Model $owner) {}

        public function resolve(): ?Model
        {
            return $this->owner;
        }
    });
}

it('blocks cross-tenant reads and writes when owner scoping is enabled', function (): void {
    config()->set('tax.features.owner.enabled', true);
    config()->set('tax.features.owner.include_global', false);
    config()->set('tax.features.zone_resolution.unknown_zone_behavior', 'zero');

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'tax-owner-a-x@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'tax-owner-b-x@example.com',
        'password' => 'secret',
    ]);

    bindTaxOwner($ownerA);

    $zoneA = TaxZone::query()->create([
        'name' => 'Zone A',
        'code' => 'ZA',
        'is_active' => true,
        'is_default' => true,
    ]);

    TaxRate::query()->create([
        'zone_id' => $zoneA->id,
        'name' => 'Rate A',
        'rate' => 600,
        'tax_class' => 'standard',
        'is_active' => true,
    ]);

    bindTaxOwner($ownerB);

    $zoneB = TaxZone::query()->create([
        'name' => 'Zone B',
        'code' => 'ZB',
        'is_active' => true,
        'is_default' => true,
    ]);

    bindTaxOwner($ownerA);

    $calculator = new TaxCalculator;

    $result = $calculator->calculateTax(10000, 'standard', $zoneB->id);

    expect($result->zone->id)
        ->toBe($zoneA->id);

    expect(fn () => TaxRate::query()->create([
        'zone_id' => $zoneB->id,
        'name' => 'Cross-tenant rate',
        'rate' => 600,
        'tax_class' => 'standard',
        'is_active' => true,
    ]))
        ->toThrow(AuthorizationException::class);
});

it('blocks deleting a global tax zone while owned rates exist', function (): void {
    config()->set('tax.features.owner.enabled', true);
    config()->set('tax.features.owner.include_global', true);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'tax-owner-a-delete-global@example.com',
        'password' => 'secret',
    ]);

    bindTaxOwner(null);

    $globalZone = TaxZone::query()->create([
        'name' => 'Global Zone',
        'code' => 'GLOBAL-ZONE-DELETE',
        'is_active' => true,
        'is_default' => false,
    ]);

    bindTaxOwner($ownerA);

    TaxRate::query()->create([
        'zone_id' => $globalZone->id,
        'name' => 'Owned Rate',
        'rate' => 600,
        'tax_class' => 'standard',
        'is_active' => true,
    ]);

    bindTaxOwner(null);

    expect(fn () => $globalZone->delete())
        ->toThrow(AuthorizationException::class);
});

it('blocks creating an exemption referencing an out-of-scope zone', function (): void {
    config()->set('tax.features.owner.enabled', true);
    config()->set('tax.features.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'tax-owner-a-exemption-zone@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'tax-owner-b-exemption-zone@example.com',
        'password' => 'secret',
    ]);

    bindTaxOwner($ownerB);

    $zoneB = TaxZone::query()->create([
        'name' => 'Zone B',
        'code' => 'ZONE-B-EXEMPTION',
        'is_active' => true,
    ]);

    bindTaxOwner($ownerA);

    expect(fn () => TaxExemption::query()->create([
        'exemptable_type' => 'App\\Models\\Customer',
        'exemptable_id' => 'customer-uuid-exemption',
        'tax_zone_id' => $zoneB->id,
        'reason' => 'Should be blocked',
        'status' => 'approved',
    ]))
        ->toThrow(AuthorizationException::class);
});
